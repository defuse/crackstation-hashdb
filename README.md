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

Lookup By Hash
--------------

A new program under test/ called lookup_hash.php is usable after running make test.
Example run:

    $ php test/lookup_hash.php sha1
    To exit type: quit
    Enter hash: e68e11be8b70e435c65aef8ba9798ff7775c361e
    e68e11be8b70e435c65aef8ba9798ff7775c361e:trustno1
    e68e11be8b70e435:trustno1 (partial match)
    Enter hash: quit

lookup_hash.php also accepts a file name of hashes.  For example:

    $ php test/lookup_hash.php sha1 test/sha1_hashes.txt
    D0BE2DC421BE4FCD0172E5AFCEEA3970E2F3D940:apple
    D0BE2DC421BE4FCD:apple (partial match)
    250E77F12A5AB6972A0895D290C4792F0A326EA8:banana
    250E77F12A5AB697:banana (partial match)
    B081F21CD313C2DE90142B4E815BE841AEA0A897:minions
    B081F21CD313C2DE:minions (partial match)
    E68E11BE8B70E435C65AEF8BA9798FF7775C361E:trustno1
    E68E11BE8B70E435:trustno1 (partial match)
    8A4403E154C81595E2859F9C5559B9FFF6C610C3:123456Seven
    8A4403E154C81595:123456Seven (partial match)
    Nothing for sha1:6DB82964033D81A17884BDD215C407D12FBDD282
    Nothing for sha1:6DB82964033D81A1


Adding Words
------------

Once a wordlist has been indexed, you can not modify the wordlist file without
breaking the indexes. Appending to the wordlist is safe in that it will not
break the indexes, but the words you append  won't be indexed, unless you
re-generate the index. There is currently no way to add words to an index
without re-generating the entire index.
