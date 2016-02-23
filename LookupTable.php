<?php
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

// TODO: Support partial matches

require_once('MoreHashes.php');

define("INDEX_ENTRY_SIZE", 6+8);

class IndexFileException extends Exception {}
class DictFileException extends Exception {}
class InvalidHashTypeException extends Exception {}
class HashFormatException extends Exception {}
class Missing64BitException extends Exception {}

class LookupTable
{
    private $index;
    private $dict;
    private $hasher;
    private $cache = array();
    private $index_count;

    public function __construct($index_path, $dict_path, $hashtype)
    {
        if(PHP_INT_SIZE < 8)
            throw new Missing64BitException("This script needs 64-bit integers.");

        $this->hasher = MoreHashAlgorithms::GetHashFunction($hashtype);

        $this->index = fopen($index_path, "rb");
        if($this->index === false)
            throw new IndexFileException("Can't open index file");

        $this->dict = fopen($dict_path, "rb");
        if($this->dict === false)
            throw new DictFileException("Can't open dictionary file");

        $size = $this->getFileSize($this->index);
        if($size % INDEX_ENTRY_SIZE != 0)
            throw new IndexFileException("Invalid index file");
        $this->index_count = $size / INDEX_ENTRY_SIZE;
    }

    public function __destruct()
    {
        fclose($this->index);
        fclose($this->dict);
    }
    
    public function crack($hash)
    {
        $hash_binary = $this->getHashBinary($hash);

        $find = -1;
        $lower = 0;
        $upper = $this->index_count - 1;

        while($upper >= $lower)
        {
            $middle = $lower + (int)(($upper - $lower)/2);
            $cmp = $this->hashcmp($this->getIdxHash($this->index, $middle), $hash_binary);
            if($cmp > 0)
                $upper = $middle - 1;
            elseif($cmp < 0)
                $lower = $middle + 1;
            elseif($cmp == 0)
            {
                $find = $middle;
                break;
            }
        }

        $results = array();
        if($find >= 0)
        {
            // Walk back to find the start of collision block
            while($this->hashcmp($this->getIdxHash($this->index, $find), $hash_binary) == 0)
                $find--;
            $find++; // Get to start of block

            // Walk through the block of collisions
            while($this->hashcmp($this->getIdxHash($this->index, $find), $hash_binary) == 0)
            {
                $position = $this->getIdxPosition($this->index, $find);
                $word = $this->getWordAt($this->dict, $position);
                if($this->hasher->hash($word, true) === $hash_binary)
                {
                    $results[] = $word;
                }
                $find++;
            }
        }

        if(count($results) > 0)
            return $results;
        else
            return false;
    }

    private function getWordAt($file, $position)
    {
        fseek($file, $position);
        $word = fgets($file);
        return trim($word);
    }

    private function hashcmp($hashA, $hashB)
    {
        for($i = 0; $i < 8; $i++)
        {
            if($hashA[$i] < $hashB[$i])
                return -1;
            if($hashA[$i] > $hashB[$i])
                return 1;
        }
        return 0;
    }

    private function getIdxHash($file, $index)
    {
        if(array_key_exists($index, $this->cache))
            return $this->cache[$index];

        fseek($file, $index * (6+8));
        $hash = fread($file, 8);
        if (strlen($hash) == 8) {
            $this->cache[$index] = $hash;
            return $hash;
        } elseif (strlen($hash) == 0) {
            // FIXME: hack for hashcmp to fail when we reach EOF.
            // This isn't guaranteed to be correct, but it probably
            // works assuming the file is sorted and there's at least
            // one non-zero hash!

            // You can trigger this case by trying to crack the last hash in the
            // index, e.g. run `xxd -c 14 whatever.idx`.
            return "\x00\x00\x00\x00\x00\x00\x00\x00";
        } else {
            throw new Exception("Something is wrong with the index!");
        }
    }

    private function getIdxPosition($file, $index)
    {
        fseek($file, $index * (6+8) + 8);
        $binary = fread($file, 6);
        $value = 0;
        for($i = 5; $i >= 0; $i--)
        {
            $value = $value << 8;
            $value += ord($binary[$i]);
        }
        return $value;
    }

    private function getHashBinary($hash)
    {
        if(!$this->isValidHash($hash))
            throw new HashFormatException("Invalid hash");
        $binary = pack("H*", $hash);
        if(strlen($binary) < 8)
            throw new HashFormatException("Hash too small");
        return $binary;
    }

    private function getFileSize($handle)
    {
        fseek($handle, 0, SEEK_END);
        return ftell($handle);
    }

    private function isValidHash($hash_str)
    {
        // Make sure the hash "looks right"
        $sample = $this->hasher->hash("", false);
        return strlen($sample) === strlen($hash_str);
    }

}

?>
