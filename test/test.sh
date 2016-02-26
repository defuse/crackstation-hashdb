#!/bin/bash

set -e

# Rebuild ./sortidx and ./checksort
make clean
make all

hashTypes=( "md5" "sha1" "NTLM" "LM" "MySQL4.1+" "md5(md5)" )

mkdir -p test-index-files

for hash in "${hashTypes[@]}"; do
    echo "TESTING [$hash]..."
    php createidx.php "$hash" "test/words.txt" "test-index-files/test-words-$hash.idx"
    ./sortidx "test-index-files/test-words-$hash.idx"
    ./checksort "test-index-files/test-words-$hash.idx"
    php test/test.php "$hash"
done

echo ""
echo "Tests passed."
