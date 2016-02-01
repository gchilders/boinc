#include <stdio.h>
#include <stdlib.h>
#include <sys/types.h>
#include <string.h>

int main(int argc, char **argv) {
  char  name[512], token[256], value[512], thisLine[1024];
  char  inname[512],fbname[512],ininame[512];
  long long  i, j; 
  int cont=1;
  FILE *infile, *outfb, *outini;

 if (argc != 2) {
                printf("\nusage: %s name\n\n", argv[1]);
                exit(-1);
        }
  sprintf(inname,"%s.poly",argv[1]);
  sprintf(fbname,"%s.fb",argv[1]);
  sprintf(ininame,"%s.ini",argv[1]);
   infile = fopen(inname, "r");
   if (infile == NULL) {
                printf("cannot open %s\n", inname);
                exit(-1);
   }
   outfb = fopen(fbname, "w");
   if (outfb == NULL) {
                printf("cannot open %s\n", fbname);
                exit(-1);
   }
   outini = fopen(ininame, "w");
   if (outini == NULL) {
                printf("cannot open %s\n", ininame);
                exit(-1);
   }


  while (cont) {
    thisLine[0] = 0;
    fgets(thisLine, 1023, infile);
    if ((sscanf(thisLine, "%255s %511s", token, value)==2) &&
                (thisLine[0] != '#')) {
	  token[sizeof(token)-1] = 0;
      if (strncmp(token, "n:", 2)==0) {
        fprintf(outini,"%s\n",value);
        fprintf(outfb,"N %s\n",value);
      } else if (strncmp(token, "m:", 2)==0) {
        fprintf(outfb,"R1 1\n");
        fprintf(outfb,"R0 -%s\n",value);
      } else if ((token[0]=='c') && (token[1] >= '0') && (token[1] <= '6')) {
        fprintf(outfb,"A%c %s\n",token[1], value);
      } else if ((token[0]=='Y') && (token[1] >= '0') && (token[1] <= '6')) {
        fprintf(outfb,"R%c %s\n",token[1], value);
      } else if (strncmp(token, "skew:", 5)==0) {
        fprintf(outfb,"SKEW %s\n",value);
      } else if (strncmp(token, "alim:", 5)==0) {
        fprintf(outfb,"FAMAX %s\n",value);
      } else if (strncmp(token, "rlim:", 5)==0) {
        fprintf(outfb,"FRMAX %s\n",value);
      } else if (strncmp(token, "lpba:", 5)==0) {
        i=atoll(value);
        j=1;
        while (i--) j=j<<1;
        fprintf(outfb,"SALPMAX %lld\n",j);
      } else if (strncmp(token, "lpbr:", 5)==0) {
        i=atoll(value);
        j=1;
        while (i--) j=j<<1;
        fprintf(outfb,"SRLPMAX %lld\n",j);
      } else if (strncmp(token, "END_POLY", 8)==0) {
        cont=0;
      }
    }
    if (feof(infile)) cont=0;
  }
fclose(infile);
fclose(outfb);
fclose(outini);
return 0;

}
