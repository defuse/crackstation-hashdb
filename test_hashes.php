<?php

require_once('MoreHashes.php');

$hash_algos = MoreHashAlgorithms::GetHashAlgoNames();

foreach ($hash_algos as $algo) {
    $hasher = MoreHashAlgorithms::GetHashFunction($algo);
    echo $algo . "\t" . $hasher->hash("test", false) . "\t" . $hasher->hash("rest", false);
    echo "\n";
}

?>
