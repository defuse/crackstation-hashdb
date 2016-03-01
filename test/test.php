<?php
// A continuation of test.sh...

require_once('LookupTable.php');
require_once('MoreHashes.php');

if (count($argv) !== 2) {
    echo "Usage: php test.php <hash type>\n";
    exit(1);
}

$hash_algorithm = $argv[1];
$lookup = new LookupTable("test-index-files/test-words-$hash_algorithm.idx", "test/words.txt", $hash_algorithm);

$hasher = MoreHashAlgorithms::GetHashFunction($hash_algorithm);

$fh = fopen("test/words.txt", "r");
if ($fh === false) {
    echo "Error opening words.txt";
    exit(1);
}

while (($line = fgets($fh)) !== false) {
    $word = rtrim($line, "\r\n");

    // words.txt must be in sorted order for this to work!
    $count = 1;
    while (($line = fgets($fh)) !== false) {
        if ($hasher->hash(rtrim($line, "\r\n"), false) !== $hasher->hash($word, false)) {
            fseek($fh, -1 * strlen($line), SEEK_CUR);
            break;
        }
        $count++;
    }

    // Full match.
    $to_crack = $hasher->hash($word, false);
    $results = $lookup->crack($to_crack);
    if (count($results) !== $count || $results[0]->getPlaintext() !== "$word" || $results[0]->isFullMatch() !== true) {
        echo "FAILURE: Expected to crack [$word] but did not.\n";
        exit(1);
    } else {
        $cracked = $results[0]->getPlaintext();
        echo "Successfully cracked [$cracked].\n";
    }

    foreach ($results as $result) {
        if ($result->getAlgorithmName() !== $hash_algorithm) {
            echo "Algorithm name is not set correctly (full match).";
            exit(1);
        }
    }

    // Partial match (first 8 bytes, 16 hex chars).
    $to_crack = substr($to_crack, 0, 16);
    $results = $lookup->crack($to_crack);

    if (count($results) !== $count || $results[0]->getPlaintext() !== "$word" || $results[0]->isFullMatch() !== false) {
        echo "FAILURE: Expected to crack [$word] (as partial match) but did not.\n";
        exit(1);
    } else {
        $cracked = $results[0]->getPlaintext();
        echo "Successfully cracked [$cracked] (as partial match).\n";
    }

    foreach ($results as $result) {
        if ($result->getAlgorithmName() !== $hash_algorithm) {
            echo "Algorithm name is not set correctly (partial match).";
            exit(1);
        }
    }

}

fclose($fh);

?>
