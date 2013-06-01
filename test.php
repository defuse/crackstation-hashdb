<?php
    require_once('LookupTable.php');

    $md5 = new LookupTable("words-md5.idx", "words.txt", "md5");
    $to_crack = md5("grape");

    $result = $md5->crack($to_crack);
    if ($result !== FALSE) {
        echo "Cracked: " . $result[0] . "\n";
    }
?>
