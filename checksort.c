#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <inttypes.h>

#define INDEX_HASH_WIDTH 8
#define INDEX_POSITION_WIDTH 6
#define INDEX_ENTRY_WIDTH (INDEX_HASH_WIDTH + INDEX_POSITION_WIDTH)

struct IndexEntry {
    /* The leading 64 bits of the hash. */
    unsigned char hash[INDEX_HASH_WIDTH]; // First 64 bits of the hash
    unsigned char position[INDEX_POSITION_WIDTH]; // Position of word in dictionary (48-bit little endian integer)
};

void freadIndexEntryAt(FILE* file, int64_t index, struct IndexEntry* out)
{
    size_t fr1, fr2;
    fr1 = 0;
    fr2 = 0;
    fseek(file, index * INDEX_ENTRY_WIDTH, SEEK_SET);
    fr1 = fread(out->hash, sizeof(unsigned char), INDEX_HASH_WIDTH, file);
    fr2 = fread(out->position, sizeof(unsigned char), INDEX_POSITION_WIDTH, file);
    if (fr1 != INDEX_HASH_WIDTH) {
        fprintf(stderr,"hash width != %d\n",INDEX_HASH_WIDTH);
        exit(1);
    } else if (fr2 != INDEX_POSITION_WIDTH) {
        fprintf(stderr,"position width != %d\n",INDEX_POSITION_WIDTH);
        exit(1);
    }
}

/*
 * Compares two INDEX_HASH_WIDTH-char arrays.
 * Returns 1 if the first argument is greater than the second.
 * Returns -1 if the first argument is less than the second.
 * Returns 0 if both are equal.
 */
int hashcmp(const unsigned char hashA[INDEX_HASH_WIDTH], const unsigned char hashB[INDEX_HASH_WIDTH])
{
    int i = 0;
    for(i = 0; i < INDEX_HASH_WIDTH; i++)
    {
        if(hashA[i] > hashB[i]) {
            return 1;
        } else if(hashA[i] < hashB[i]) {
            return -1;
        }
    }
    return 0;
}


int main(int argc, char **argv)
{
    struct IndexEntry current, max;
    FILE* file = fopen(argv[1], "rb");

    memset(&current,0,sizeof(current));
    memset(&max,0,sizeof(max));
    if(file == NULL)
    {
        printf("File does not exist.\n");
        return 3;
    }

    fseek(file, 0L, SEEK_END);
    int64_t size = ftell(file);
    if(size % (int64_t)INDEX_ENTRY_WIDTH != 0)
    {
        printf("Invalid index file!\n");
        return 1;
    }
    int64_t numEntries = size / (int64_t)INDEX_ENTRY_WIDTH;

    int64_t i;

    for(i = 0; i < numEntries; i++)
    {
        freadIndexEntryAt(file, i, &current);
        if(hashcmp(current.hash, max.hash) < 0) // Current is less than max
        {
            printf("NOT SORTED!!!!\n");
            exit(1);
        }
        max = current;
        if(i % 10000000 == 0)
        {
            printf("%ld...\n", i);
        }
    }

    printf("ALL SORTED!\n");
    exit(0);
}
