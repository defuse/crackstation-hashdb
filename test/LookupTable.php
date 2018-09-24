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

require_once('MoreHashes.php');

define("INDEX_ENTRY_SIZE", 6+8);

class IndexFileException extends Exception {}
class DictFileException extends Exception {}
class InvalidHashTypeException extends Exception {}
class HashFormatException extends Exception {}
class Missing64BitException extends Exception {}

class HashCrackResult
{
    private $plaintext;
    private $given_hash_raw;
    private $full_hash_raw;
    private $algorithm_name;

    function __construct($plaintext, $given_hash_raw, $full_hash_raw, $algorithm_name)
    {
        $this->plaintext = $plaintext;
        $this->given_hash_raw = $given_hash_raw;
        $this->full_hash_raw = $full_hash_raw;
        $this->algorithm_name = $algorithm_name;
    }

    public function isFullMatch()
    {
        return strtolower($this->given_hash_raw) === strtolower($this->full_hash_raw);
    }

    public function getPlaintext()
    {
        return $this->plaintext;
    }

    public function getGivenHashBytes()
    {
        return $this->given_hash_raw;
    }

    public function getRecomputedFullHashBytes()
    {
        return $this->full_hash_raw;
    }

    public function getAlgorithmName()
    {
        return $this->algorithm_name;
    }
}

class LookupTable
{
    private $index;
    private $dict;
    private $hasher;
    private $cache = array();
    private $index_count;

    // The minimum length of prefix that needs to match for it to be considered
    // a "partial match." This value must not be greater than 8, since only
    // the 8 leading bytes of the hash are stored in the index.
    const PARTIAL_MATCH_PREFIX_BYTES = 8;

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
    
    /*
     * Attempts to crack $hash by interpreting it as (the prefix of) a hash of
     * the set type. Returns all partial matches, i.e. at least the first
     * PARTIAL_MATCH_PREFIX_BYTES are in agreement, as a list of HashCrackResult.
     */
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

            // Walk through the block of collisions (partial and full matches)
            while($this->hashcmp($this->getIdxHash($this->index, $find), $hash_binary) == 0)
            {
                $position = $this->getIdxPosition($this->index, $find);
                $word = $this->getWordAt($this->dict, $position);
                $full_hash_raw = $this->hasher->hash($word, true);
                $results[] = new HashCrackResult(
                    $word,
                    $hash_binary,
                    $full_hash_raw,
                    $this->hasher->getAlgorithmName()
                );
                $find++;
            }
        }

        return $results;
    }

    private function getWordAt($file, $position)
    {
        fseek($file, $position);
        $word = fgets($file);
        return trim($word);
    }

    private function hashcmp($hashA, $hashB)
    {
        for($i = 0; $i < self::PARTIAL_MATCH_PREFIX_BYTES && $i < 8; $i++)
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
            throw new IndexFileException("Something is wrong with the index!");
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
        if (preg_match('/^([A-Fa-f0-9]{2})+$/', $hash) !== 1) {
            throw new HashFormatException("Hash is not a valid hex string.");
        }
        $binary = pack("H*", $hash);
        if(strlen($binary) < self::PARTIAL_MATCH_PREFIX_BYTES) {
            throw new HashFormatException("Hash too small");
        }
        return $binary;
    }

    private function getFileSize($handle)
    {
        fseek($handle, 0, SEEK_END);
        return ftell($handle);
    }

}

?>
