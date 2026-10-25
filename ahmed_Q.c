#include <stdio.h>
#include <string.h>
#include <ctype.h>

int main(){

char password[20];

int longth =0;
int upaar =0;
int lowar =0;
int digit =0;
int special =0;

printf("*** Passeword check *** \n");
printf("***Enter the passeword  *** \n");

scanf_s("%19s", password, (unsigned int )sizeof(password));

if (strlen(password) > 8){

    longth= 1;
}
for (int i =0; password[i] != '\0'; i++){
    if (isupper (password[i])){
        upaar =1;
    }
    else if (islower(password[i])){
        lowar =1;
    }
    else if(isdigit(password[i])) {
        digit= 1;
    }
    else if(isspace(password[i])) {
        special=1;
    }
    printf("\n- - - Test result - - -\n");
    printf("More than 8 letters: %s\n",longth ?"Valid condition":"Invalid condition");
    printf("Capital letters: %s\n",upaar ?"Valid condition":"Invalid condition");
    printf("Small letters: %s\n",lowar ?"Valid condition":"Invalid condition");
    printf("Numbers: %s\n",digit ?"Valid condition":"Invalid condition");
    printf("Symbols: %s\n",special ?"Valid condition":"Invalid condition");

    printf("\n- - - Final result - - -\n");
    if (longth && upaar && lowar && digit && special){
        printf("The password is strong and good \n");
        
    }else {
        printf("The password dose not meet the requirements \n");

    }
    return 0;
 }
}