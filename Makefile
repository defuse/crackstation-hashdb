.PHONY: all clean

all: sortidx checksort

sortdx: sortidx.c
	gcc -Wall -O2 sortidx.c -o sortidx

checksort: checksort.c
	gcc -Wall -O2 checksort.c -o checksort

clean:
	rm -f sortidx checksort
