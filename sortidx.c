#include <stdio.h>
#include <stdint.h>
#include <string.h>
#include <stdlib.h>
#include <errno.h>
#include <time.h>

#define DEFAULT_MEMORY 256 * 1024 * 1024 // 256 MiB
#define INDEX_HASH_WIDTH 8
#define INDEX_POSITION_WIDTH 6
#define INDEX_ENTRY_WIDTH (INDEX_HASH_WIDTH + INDEX_POSITION_WIDTH)
#define INSERTIONSORT_THRESHOLD 16

struct IndexEntry {
    unsigned char hash[INDEX_HASH_WIDTH]; // First 64 bits of the hash
    unsigned char position[INDEX_POSITION_WIDTH]; // Position of word in dictionary (48-bit little endian integer)
} __attribute__((packed)) ;

void printUsage();
int sortFile(FILE *file, struct IndexEntry *sortBuffer, int64_t bufcount);
void quickSortFile(FILE* file, int64_t lowerIdx, int64_t upperIdx, struct IndexEntry *sortBuffer, int64_t bufcount);
int64_t partitionFile(FILE* file, int64_t lowerIdx, int64_t upperIdx);
void quickSortMemory(struct IndexEntry *sortBuffer, int64_t lowerIdx, int64_t upperIdx);
int64_t partitionMemory(struct IndexEntry *sortBuffer, int64_t lowerIdx, int64_t upperIdx);
void insertionSortMemory(struct IndexEntry *sortBuffer, int64_t lowerIdx, int64_t upperIdx);

int hashcmp(const unsigned char hashA[INDEX_HASH_WIDTH], const unsigned char hashB[INDEX_HASH_WIDTH]);
void freadIndexEntryAt(FILE* file, int64_t index, struct IndexEntry* out);
void fwriteIndexEntryAt(FILE* file, int64_t index, struct IndexEntry* in);
void loadFileToBuffer(FILE* file, struct IndexEntry* buffer, int64_t lowerIdx, int64_t upperIdx, int64_t bufsize);
void writeBufferToFile(FILE* file, struct IndexEntry* buffer, int64_t lowerIdx, int64_t writeCount);


int main(int argc, char** argv)
{
    int64_t bufsize = DEFAULT_MEMORY;
    int64_t bufcount = 0;
    FILE *index = NULL;
    struct IndexEntry *sortBuffer;

    if(argc < 2)
    {
        printUsage("Not enough arguments.");
        return 1;
    }

    if (strcmp("-r", argv[1]) == 0) {
        /* Usage: ./sortidx -r xyz abc.idx */
        if (argc != 4) {
            printUsage("Wrong number of arguments.");
            return 1;
        }
        bufsize = (int64_t)atoi(argv[2]) * 1024 * 1024;
        index = fopen(argv[3], "r+b");
    } else {
        /* Usage: ./sortidx abc.idx */
        if (argc != 2) {
            printUsage("Wrong number of arguments.");
            return 1;
        }
        index = fopen(argv[1], "r+b");
    }

    if (index == NULL) {
        printUsage("Could not open the index file.");
        return 1;
    }

    if(bufsize <= 0)
    {
        printUsage("Invalid buffer size given with -r");
        fclose(index);
        return 1;
    }

    /* Adjust bufsize to a multiple of the size of an IndexEntry */
    bufcount = bufsize / sizeof(struct IndexEntry);
    bufsize = bufcount * sizeof(struct IndexEntry);

    sortBuffer = malloc(bufsize);

    if(sortBuffer == NULL)
    {
        printUsage("Could not allocate enough memory. Try passing a lower -r.");
        fclose(index);
        return 1;
    }

    /* The quicksort algorithm is randomized, using rand(). */
    srand((unsigned int) time(NULL));

    if(sortFile(index, sortBuffer, bufcount) == 0)
    {
        printf("Index sort complete.\n");
        free(sortBuffer);
        fclose(index);
        return 0;
    }
    else
    {
        printf("Not a valid index file.\n");
        free(sortBuffer);
        fclose(index);
        return 1;
    }
}

void printUsage(const char *msg)
{
    if (msg) {
        printf("ERROR: %s\n", msg);
    }
    printf(
        "Usage: sortidx [OPTIONS] <INDEX>\n\n"
        "Options:\n"
        "-r n      'n' is the sort buffer size (memory) in megabytes\n"
    );
}

/*
 * Sort an index file.
 *
 * file:        Index file.
 * sortBuffer:  Memory buffer used to speed up sorting.
 * bufcount:    Size of buffer (number of elements).
 * returns:     0 on success, 1 if the index file is not valid.
 */
int sortFile(FILE *file, struct IndexEntry *sortBuffer, int64_t bufcount)
{
    fseek(file, 0L, SEEK_END);
    int64_t size = ftell(file);
    if(size % INDEX_ENTRY_WIDTH != 0) {
        return 1;
    }
    int64_t numEntries = size / INDEX_ENTRY_WIDTH;
    quickSortFile(file, 0, numEntries - 1, sortBuffer, bufcount);
    return 0;
}


/*
 * Quicksort an index file with file operations, moving to memory when possible.
 *
 * file:        Index file.
 * lowerIdx:    Lower index of range to sort.
 * upperIdx:    Upper index of range to sort (inclusive).
 * sortBuffer:  Memory buffer. When the partition will fit in this buffer, we
 *              switch  to a completely in-memory sort.
 * bufcount:    Size of buffer (number of elements).
 */
void quickSortFile(FILE* file, int64_t lowerIdx, int64_t upperIdx, struct IndexEntry *sortBuffer, int64_t bufcount)
{
    int64_t size = upperIdx - lowerIdx + 1;
    /* Base case: A size-0 or size-1 list is already sorted. */
    if(size >= 2)
    {
        if(size <= bufcount)
        {
            loadFileToBuffer(file, sortBuffer, lowerIdx, upperIdx, bufcount);
            quickSortMemory(sortBuffer, 0, size-1);
            writeBufferToFile(file, sortBuffer, lowerIdx, size);
        }
        else
        {
            int64_t newPivot = partitionFile(file, lowerIdx, upperIdx);

            // Sort the smallest pivot first, to keep the stack depth low.
            if ((newPivot - 1) - lowerIdx > upperIdx - (newPivot + 1)) {
                quickSortFile(file, newPivot + 1, upperIdx, sortBuffer, bufcount);
                quickSortFile(file, lowerIdx, newPivot - 1, sortBuffer, bufcount);
            } else {
                quickSortFile(file, lowerIdx, newPivot - 1, sortBuffer, bufcount);
                quickSortFile(file, newPivot + 1, upperIdx, sortBuffer, bufcount);
            }
        }
    }
}

/*
 * QuickSort partition step (in-file).
 *
 * file:        Index file.
 * lowerIdx:    Lower index of range to partition.
 * upperIdx:    Upper index of range to partition (inclusive).
 * returns:     Pivot index.
 */
int64_t partitionFile(FILE* file, int64_t lowerIdx, int64_t upperIdx)
{
    /*
     * I think this is near-optimal for an already-sorted list, especially for
     * randomly distributed hash values. However, there's the problem of sorting
     * an all-identical list (which can happen for NTLM hashes), and that's
     * dealt with by randomization below.
     */
    int64_t pivotIdx = lowerIdx + (upperIdx-lowerIdx)/2;

    /* Read the pivot value. */
    struct IndexEntry pivot;
    freadIndexEntryAt(file, pivotIdx, &pivot);

    /* Move the pivot to the end (get it out of the way). */
    struct IndexEntry tmp;
    freadIndexEntryAt(file, upperIdx, &tmp);
    fwriteIndexEntryAt(file, upperIdx, &pivot);
    fwriteIndexEntryAt(file, pivotIdx, &tmp);

    struct IndexEntry tmp2;

    int64_t storeIndex = lowerIdx;
    int64_t i;
    for(i = lowerIdx; i < upperIdx; i++)
    {
         freadIndexEntryAt(file, i, &tmp);
         int cmp = hashcmp(tmp.hash, pivot.hash);
         if(cmp < 0 || (cmp == 0 && (rand() & 2) == 0))
         {
            /* Swap i-th and storeIndex */
            freadIndexEntryAt(file, storeIndex, &tmp2);
            fwriteIndexEntryAt(file, storeIndex, &tmp);
            fwriteIndexEntryAt(file, i, &tmp2);
            storeIndex++;
         }
    }

    /* Put the pivot in its proper place. */
    freadIndexEntryAt(file, storeIndex, &tmp2);
    fwriteIndexEntryAt(file, storeIndex, &pivot);
    fwriteIndexEntryAt(file, upperIdx, &tmp2);

    return storeIndex;
}

/*
 * Quicksort a (portion of an) index file in-memory.
 *
 * sortBuffer:      IndexEntries to sort.
 * lowerIdx:        Lower index of the range to sort.
 * upperIdx:        Upper index of the range to sort (inclusive).
 */
void quickSortMemory(struct IndexEntry *sortBuffer, int64_t lowerIdx, int64_t upperIdx)
{
    int64_t size = upperIdx - lowerIdx + 1;
    /* Base case: A size-0 or size-1 list is already sorted. */
    if(size >= 2)
    {
        if(size <= INSERTIONSORT_THRESHOLD)
        {
            insertionSortMemory(sortBuffer, lowerIdx, upperIdx);
        }
        else
        {
            int64_t newPivot = partitionMemory(sortBuffer, lowerIdx, upperIdx);
            // Sort the smallest pivot first, to keep the stack depth low.
            if ((newPivot - 1) - lowerIdx > upperIdx - (newPivot + 1)) {
                quickSortMemory(sortBuffer, newPivot + 1, upperIdx);
                quickSortMemory(sortBuffer, lowerIdx, newPivot - 1);
            } else {
                quickSortMemory(sortBuffer, lowerIdx, newPivot - 1);
                quickSortMemory(sortBuffer, newPivot + 1, upperIdx);
            }
        }
    }
}

/* 
 * QuickSort partition step (in-memory).
 *
 * sortBuffer:  Index entries to sort.
 * lowerIdx:    Lower index of range to partition.
 * upperIdx:    Upper index of range to partition (inclusive).
 * returns:     Pivot index.
 */
int64_t partitionMemory(struct IndexEntry *sortBuffer, int64_t lowerIdx, int64_t upperIdx)
{
    int64_t pivotIndex = lowerIdx + (upperIdx-lowerIdx)/2;

    /* Get the pivot value. */
    struct IndexEntry pivotValue = sortBuffer[pivotIndex];

    /* Move the pivot to the end (give us room to work). */
    sortBuffer[pivotIndex] = sortBuffer[upperIdx];
    sortBuffer[upperIdx] = pivotValue;

    int64_t storeIndex = lowerIdx;
    int64_t i;
    struct IndexEntry tmp;
    for(i = lowerIdx; i < upperIdx; i++)
    {
        int cmp = hashcmp(sortBuffer[i].hash, pivotValue.hash);
        if(cmp < 0 || (cmp == 0 && (rand() & 2) == 0))
        {
            tmp = sortBuffer[i];
            sortBuffer[i] = sortBuffer[storeIndex];
            sortBuffer[storeIndex] = tmp;
            storeIndex++;
        }
    }

    /* Put the pivot in its proper place. */
    tmp = sortBuffer[storeIndex];
    sortBuffer[storeIndex] = pivotValue;
    sortBuffer[upperIdx] = tmp;
    return storeIndex;
}

/*
 * Insertion Sort (in-memory)
 *
 * sortBuffer:  Index entries to sort.
 * lowerIdx:    Lower index range to sort.
 * upperIdx:    Upper index range to sort (inclusive).
 */
void insertionSortMemory(struct IndexEntry *sortBuffer, int64_t lowerIdx, int64_t upperIdx)
{
    int64_t size = upperIdx - lowerIdx + 1;
    struct IndexEntry key;
    int64_t j;
    int64_t i;
    for(j = 0; j < size; j++)
    {
        key = sortBuffer[j + lowerIdx];
        i = j - 1;
        while( i >= 0 && hashcmp(sortBuffer[i + lowerIdx].hash, key.hash) > 0)
        {
            sortBuffer[i + 1 + lowerIdx] = sortBuffer[i + lowerIdx];
            i--;
        }
        sortBuffer[i + 1 + lowerIdx] = key;
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
        if(hashA[i] > hashB[i])
            return 1;
        else if(hashA[i] < hashB[i])
            return -1;
    }

    return 0;
}

void loadFileToBuffer(FILE* file, struct IndexEntry* buffer, int64_t lowerIdx, int64_t upperIdx, int64_t bufsize)
{
    int64_t i, s;
    for(i = lowerIdx, s = 0; i <= upperIdx && s < bufsize; i++, s++)
    {
        freadIndexEntryAt(file, i, buffer + s);
    }
}

void writeBufferToFile(FILE* file, struct IndexEntry* buffer, int64_t lowerIdx, int64_t writeCount)
{
    int64_t i;
    for(i = 0; i < writeCount; i++)
    {
        fwriteIndexEntryAt(file, lowerIdx + i, buffer + i);
    }
}

void freadIndexEntryAt(FILE* file, int64_t index, struct IndexEntry* out)
{
    if (fseek(file, index * INDEX_ENTRY_WIDTH, SEEK_SET) != 0) {
        printf("ERROR: fseek() failed.\n");
        exit(1);
    }
    if (fread(out->hash, sizeof(unsigned char), INDEX_HASH_WIDTH, file) != INDEX_HASH_WIDTH) {
        printf("ERROR: fread() failed.\n");
        exit(1);
    }
    if (fread(out->position, sizeof(unsigned char), INDEX_POSITION_WIDTH, file) != INDEX_POSITION_WIDTH) {
        printf("ERROR: fread() failed.\n");
        exit(1);
    }
}

void fwriteIndexEntryAt(FILE* file, int64_t index, struct IndexEntry* in)
{
    if (fseek(file, index * INDEX_ENTRY_WIDTH, SEEK_SET) != 0) {
        printf("ERROR: fseek() failed.\n");
        exit(1);
    }
    if (fwrite(in->hash, sizeof(unsigned char), INDEX_HASH_WIDTH, file) != INDEX_HASH_WIDTH) {
        printf("ERROR: fwrite() failed.\n");
        exit(1);
    }
    if (fwrite(in->position, sizeof(unsigned char), INDEX_POSITION_WIDTH, file) != INDEX_POSITION_WIDTH) {
        printf("ERROR: fwrite() failed.\n");
        exit(1);
    }
}
