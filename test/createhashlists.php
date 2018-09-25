<?php

// Create some hashes, use words we have and those we don't
// sha1 and md5 for the moment

$words = [
	"apple", "banana", "fruit", "grape", "grapes", "gr33ce!", "orange", "zρπΣ", "hello", "goodbye",
	"bananas", "minions", "google", "dash", "p@55w0rd", "password!", "simple", "list", "london",
	"sydney", "applepie", "laptop", "cpu", "gam3r", "foo", "bar", "baz", "bazooka", "hash", "crack",
	"nevermind", "yuck", "escargot", "prénom", "navn", "camera", "batman", "robin", "bitcoins", "bond007",
	"mountain", "newyork", "thx123", "trustno1", "abc123", "test123", "123abc", "maxwell", "hockey",
	"dolphins", "hunter", "mustang", "monkey", "airborne", "rush2012", "123456", "trucker", "metallic",
	"liverpool1", "walthamstow", "monica", "biteme!", "star", "qwerty", "qwertyuiop", "elephant", "angela",
	"lifehack", "h4ck3r", "ladybug", "butterfly", "fishing123", "speedboat", "codez", "snowball5", "natasha",
	"einstein1337", "charlie", "hax0r", "pyjamas", "slippers", "eggs", "spam", "sandwich", "wizard", "yellow",
	"chicken", "snoopy", "peanuts", "whatever", "forgotpassword", "dakota", "smokey", "qwer1234", "monster",
	"tigers", "hello1", "letmein", "pepper", "turtle"
];

// Ensure the list of hash algorithms matches the file names below
$fnames = [
	"sha1_hashes.txt", "md5_hashes.txt", "whirlpool_hashes.txt", "md5md5_hashes.txt"
];

$algorithms = [
	"sha1", "md5", "whirlpool", "md5" // special case, see below
];

$iters = [
	1, 1, 1, 2	// twice for this, i.e. md5(md5())
];

// Make sure this is not out of bounds, it indexes into algorithms and iters
$position = 0;

foreach ($fnames as $fn) {
	
	$fp = fopen($fn, "w");
	if ($fp === false) {
		echo "Failed to open $fn\n";
		exit(1);
	}

	foreach ($words as $w) {

		$digest = hash($algorithms[$position], $w);

		if ($iters[$position] > 1) {
			for ($i = 1; $i < $iters[$position]; $i++) {
				$digest = hash($algorithms[$position], $digest);
			}
		}

		fwrite($fp, "$digest\n");
	}

	fclose($fp);

	$position = $position + 1;
}

?>