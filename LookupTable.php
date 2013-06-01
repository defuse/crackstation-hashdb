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
    private $hashtype;
    private $builtin_hash;
    private $cache = array();
    private $index_count;

    public function __construct($index_path, $dict_path, $hashtype)
    {
        if(PHP_INT_SIZE < 8)
            throw new Missing64BitException("This script needs 64-bit integers.");

        $this->builtin_hash = in_array($hashtype, hash_algos());
        if(!$this->isValidHashType($hashtype))
            throw new InvalidHashTypeException('Unsupported hash type');

        $this->hashtype = $hashtype;

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
                if($this->computeHash($this->hashtype, $word, true) === $hash_binary)
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
        return ($this->cache[$index] = fread($file, 8));
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

    private function isValidHashType($type)
    {
        return $this->computeHash($type, "", true) !== false;
    }

    private function isValidHash($hash_str)
    {
        // Make sure the hash "looks right"
        $sample = $this->computeHash($this->hashtype, "", false);
        if($this->hashtype != "crypt" && strlen($sample) != strlen($hash_str))
            return false;
        return true;
    }

    private function computeHash($hash_type, $plaintext, $binary)
    {
        if($this->builtin_hash)
        {
            return hash($hash_type, $plaintext, $binary);
        }
        elseif($hash_type == "ntlm")
        {
            $hash = $this->NTLMHash($plaintext);
            if($binary)
                return $hash;
            else
                return bin2hex($hash);
        }
        elseif($hash_type == "md5md5")
        {
            return hash("md5", hash("md5", $plaintext), $binary);
        }
        elseif($hash_type == "mysql41")
        {
            return hash("sha1", hash("sha1", $plaintext, true), $binary);
        }
        elseif($hash_type == "lm")
        {
             $hash = $this->LMHash($plaintext);        
             if($binary)
                 return $hash;
             else
                 return bin2hex($hash);
        }
        else
        {
            return false;
        }
    }

    // NTLM Code Source: http://www.php.net/manual/en/ref.hash.php#82018
    private function NTLMHash($Input) {
      // Convert the password from UTF8 to UTF16 (little endian)
      $Input=@iconv('UTF-8','UTF-16LE',$Input);
      $MD4Hash=hash('md4',$Input, true);
      return $MD4Hash;
    }

    // LM Code Source: http://www.php.net/manual/en/ref.hash.php#84587
    private function LMhash($string)
    {
        $string = strtoupper(substr($string,0,14));
    
        $p1 = $this->LMhash_DESencrypt(substr($string, 0, 7));
        $p2 = $this->LMhash_DESencrypt(substr($string, 7, 7));
    
        return $p1.$p2;
    }
    
    private function LMhash_DESencrypt($string)
    {
        $key = array();
        $tmp = array();
        $len = strlen($string);
    
        for ($i=0; $i<7; ++$i)
            $tmp[] = $i < $len ? ord($string[$i]) : 0;
    
        $key[] = $tmp[0] & 254;
        $key[] = ($tmp[0] << 7) | ($tmp[1] >> 1);
        $key[] = ($tmp[1] << 6) | ($tmp[2] >> 2);
        $key[] = ($tmp[2] << 5) | ($tmp[3] >> 3);
        $key[] = ($tmp[3] << 4) | ($tmp[4] >> 4);
        $key[] = ($tmp[4] << 3) | ($tmp[5] >> 5);
        $key[] = ($tmp[5] << 2) | ($tmp[6] >> 6);
        $key[] = $tmp[6] << 1;
      
        $is = mcrypt_get_iv_size(MCRYPT_DES, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($is, MCRYPT_RAND);
        $key0 = "";
      
        foreach ($key as $k)
            $key0 .= chr($k);
        $crypt = mcrypt_encrypt(MCRYPT_DES, $key0, "KGS!@#$%", MCRYPT_MODE_ECB, $iv);
    
        return $crypt;
    }
}

?>
