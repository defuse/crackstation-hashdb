<?php
// Based on test.php
// https://github.com/defuse/crackstation-hashdb/issues/10

require_once('LookupTable.php');
require_once('MoreHashes.php');

if (count($argv) !== 2) {
    echo "Usage: php test.php <hash type>\n";
    exit(1);
}

function menu() {
	echo "Enter hash: ";
	$hash = readline();
	return $hash;
}

$hash_algorithm = $argv[1];
$lookup = new LookupTable("test-index-files/test-words-$hash_algorithm.idx", "test/words.txt", $hash_algorithm);

$hasher = MoreHashAlgorithms::GetHashFunction($hash_algorithm);

echo "To exit type: quit\n";

$to_crack = "";
while ( ($to_crack = menu()) !== "quit") {

	$results = $lookup->crack($to_crack);
    
    if (count($results) > 0) {
    	$cracked = $to_crack . ":" . $results[0]->getPlaintext() . "\n";
    }
    else {
    	$cracked = "Nothing for " . $hash_algorithm . ":" . $to_crack . "\n";
    }

    echo $cracked;

    // Partial match (first 8 bytes, 16 hex chars).
    $to_crack = substr($to_crack, 0, 16);
    $results = $lookup->crack($to_crack);

    if (count($results) > 0) {
    	$cracked = $to_crack . ":" . $results[0]->getPlaintext() . " (partial match)\n";
    }
    else {
    	$cracked = "Nothing for " . $hash_algorithm . ":" . $to_crack . "\n";
    }

    echo $cracked;

}