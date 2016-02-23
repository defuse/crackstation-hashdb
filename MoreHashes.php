<?php

interface HashAlgorithm
{
    public function hash($input, $raw);
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

class NTLMHashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        // Convert the password from UTF8 to UTF16 (little endian)
        $input=@iconv('UTF-8','UTF-16LE',$input);
        $MD4Hash=hash('md4',$input, $raw);
        return $MD4Hash;
    }
}

class MD5MD5HexHashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        return hash("md5", hash("md5", $input, false), $raw);
    }
}

class MySQL41HashAlgorithm implements HashAlgorithm
{
    public function hash($input, $raw)
    {
        return hash("sha1", hash("sha1", $input, true), $raw);
    }
}

class MoreHashAlgorithms
{
    public static function GetHashAlgoNames()
    {
        $extra_algos = array('LM', 'NTLM', 'md5(md5)', 'MySQL4.1+');
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
            default:
                throw new Exception("Unknown algorithm name.");
            }
        }
    }

}
