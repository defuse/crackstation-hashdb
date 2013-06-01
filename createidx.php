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

/*
 * Usage: php createidx.php <hashtype> <wordlist> <output>
 *
 * hashtype - The type of hash. See the php hash() function documentation.
 * wordlist - A list of passwords/words. Lines SHOULD end with a single LF (ASCII 0x0A).
 * output - Where to store output, the index file.
 *
 * The first step in creating a cryptographic hash lookup table.
 *  Creates a file of the following format:
 *
 *      [HASH_PART][WORDLIST_OFFSET][HASH_PART][WORDLIST_OFFSET]...
 *
 *   HASH_PART is the first 64 BITS of the hash, right-padded with zeroes if
 *   necessary.  WORDLIST_OFFSET is the position of the first character of the
 *   word in the dictionary encoded as a 48-bit LITTLE ENDIAN integer.
 *
 * NOTE: This only supports the hashes supported by php's hash() function. If 
 *       you want more, just modify the code.
 */

if(PHP_INT_SIZE < 8)
{
    echo "This script requires 64-bit PHP.\n";
    die();
}

if($argc < 4)
{
    printUsage();
    die();
}

$hashType = strtolower($argv[1]);
$wordlistFile = $argv[2];
$outputFile = $argv[3];

if(!in_array($hashType, hash_algos()))
{
    echo "Unknown hash algorithm ($hashType)!\n";
    die();
}

if(($wordlist = fopen($wordlistFile, "rb")) == FALSE)
{
    echo "Couldn't open wordlist file.\n";
    die();
}

if(($index = fopen($outputFile, "wb")) == FALSE)
{
    echo "Could not open wordlist file.\n";
    die();
}

$progressLines = 0;
$position = ftell($wordlist);
while(($word = fgets($wordlist)) !== FALSE)
{
    $word = trim($word, "\n\r"); // Get rid of any extra newline characters, but don't get rid of spaces or tabs.
    $hash = getFirst64Bits(hash($hashType, $word, true));
    fwrite($index, $hash);
    fwrite($index, encodeTo48Bits($position));

    $position = ftell($wordlist);
    $progressLines++;
    if($progressLines % 100000 == 0) // Arbitrary.
    {
        $gb = round((double)$position / pow(1024, 3), 3);
        echo "So far, completed $progressLines lines (${gb}GB) ...\n";
    }
}

fclose($wordlist);
fclose($index);

echo "Index creation complete. Please sort the index using the C program.\n";

/* Encode 64 bit integer to 48-bit little endian */
function encodeTo48Bits($n)
{
    // 6 Bytes = 48-bits.
    $foo = array('\0', '\0', '\0', '\0', '\0', '\0');
    for($i = 0, $p = 0; $i < 48; $i+=8, $p++)
        $foo[$p] = chr(($n >> $i) % 256);
    return implode('', $foo);
}

/* Get first 64 bits of binary hash
    * Always padded to 64 bits with null bytes
    */
function getFirst64Bits($binaryHash)
{
    $wantlength =  8; // 8 Bytes = 64 bits.
    $getlength = 8;
    if(strlen($binaryHash) < $getlength)
        $getlength = strlen($binaryHash);
    $result = substr($binaryHash, 0, $getlength);
    if($getlength < $wantlength)
        $result .= str_repeat('\0', $wantlength - $getlength);
    return $result;
}


function printUsage()
{
    echo "Usage: php createidx.php <hashtype> <wordlist> <output>\n\n";
    echo "hashtype - Hash algorithm. See: hash()\n";
    echo "wordlist - Dictionary file.\n";
    echo "output - Index output file.\n";
}
?>
