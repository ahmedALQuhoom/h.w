#include<stdio.h>


int main(int argc ,char *argv[]){
    unsigned char buffer[16];
    FILE*file;


    if(argc !=2){
        fprintf(stderr, "Usage: %s <filename>\n",argv[0]);
        return 1;


    }
    file= fopen(argv[1], "rb");
    if (file == NULL){
        perror("Error opening file");
        return 1;
    }
    size_t bytes_read ;
    while ((bytes_read = fread (buffer,1,sizeof buffer ,file ))>0){

        for (size_t i =0;i<bytes_read;i++){
            printf("%02X ",(unsigned int)buffer[i]);
        }
        for (size_t i= bytes_read; i<sizeof buffer;i++){
            printf("   ");
        }
        printf(" | ");
        for(size_t i=0; i<bytes_read; i++){
            if (buffer[i]>=32 && buffer[i] <=126){
                putchar(buffer[i]);
            }else{
                putchar('.');
            }
        }
        printf(" |\n");

        if (ferror(file)){
            fprintf(stderr,"Error reading file.\n");
            fclose(file);
            return 1;

        }
        fclose(file);
        return 0;

 }
    




}