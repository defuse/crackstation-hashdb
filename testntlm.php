<?php
    require_once('LookupTable.php');
    require_once('MoreHashes.php');

    $ntlm = new LookupTable("words-NTLM.idx", "words.txt", "NTLM");
    $hasher = MoreHashAlgorithms::GetHashFunction("NTLM");
    $to_crack = $hasher->hash("orange", false);

    $result = $ntlm->crack($to_crack);
    if ($result !== FALSE) {
        echo "Cracked: " . $result[0] . "\n";
    }
            // EOF
?>
