<?php

interface HashAlgorithm
{
    public function hash($input, $raw);
    public function getAlgorithmName();
}

class StandardHashAlgorithm implements HashAlgorithm
{
    private $algorithm;

    function __construct($algorithm)
    {
        if (!in_array($algorithm, hash_algos())) {
            throw new Exception("Not a standard algorithm.");
        }
        $this->algorithm = $algorithm;
    }

    public function hash($input, $raw)
    {
        return hash($this->algorithm, $input, $raw);
    }

    public function getAlgorithmName()
    {
        return $this->algorithm;
    }
}

class LMHashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        $hash = $this->LMHash($input);
        if (!$raw) {
            $hash = bin2hex($hash);
        }
        return $hash;
    }

    private function LMhash($string)
    {
        $string = strtoupper(substr($string,0,14));
        $string .= str_repeat("\x00", 14 - strlen($string));

        $p1 = $this->LMhash_DESencrypt(substr($string, 0, 7));
        $p2 = $this->LMhash_DESencrypt(substr($string, 7, 7));

        return $p1.$p2;
    }

    private function LMhash_DESencrypt($string)
    {
        $key = "\x00\x00\x00\x00\x00\x00\x00\x00";
        $key[0] = chr(ord($string[0]) & 254);
        $key[1] = chr((ord($string[0]) << 7) | (ord($string[1]) >> 1));
        $key[2] = chr((ord($string[1]) << 6) | (ord($string[2]) >> 2));
        $key[3] = chr((ord($string[2]) << 5) | (ord($string[3]) >> 3));
        $key[4] = chr((ord($string[3]) << 4) | (ord($string[4]) >> 4));
        $key[5] = chr((ord($string[4]) << 3) | (ord($string[5]) >> 5));
        $key[6] = chr((ord($string[5]) << 2) | (ord($string[6]) >> 6));
        $key[7] = chr(ord($string[6]) << 1);
    
        $crypt = openssl_encrypt("KGS!@#$%", "des-ecb", $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        return $crypt;
    }

    public function getAlgorithmName()
    {
        return "LM";
    }
}

class NTLMHashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        // Convert the password from UTF8 to UTF16 (little endian)
        $input=@iconv('UTF-8','UTF-16LE',$input);
        if ($input === false) {
            return false;
        }
        $MD4Hash=hash('md4',$input, $raw);
        return $MD4Hash;
    }

    public function getAlgorithmName()
    {
        return "NTLM";
    }
}

class MD5MD5HexHashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        return hash("md5", hash("md5", $input, false), $raw);
    }

    public function getAlgorithmName()
    {
        return "md5(md5)";
    }
}

class MySQL41HashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        return hash("sha1", hash("sha1", $input, true), $raw);
    }

    public function getAlgorithmName()
    {
        return "MySQL4.1+";
    }
}

class QubesV31BackupDefaultsHashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        $default_backup_header = "version=3\nhmac-algorithm=SHA512\ncrypto-algorithm=aes-256-cbc\nencrypted=True\ncompressed=False\n";
        return hash_hmac("sha512", $default_backup_header, $input, $raw);
    }

    public function getAlgorithmName()
    {
        return "QubesV31DefaultBackup";
    }
}

class MoreHashAlgorithms
{
    public static function GetHashAlgoNames()
    {
        $extra_algos = array('LM', 'NTLM', 'md5(md5)', 'MySQL4.1+', 'QubesV3.1BackupDefaults');
        return array_merge(hash_algos(), $extra_algos);
    }

    public static function GetHashFunction($algorithm)
    {
        if (in_array($algorithm, hash_algos())) {
            return new StandardHashAlgorithm($algorithm);
        } else {
            switch ($algorithm) {
            case "LM":
                return new LMHashAlgorithm();
                break;
            case "NTLM":
                return new NTLMHashAlgorithm();
                break;
            case "md5(md5)":
                return new MD5MD5HexHashAlgorithm();
                break;
            case "MySQL4.1+":
                return new MySQL41HashAlgorithm();
                break;
            case "QubesV3.1BackupDefaults":
                return new QubesV31BackupDefaultsHashAlgorithm();
                break;
            default:
                throw new Exception("Unknown algorithm name.");
            }
        }
    }

}
