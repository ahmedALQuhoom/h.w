#include <stdio.h>
#include <stdlib.h>
#include <string.h>

static int append_chunk(char **line, size_t *length, size_t *capacity,
const char *chunk)
{
size_t chunk_length = strlen(chunk);
size_t needed = *length + chunk_length + 1;

if (needed > *capacity) {
size_t new_capacity = (*capacity == 0) ? 512 : *capacity;

while (new_capacity < needed) {
if (new_capacity > (size_t)-1 / 2) {
return 0;
}
new_capacity *= 2;
}

char *new_line = realloc(*line, new_capacity);
if (new_line == NULL) {
return 0;
}

*line = new_line;
*capacity = new_capacity;
}

memcpy(*line + *length, chunk, chunk_length);
*length += chunk_length;
(*line)[*length] = '\0';

return 1;
}

static void check_line(const char *line, size_t length,
const char *keyword, unsigned long long line_number,
unsigned long long *alert_count)
{
if (strstr(line, keyword) != NULL) {
printf("Alert at line %llu: %s", line_number, line);

/* Add a newline if the line doesn't end with one */
if (length == 0 || line[length - 1] != '\n') {
putchar('\n');
}

(*alert_count)++;
}
}

int main(int argc, char *argv[])
{
if (argc != 3) {
fprintf(stderr, "Usage: %s <log_file> <keyword>\n", argv[0]);
return 1;
}

FILE *file = fopen(argv[1], "r");
if (file == NULL) {
perror("Error opening log file");
return 1;
}

char chunk[512];
char *line = NULL;
size_t line_length = 0;
size_t line_capacity = 0;
unsigned long long line_number = 0;
unsigned long long alert_count = 0;

while (fgets(chunk, sizeof chunk, file) != NULL) {
if (!append_chunk(&line, &line_length, &line_capacity, chunk)) {
fprintf(stderr, "Error: Failed to allocate enough memory for the line.\n");
free(line);
fclose(file);
return 1;
}

/* Each fgets call reads until newline or buffer is full */
if (strchr(chunk, '\n') != NULL) {
line_number++;
check_line(line, line_length, argv[2], line_number, &alert_count);
line_length = 0;
line[0] = '\0';
}
}

/* Handle the last line if it doesn't end with a newline character */
if (line_length > 0) {
line_number++;
check_line(line, line_length, argv[2], line_number, &alert_count);
}

if (ferror(file)) {
fprintf(stderr, "Error reading the log file.\n");
free(line);
fclose(file);
return 1;
}

printf("\nTotal Alerts: %llu\n", alert_count);

free(line);
fclose(file);
return 0;
}

/* 
cd log_analyzer
gcc -Wall -Wextra -O2 log_analyzer.c -o log_analyzer
log_analyzer.exe test.log FAILED
log_analyzer.exe test.log 404
 */