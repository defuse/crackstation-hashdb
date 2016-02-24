#!/bin/bash

set -e

# Build ./sortidx
make

hashTypes=( "md5" "sha1" "NTLM" "LM" "MySQL4.1+" "md5(md5)" )

for hash in "${hashTypes[@]}"; do
    echo "TESTING [$hash]..."
    php createidx.php "$hash" "words.txt" "words-$hash.idx"
    ./sortidx "words-$hash.idx"
    php test.php "$hash"
done

