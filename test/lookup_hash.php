<?php
// Based on test.php
// https://github.com/defuse/crackstation-hashdb/issues/10

require_once('LookupTable.php');
require_once('MoreHashes.php');

if (count($argv) < 2 || count($argv) > 3) {
    echo "Usage: php test.php <hash type> [file_with_hashes]\n";
    exit(1);
}

function menu() {
	echo "Enter hash: ";
	$hash = readline();
	return $hash;
}

function crack_hashes($hasher, $lookup, $hash_algorithm, $fname, $interactive = false) {

	if (!$interactive) {
		
		// Non-interactive mode
		$fp = fopen($fname, 'r');
	
		if ($fp === false) {
    		echo "Error opening " . $fname . "\n";
    		exit(1);
		}

		while (($to_crack = fgets($fp)) !== false) {

			$to_crack = rtrim($to_crack, "\r\n");

			// Ugly code duplication... fixme pls
			$results = $lookup->crack($to_crack);
    
    		if (count($results) > 0) {
    			echo $cracked = $to_crack . ":" . $results[0]->getPlaintext() . "\n";
    		}
    		else {
    			echo $cracked = "Nothing for " . $hash_algorithm . ":" . $to_crack . "\n";
    		}

    		// Partial match (first 8 bytes, 16 hex chars).
    		$to_crack = substr($to_crack, 0, 16);
    		$results = $lookup->crack($to_crack);

    		if (count($results) > 0) {
    			echo $cracked = $to_crack . ":" . $results[0]->getPlaintext() . " (partial match)\n";
    		}
    		else {
    			echo $cracked = "Nothing for " . $hash_algorithm . ":" . $to_crack . "\n";
    		}
		}

		fclose($fp);

		exit(0);
	} // non interactive mode
	

	// interactive mode
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

	} // end interactive mode, user types "quit"
}

$hash_algorithm = $argv[1];
$lookup = new LookupTable("test-index-files/test-words-$hash_algorithm.idx", "test/words.txt", $hash_algorithm);

$hasher = MoreHashAlgorithms::GetHashFunction($hash_algorithm);

// Set to true unless we are given a file name for non-interactive mode below
$interactive_mode = true;
$file_name = "";

if (count($argv) === 3) {
	$interactive_mode = false;
	$file_name = $argv[2];
}

crack_hashes($hasher, $lookup, $hash_algorithm, $file_name, $interactive_mode);
