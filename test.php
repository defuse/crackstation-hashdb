<?php
// A continuation of test.sh...

require_once('LookupTable.php');
require_once('MoreHashes.php');

if (count($argv) !== 2) {
    echo "Usage: php test.php <hash type>\n";
    exit(1);
}

$hash = $argv[1];
$lookup = new LookupTable("words-$hash.idx", "words.txt", $hash);

$hasher = MoreHashAlgorithms::GetHashFunction($hash);

$fh = fopen("words.txt", "r");
if ($fh === false) {
    echo "Error opening words.txt";
    exit(1);
}

while (($line = fgets($fh)) !== false) {
    $word = rtrim($line, "\r\n");
    $to_crack = $hasher->hash($word, false);
    $results = $lookup->crack($to_crack);
    if (count($results) !== 1 || $results[0]->getPlaintext() !== "$word") {
        echo "FAILURE: Expected to crack [$word] but did not.\n";
        exit(1);
    } else {
        $cracked = $results[0]->getPlaintext();
        echo "Successfully cracked [$cracked].\n";
    }
}

fclose($fh);

?>
