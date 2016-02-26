[CrackStation.net](http://crackstation.net/)'s Lookup Tables
============================================================

Introduction
------------

There are three components to this system:

1. The indexing PHP script (createidx.php), which takes a wordlist and builds
   a lookup table index for a hash function and the words in the list.

2. The indexing sorter program (sortidx.c), which sorts an index created by the
   indexing script, so that the lookup script can use a binary search on the 
   index to crack hashes.

3. The lookup script (LookupTable.php), which uses the wordlist and index to
   crack hashes.

The system is split up like this because PHP provides easy access to many
different types of hash functions, but is too slow to sort large indexes in
a reasonable amount of time. We are planning to re-write components #1 and #3 in
C or C++.

Building and Testing
--------------------

The PHP scripts to not need to be built. To build the C programs, run `make`.

To run the tests, just run `make test`, and then clean up the files the tests
created with `make testclean`.

Indexing a Dictionary
---------------------

Suppose you have a password dictionary in the file `words.txt` and you would
like to index it for MD5 and SHA1 cracking.

First, create the MD5 and SHA1 indexes:

    $ php createidx.php md5 words.txt words-md5.idx
    $ php createidx.php sha1 words.txt words-sha1.idx

Next, use the sortidx program to sort the indexes:

    $ ./sortidx -r 256 words-md5.idx
    $ ./sortidx -r 256 words-sha256.idx

The -r parameter is the maximum amount of memory sortidx is allowed to use in
MiB. The more memory you let it use, the faster it will go. Give it as much as
your system will allow.

You now have everything you need to crack MD5 and SHA1 hashes quickly.

Cracking Hashes
---------------

Once you have generated and sorted the index, you can use the LookupTable class
to crack hashes. See test/test.php for an example of how to use it.

Adding Words
------------

Once a wordlist has been indexed, you can not modify the wordlist file without
breaking the indexes. Appending to the wordlist is safe in that it will not
break the indexes, but the words you append  won't be indexed, unless you
re-generate the index. There is currently no way to add words to an index
without re-generating the entire index.
