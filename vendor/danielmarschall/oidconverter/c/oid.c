/*#################################################################################
###                                                                             ###
### Object ID converter. Matthias Gaertner, 06/1999                             ###
### Converted to plain 'C' 07/2001                                              ###
###                                                                             ###
### Enhanced version by Daniel Marschall, ViaThinkSoft 06-07/2011, 2022         ###
### based on (or rather synchronized to) upstream version 1.3                   ###
### -- NEW in +viathinksoft2: 2.48 can also be encoded!                         ###
### -- NEW in +viathinksoft2: UUIDs (128-bit) are now supported!                ###
###                           (requires GMPLib)                                 ###
### -- NEW in +viathinksoft3: Length can now have more than 1 byte              ###
### -- NEW in +viathinksoft4: No command line limitation anymore.               ###
### -- NEW in +viathinksoft5: Now also relative OIDs supported                  ###
### -- NEW in +viathinksoft6: 0x80 paddings are now disallowed                  ###
### -- NEW in +viathinksoft8: Removed Application/Context/Private "OID"s        ###
### -- NEW in +viathinksoft9: Also allow decoding C-notation with "-x"          ###
### -- AS WELL AS SEVERAL BUG FIXES                                             ###
###                                                                             ###
### To compile with gcc simply use:                                             ###
###   gcc -O2 -o oid oid.c -lgmp -lm                                            ###
###                                                                             ###
### To compile using lcc-win32, use:                                            ###
###   lcc oid.c & lcclnk oid.obj                                                ###
###                                                                             ###
### To compile using cl, use:                                                   ###
###   cl -DWIN32 -O1 oid.c (+ include gmp library)                              ###
###                                                                             ###
### Freeware - do with it whatever you want.                                    ###
### Use at your own risk. No warranty of any kind.                              ###
###                                                                             ###
#################################################################################*/
/* $Version: 1.3+viathinksoft12$ */

// FUTURE
// - Alles in Funktionen kapseln. Als Parameter: Array of integer (= dot notation) oder Array of byte (= hex notation)

// MINOR THINGS
// - All stderr: Output new line at stdOut and close stdOut
// - Make as much GMP as possible (e.g. nBinary counter, nBinaryWork etc)
// - �berlegen, wie man die return-codes (errorcodes) besser verteilt/definiert
// - irgendwie in funktionen kapseln (z.b. class-tag-parser usw)
// - "TODO"s beachten (unklare dinge)

// MINOR PROBLEMS IN CLI-INTERPRETATION:
// - A wrong error message is shown when trying to encode "-0.0" or "x"
// - 2.9a9 is not recognized as error
// - "./oid R 2.999" is not interpretet correctly
// - "./oid .2.999" will be interpreted as "0.2.999"

// NICE TO HAVE:
// - makefile, manpage, linuxpackage
// - better make functions instead of putting everything in main() with fprintf...

// NICE TO HAVE (INFINITY-IDEA - NOT IMPORTANT):
// - Is it possible to detect integer overflows and therefore output errors?


// -------------------------------------------------------

// Allows OIDs which are bigger than "long"
// Compile with "gcc oid.c -lgmp -lm"
#define is_gmp

#include <stdio.h>
#include <string.h>
#include <stdlib.h>

#ifdef is_gmp
#include <gmp.h>
#endif

#include <stdbool.h>

#ifndef __STRNICMP_LOCAL
#ifdef WIN32
#define __STRNICMP_LOCAL strnicmp
#else
#define __STRNICMP_LOCAL strncasecmp
#endif
#endif

// char abCommandLine[4096];
const unsigned int CLI_INITIAL_SIZE = 1024;
const unsigned int CLI_EXPANSION_SIZE = 1024;
unsigned int cli_size;
char * abCommandLine;

// unsigned char abBinary[128];
const unsigned int ABB_INITIAL_SIZE = 1024;
const unsigned int ABB_EXPANSION_SIZE = 1024;
unsigned int abb_size;
unsigned char * abBinary;

unsigned int nBinary = 0;

const int MODE_DOT_TO_HEX = 0;
const int MODE_HEX_TO_DOT = 1;

#ifdef is_gmp
static void MakeBase128(mpz_t l, int first) {
	if (mpz_cmp_si(l, 127) > 0) {
		mpz_t l2;
		mpz_init(l2);
		mpz_div_ui(l2, l, 128);
		MakeBase128(l2, 0);
	}
	mpz_mod_ui(l, l, 128);

	if (nBinary+1 >= abb_size) {
		abb_size += ABB_EXPANSION_SIZE;
		abBinary = (unsigned char*) realloc(abBinary, abb_size);
		if (abBinary == NULL) {
			fprintf(stderr, "Memory reallocation failure!\n");
			exit(EXIT_FAILURE);
		}
	}

	if (first) {
		abBinary[nBinary++] = mpz_get_ui(l);
	} else {
		abBinary[nBinary++] = 0x80 | mpz_get_ui(l);
	}
}
#else
static void MakeBase128(unsigned long l, int first) {
	if (l > 127) {
		MakeBase128(l / 128, 0);
	}
	l %= 128;

	if (nBinary+1 >= abb_size) {
		abb_size += ABB_EXPANSION_SIZE;
		abBinary = (unsigned char*) realloc(abBinary, abb_size);
		if (abBinary == NULL) {
			fprintf(stderr, "Memory reallocation failure!\n");
			exit(EXIT_FAILURE);
		}
	}

	if (first) {
		abBinary[nBinary++] = (unsigned char)l;
	} else {
		abBinary[nBinary++] = 0x80 | (unsigned char)l;
	}
}
#endif

int main(int argc, char **argv) {
	cli_size = CLI_INITIAL_SIZE;
	abCommandLine  = (char*) malloc(cli_size * sizeof(char*));
	if (abCommandLine == NULL) {
		fprintf(stderr, "Memory allocation failure!\n");
		return EXIT_FAILURE;
	}

	abb_size = ABB_INITIAL_SIZE;
	abBinary  = (unsigned char*) malloc(abb_size * sizeof(unsigned char*));
	if (abBinary == NULL) {
		fprintf(stderr, "Memory allocation failure!\n");
		return EXIT_FAILURE;
	}


	char *fOutName = NULL;
	char *fInName = NULL;
	FILE *fOut = NULL;

	int n = 1;
	int nMode = MODE_DOT_TO_HEX;
	int nCHex = 0;
	int nAfterOption = 0;
	bool isRelative = false;

	if (argc == 1) {
		fprintf(stderr,
		"OID encoder/decoder 1.3+viathinksoft12 - Matthias Gaertner 1999/2001, Daniel Marschall 2011/2012 - Freeware\n"
		#ifdef is_gmp
		"GMP Edition (unlimited arc sizes)\n"
		#else
		"%d-bit Edition (arc sizes are limited!)\n"
		#endif
		"\nUsage:\n"
		" OID [-c|-C] [-r] [-o<outfile>] {-i<infile>|2.999.1}\n"
		"   converts dotted form to ASCII HEX DER output.\n"
		"   -c: Output as C-syntax (array).\n"
		"   -C: Output as C-syntax (string).\n"
		"   -r: Handle the OID as relative and not absolute.\n"
		" OID -x [-o<outfile>] {-i<infile>|hex-digits}\n"
		"   decodes ASCII HEX DER and gives dotted form.\n" , sizeof(unsigned long) * 8);
		return 1;
	}

	while (n < argc) {
		if (!nAfterOption && argv[n][0] == '-') {
			if (argv[n][1] == 'x') {
				nMode = MODE_HEX_TO_DOT;
				if (argv[n][2] != '\0') {
					argv[n--] += 2;
					nAfterOption = 1;
				}
			} else if (argv[n][1] == 'c') {
				nMode = MODE_DOT_TO_HEX;
				nCHex = 1;

				if (argv[n][2] != '\0') {
					argv[n--] += 2;
					nAfterOption = 1;
				}
			} else if (argv[n][1] == 'C') {
				nMode = MODE_DOT_TO_HEX;
				nCHex = 2;

				if (argv[n][2] != '\0') {
					argv[n--] += 2;
					nAfterOption = 1;
				}
			} else if (argv[n][1] == 'r') {
				nMode = MODE_DOT_TO_HEX;
				isRelative = true;

				if (argv[n][2] != '\0') {
					argv[n--] += 2;
					nAfterOption = 1;
				}
			} else if (argv[n][1] == 'o') {
				if (argv[n][2] != '\0') {
					fOutName = &argv[n][2];
				} else if (n < argc-1) {
					fOutName = argv[++n];
				} else {
					fprintf(stderr, "Incomplete command line.\n");
					return EXIT_FAILURE;
				}
			} else if (argv[n][1] == 'i') {
				if (argv[n][2] != '\0') {
					fInName = &argv[n][2];
				} else if (n < argc-1) {
					fInName = argv[++n];
				} else {
					fprintf(stderr, "Incomplete command line.\n");
					return EXIT_FAILURE;
				}
			}
		} else {
			if (fInName != NULL) { // TODO: (Unklar) Was bewirkt das? Auch f�r fOutName notwendig?
				break;
			}

			nAfterOption = 1;
			if (strlen(argv[n]) + strlen(abCommandLine) >= sizeof(abCommandLine)-2) { // TODO: warum -2 ?
				// fprintf(stderr, "Command line too long.\n");
				// return 2;

				cli_size += CLI_EXPANSION_SIZE + strlen(argv[n]) + 1; // 1 = "."
				abCommandLine = (char*) realloc(abCommandLine, cli_size);
				if (abCommandLine == NULL) {
					fprintf(stderr, "Memory reallocation failure!\n");
					return EXIT_FAILURE;
				}
			}
			strcat(abCommandLine, argv[n]); // (f�gt ein \0 automatisch an)
			if (n != argc - 1 && nMode != MODE_HEX_TO_DOT) {
				strcat(abCommandLine, ".");
			}
		}
		n++;
	}

	if (fInName != NULL && nMode == MODE_HEX_TO_DOT) {
		FILE *fIn = fopen(fInName, "rb");
		size_t nRead = 0;
		if (fIn == NULL) {
			fprintf(stderr, "Unable to open input file %s.\n", fInName);
			return 11;
		}
		nRead = fread(abCommandLine, 1, sizeof(abCommandLine), fIn);
		abCommandLine[nRead] = '\0';
		fclose(fIn);
	} else if (fInName != NULL && nMode == MODE_DOT_TO_HEX) {
		FILE *fIn = fopen(fInName, "rt");
		if (fIn == NULL) {
			fprintf(stderr, "Unable to open input file %s.\n", fInName);
			return 11;
		}
		fgets(abCommandLine, sizeof(abCommandLine), fIn);
		fclose(fIn);
	}

	while (nMode == MODE_HEX_TO_DOT) { /* better if */
		/* hex->dotted */
		/*printf("Hex-In: %s\n", abCommandLine);*/

		char *p = abCommandLine;
		char *q = p;

		unsigned char *pb = NULL;
		unsigned int nn = 0;
		#ifdef is_gmp
		mpz_t ll;
		mpz_init(ll);
		#else
		unsigned long ll = 0;
		#endif
		bool fOK = false;
		int fSub = 0; // Subtract value from next number output. Used when encoding {2 48} and up

		while (*p) {
			if (*p != '.' && *p != ':' && *p != '\r' && *p != '\n' && *p != '\x20' && *p != '\t') {
				*q++ = *p;
			}
			p++;
		}
		*q = '\0';

		if (strlen(abCommandLine) % 2 != 0) {
			fprintf(stderr, "Encoded OID must have even number of hex digits!\n");
			return 2;
		}

		if (strlen(abCommandLine) < 3) {
			fprintf(stderr, "Encoded OID must have at least three bytes!\n");
			return 2;
		}

		nBinary = 0;
		p = abCommandLine;

		while (*p) {
			unsigned char b;

			// This allows also C-hexstring-notation
			if ((p[0] == '\\') && (p[1] == 'x')) {
				p += 2;
				continue;
			}

			// This allows also C-array-notation
			if ((p[0] == '0') && (p[1] == 'x')) {
				p += 2;
				continue;
			}
			if ((p[0] == ',') || (p[0] == '{') || (p[0] == '}') || (p[0] == ' ')) {
				p++;
				continue;
			}

			// Interpret upper nibble
			if (p[0] >= 'A' && p[0] <= 'F') {
				b = (p[0] - 'A' + 10) * 16;
			} else if (p[0] >= 'a' && p[0] <= 'f') {
				b = (p[0] - 'a' + 10) * 16;
			} else if (p[0] >= '0' && p[0] <= '9') {
				b = (p[0] - '0') * 16;
			} else {
				fprintf(stderr, "Must have hex digits only!\n");
				return 2;
			}

			// Interpret lower nibble
			if (p[1] >= 'A' && p[1] <= 'F') {
				b += (p[1] - 'A' + 10);
			} else if (p[1] >= 'a' && p[1] <= 'f') {
				b += (p[1] - 'a' + 10);
			} else if (p[1] >= '0' && p[1] <= '9') {
				b += (p[1] - '0');
			} else {
				fprintf(stderr, "Must have hex digits only!\n");
				return 2;
			}

			if (nBinary+1 >= abb_size) {
				abb_size += ABB_EXPANSION_SIZE;
				abBinary = (unsigned char*) realloc(abBinary, abb_size);
				if (abBinary == NULL) {
					fprintf(stderr, "Memory reallocation failure!\n");
					return EXIT_FAILURE;
				}
			}

			abBinary[nBinary++] = b;
			p += 2;
		}

		/*printf("Hex-In: %s\n", abCommandLine);*/

		if (fOutName != NULL) {
			fOut = fopen(fOutName, "wt");
			if (fOut == NULL) {
				fprintf(stderr, "Unable to open output file %s\n", fOutName);
				return 33;
			}
		} else {
			fOut = stdout;
		}

		pb = abBinary;
		nn = 0;
		#ifdef is_gmp
		mpz_init(ll);
		#else
		ll = 0;
		#endif
		fOK = false;
		fSub = 0;

		// 0 = Universal Class Identifier Tag (can be more than 1 byte, but not in our case)
		// 1 = Length part (may have more than 1 byte!)
		// 2 = First two arc encoding
		// 3 = Encoding of arc three and higher
		unsigned char part = 0;

		unsigned char lengthbyte_count = 0;
		unsigned char lengthbyte_pos = 0;
		bool lengthfinished = false;

		bool arcBeginning = true;
		bool firstWrittenArc = true;

		while (nn < nBinary) {
			if (part == 0) { // Class Tag

				// Leading octet
				// Bit 7 / Bit 6 = Universal (00), Application (01), Context (10), Private(11)
				// Bit 5 = Primitive (0), Constructed (1)
				// Bit 4..0 = 00000 .. 11110 => Tag 0..30, 11111 for Tag > 30 (following bytes with the highest bit as "more" bit)
				// --> We don't need to respect 11111 (class-tag encodes in more than 1 octet)
				//     as we terminate when the tag is not of type OID or RELATEIVE-OID
				// See page 396 of "ASN.1 - Communication between Heterogeneous Systems" by Olivier Dubuisson.

				// Class: 8. - 7. bit
				// 0 (00) = Universal
				// 1 (01) = Application
				// 2 (10) = Context
				// 3 (11) = Private
				unsigned char cl = ((*pb & 0xC0) >> 6) & 0x03;
				if (cl != 0) {
					fprintf(stderr, "\nError at type: The OID tags are only defined as UNIVERSAL class tags.\n");
					fprintf(fOut, "\n");
					return 6;
				}

				// Primitive/Constructed: 6. bit
				// 0 = Primitive
				// 1 = Constructed
				unsigned char pc = *pb & 0x20;
				if (pc != 0) {
					fprintf(stderr, "\nError at type: OIDs must be primitive, not constructed.\n");
					fprintf(fOut, "\n");
					return 6;
				}

				// Tag number: 5. - 1. bit
				unsigned char tag = *pb & 0x1F;
				if (tag == 0x0D) {
					isRelative = true;
				} else if (tag == 0x06) {
					isRelative = false;
				} else {
					fprintf(stderr, "\nError at type: The tag number is neither an absolute OID (0x06) nor a relative OID (0x0D).\n");
					fprintf(fOut, "\n");
					return 6;
				}

				// Output
				if (isRelative) {
					fprintf(fOut, "RELATIVE");
				} else {
					fprintf(fOut, "ABSOLUTE");
				}

				fprintf(fOut, " OID");
				part++;
			} else if (part == 1) { // Length

				// Find out the length and save it into ll

				// [length] is encoded as follows:
				//  0x00 .. 0x7F = The actual length is in this byte, followed by [data].
				//  0x80 + n     = The length of [data] is spread over the following 'n' bytes. (0 < n < 0x7F)
				//  0x80         = "indefinite length" (only constructed form) -- Invalid
				//  0xFF         = Reserved for further implementations -- Invalid
				//  See page 396 of "ASN.1 - Communication between Heterogeneous Systems" by Olivier Dubuisson.

				if (nn == 1) { // The first length byte
					lengthbyte_pos = 0;
					if ((*pb & 0x80) != 0) {
						// 0x80 + n => The length is spread over the following 'n' bytes
						lengthfinished = false;
						lengthbyte_count = *pb & 0x7F;
						if (lengthbyte_count == 0x00) {
							fprintf(stderr, "\nLength value 0x80 is invalid (\"indefinite length\") for primitive types.\n");
							fprintf(fOut, "\n");
							return 7;
						} else if (lengthbyte_count == 0x7F) {
							fprintf(stderr, "\nLength value 0xFF is reserved for further extensions.\n");
							fprintf(fOut, "\n");
							return 7;
						}
						fOK = false;
					} else {
						// 0x01 .. 0x7F => The actual length

						if (*pb == 0x00) {
							fprintf(stderr, "\nLength value 0x00 is invalid for an OID.\n");
							fprintf(fOut, "\n");
							return 7;
						}

						#ifdef is_gmp
						mpz_set_ui(ll, *pb);
						#else
						ll = *pb;
						#endif
						lengthfinished = true;
						lengthbyte_count = 0;
						fOK = true;
					}
				} else {
					if (lengthbyte_count > lengthbyte_pos) {
						#ifdef is_gmp
						mpz_mul_ui(ll, ll, 0x100);
						mpz_add_ui(ll, ll, *pb);
						#else
						ll *= 0x100;
						ll += *pb;
						#endif
						lengthbyte_pos++;
					}

					if (lengthbyte_count == lengthbyte_pos) {
						lengthfinished = true;
						fOK = true;
					}
				}

				if (lengthfinished) { // The length is now in ll
					#ifdef is_gmp
					if (mpz_cmp_ui(ll,  nBinary - 2 - lengthbyte_count) != 0) {
						fprintf(fOut, "\n");
						if (fOut != stdout) {
							fclose(fOut);
						}
						fprintf(stderr, "\nInvalid length (%d entered, but %s expected)\n", nBinary - 2, mpz_get_str(NULL, 10, ll));
						return 3;
					}
					mpz_set_ui(ll, 0); // reset for later usage
					#else
					if (ll != nBinary - 2 - lengthbyte_count) {
						fprintf(fOut, "\n");
						if (fOut != stdout) {
							fclose(fOut);
						}
						fprintf(stderr, "\nInvalid length (%d entered, but %d expected)\n", nBinary - 2, ll);
						return 3;
					}
					ll = 0; // reset for later usage
					#endif
					fOK = true;
					part++;
					if (isRelative) part++; // Goto step 3!
				}
			} else if (part == 2) { // First two arcs
				int first = *pb / 40;
				int second = *pb % 40;
				if (first > 2) {
					first = 2;
					fprintf(fOut, " %d", first);
					firstWrittenArc = false;
					arcBeginning = true;

					if ((*pb & 0x80) != 0) {
						// 2.48 and up => 2+ octets
						// Output in "part 3"

						if (arcBeginning && (*pb == 0x80)) {
							fprintf(fOut, "\n");
							if (fOut != stdout) {
								fclose(fOut);
							}
							fprintf(stderr, "\nEncoding error. Illegal 0x80 paddings. (See Rec. ITU-T X.690, clause 8.19.2)\n");
							return 4;
						} else {
							arcBeginning = false;
						}

						#ifdef is_gmp
						mpz_add_ui(ll, ll, (*pb & 0x7F));
						#else
						ll += (*pb & 0x7F);
						#endif
						fSub = 80;
						fOK = false;
					} else {
						// 2.0 till 2.47 => 1 octet
						second = *pb - 80;
						fprintf(fOut, ".%d", second);
						arcBeginning = true;
						fOK = true;
						#ifdef is_gmp
						mpz_set_ui(ll, 0);
						#else
						ll = 0;
						#endif
					}
				} else {
					// 0.0 till 0.37 => 1 octet
					// 1.0 till 1.37 => 1 octet
					fprintf(fOut, " %d.%d", first, second);
					firstWrittenArc = false;
					arcBeginning = true;
					fOK = true;
					#ifdef is_gmp
					mpz_set_ui(ll, 0);
					#else
					ll = 0;
					#endif
				}
				part++;
			} else if (part == 3) { // Arc three and higher
				if ((*pb & 0x80) != 0) {
					if (arcBeginning && (*pb == 0x80)) {
						fprintf(fOut, "\n");
						if (fOut != stdout) {
							fclose(fOut);
						}
						fprintf(stderr, "\nEncoding error. Illegal 0x80 paddings. (See Rec. ITU-T X.690, clause 8.19.2)\n");
						return 4;
					} else {
						arcBeginning = false;
					}

					#ifdef is_gmp
					mpz_mul_ui(ll, ll, 0x80);
					mpz_add_ui(ll, ll, (*pb & 0x7F));
					#else
					ll *= 0x80;
					ll += (*pb & 0x7F);
					#endif
					fOK = false;
				} else {
					fOK = true;
					#ifdef is_gmp
					mpz_mul_ui(ll, ll, 0x80);
					mpz_add_ui(ll, ll, *pb);
					mpz_sub_ui(ll, ll, fSub);
					if (firstWrittenArc) {
						fprintf(fOut, " %s", mpz_get_str(NULL, 10, ll));
						firstWrittenArc = false;
					} else {
						fprintf(fOut, ".%s", mpz_get_str(NULL, 10, ll));
					}
					// Happens only if 0x80 paddings are allowed
					// fOK = mpz_cmp_ui(ll, 0) >= 0;
					mpz_set_ui(ll, 0);
					#else
					ll *= 0x80;
					ll += *pb;
					ll -= fSub;
					if (firstWrittenArc) {
						fprintf(fOut, " %lu", ll);
						firstWrittenArc = false;
					} else {
						fprintf(fOut, ".%lu", ll);
					}
					// Happens only if 0x80 paddings are allowed
					// fOK = ll >= 0;
					ll = 0;
					#endif
					fSub = 0;
					arcBeginning = true;
				}
			}

			pb++;
			nn++;
		}

		if (!fOK) {
			fprintf(fOut, "\n");
			if (fOut != stdout) {
				fclose(fOut);
			}
			fprintf(stderr, "\nEncoding error. The OID is not constructed properly.\n");
			return 4;
		} else {
			fprintf(fOut, "\n");
		}

		if (fOut != stdout) {
			fclose(fOut);
		}
		break;
	};

	while (nMode == MODE_DOT_TO_HEX) { /* better if */
		/* dotted->hex */
		/* printf("OID %s\n", abCommandLine); */

		char *p = abCommandLine;
		unsigned char cl = 0x00;
		char *q = NULL;
		int nPieces = 1;
		int n = 0;
		unsigned char b = 0;
		unsigned int nn = 0;
		#ifdef is_gmp
		mpz_t l;
		#else
		unsigned long l = 0;
		#endif
		bool isjoint = false;

		// Alternative call: ./oid RELATIVE.2.999
		if (__STRNICMP_LOCAL(p, "ABSOLUTE.", 9) == 0) {
			isRelative = false;
			p+=9;
		} else if (__STRNICMP_LOCAL(p, "RELATIVE.", 9) == 0) {
			isRelative = true;
			p+=9;
		} else {
			// use the CLI option
			// isRelative = false;
		}

		cl = 0x00; // Class. Always UNIVERSAL (00)

		// Tag for Universal Class
		if (isRelative) {
			cl |= 0x0D;
		} else {
			cl |= 0x06;
		}

		/* if (__STRNICMP_LOCAL(p, "OID.", 4) == 0) {
			p+=4;
		} */

		q = p;
		nPieces = 1;
		while (*p) {
			if (*p == '.') {
				nPieces++;
			}
			p++;
		}

		n = 0;
		b = 0;
		p = q;
		while (n < nPieces) {
			q = p;
			while (*p) {
				if (*p == '.') {
					break;
				}
				p++;
			}

			#ifdef is_gmp
			mpz_init(l);
			#else
			l = 0;
			#endif
			if (*p == '.') {
				*p = 0;
				#ifdef is_gmp
				mpz_set_str(l, q, 10);
				#else
				l = (unsigned long) atoi(q);
				#endif
				q = p+1;
				p = q;
			} else {
				#ifdef is_gmp
				mpz_set_str(l, q, 10);
				#else
				l = (unsigned long) atoi(q);
				#endif
				q = p;
			}

			/* Digit is in l. */
			if ((!isRelative) && (n == 0)) {
				#ifdef is_gmp
				if (mpz_cmp_ui(l, 2) > 0) {
				#else
				if (l > 2) {
				#endif
					fprintf(stderr, "\nEncoding error. The top arc is limited to 0, 1 and 2.\n");
					return 5;
				}
				#ifdef is_gmp
				b += 40 * mpz_get_ui(l);
				isjoint = mpz_cmp_ui(l, 2) == 0;
				#else
				b = 40 * ((unsigned char)l);
				isjoint = l == 2;
				#endif
			} else if ((!isRelative) && (n == 1)) {
				#ifdef is_gmp
				if ((!isjoint) && (mpz_cmp_ui(l, 39) > 0)) {
				#else
				if ((l > 39) && (!isjoint)) {
				#endif
					fprintf(stderr, "\nEncoding error. The second arc is limited to 0..39 for root arcs 0 and 1.\n");
					return 5;
				}

				#ifdef is_gmp
				if (mpz_cmp_ui(l, 47) > 0) {
					mpz_add_ui(l, l, 80);
					MakeBase128(l, 1);
				} else {
					b += mpz_get_ui(l);
					if (nBinary+1 >= abb_size) {
						abb_size += ABB_EXPANSION_SIZE;
						abBinary = (unsigned char*) realloc(abBinary, abb_size);
						if (abBinary == NULL) {
							fprintf(stderr, "Memory reallocation failure!\n");
							return EXIT_FAILURE;
						}
					}
					abBinary[nBinary++] = b;
				}
				#else
				if (l > 47) {
					l += 80;
					MakeBase128(l, 1);
				} else {
					b += ((unsigned char) l);
					if (nBinary+1 >= abb_size) {
						abb_size += ABB_EXPANSION_SIZE;
						abBinary = (unsigned char*) realloc(abBinary, abb_size);
						if (abBinary == NULL) {
							fprintf(stderr, "Memory reallocation failure!\n");
							return EXIT_FAILURE;
						}
					}
					abBinary[nBinary++] = b;
				}
				#endif
			} else {
				MakeBase128(l, 1);
			}
			n++;
		}

		if ((!isRelative) && (n < 2)) {
			fprintf(stderr, "\nEncoding error. The minimum depth of an encodeable absolute OID is 2. (e.g. 2.999)\n");
			return 5;
		}

		if (fOutName != NULL) {
			fOut = fopen(fOutName, "wt");
			if (fOut == NULL) {
				fprintf(stderr, "Unable to open output file %s\n", fOutName);
				return 33;
			}
		} else {
			fOut = stdout;
		}

		// Write class-tag
		if (nCHex == 1) {
			fprintf(fOut, "{ 0x%02X, ", cl);
		} else if (nCHex == 2) {
			fprintf(fOut, "\"\\x%02X", cl);
		} else {
			fprintf(fOut, "%02X ", cl);
		}

		// Write length
		if (nBinary <= 0x7F) {
			if (nCHex == 1) {
				fprintf(fOut, "0x%02X, ", nBinary);
			} else if (nCHex == 2) {
				fprintf(fOut, "\\x%02X", nBinary);
			} else {
				fprintf(fOut, "%02X ", nBinary);
			}
		} else {
			unsigned int nBinaryWork;
			unsigned int lengthCount = 0;

			nBinaryWork = nBinary;
			do {
				lengthCount++;
				nBinaryWork /= 0x100;
			} while (nBinaryWork > 0);

			if (lengthCount >= 0x7F) {
				fprintf(stderr, "\nThe length cannot be encoded.\n");
				return 8;
			}

			if (nCHex == 1) {
				fprintf(fOut, "0x%02X, ", 0x80 + lengthCount);
			} else if (nCHex == 2) {
				fprintf(fOut, "\\x%02X", 0x80 + lengthCount);
			} else {
				fprintf(fOut, "%02X ", 0x80 + lengthCount);
			}

			nBinaryWork = nBinary;
			do {
				if (nCHex == 1) {
					fprintf(fOut, "0x%02X, ", nBinaryWork & 0xFF);
				} else if (nCHex == 2) {
					fprintf(fOut, "\\x%02X", nBinaryWork & 0xFF);
				} else {
					fprintf(fOut, "%02X ", nBinaryWork & 0xFF);
				}
				nBinaryWork /= 0x100;
			} while (nBinaryWork > 0);
		}

		nn = 0;
		while (nn < nBinary) {
			unsigned char b = abBinary[nn++];
			if (nn == nBinary) {
				if (nCHex == 1) {
					fprintf(fOut, "0x%02X }\n", b);
				} else if (nCHex == 2) {
					fprintf(fOut, "\\x%02X\"\n", b);
				} else {
					fprintf(fOut, "%02X\n", b);
				}
			} else {
				if (nCHex == 1) {
					fprintf(fOut, "0x%02X, ", b);
				} else if (nCHex == 2) {
					fprintf(fOut, "\\x%02X", b);
				} else {
					fprintf(fOut, "%02X ", b);
				}
			}
		}
		if (fOut != stdout) {
			fclose(fOut);
		}
		break;
	}

	free(abCommandLine);
	free(abBinary);

	return 0;
}
