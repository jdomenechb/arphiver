# Arphiver

> :warning: This project is not production-ready or mature enough to be used yet.

**Arphiver** is a PHP application used for translating MySQL rows into a JSON representation. The application detects foreign keys
used in the tables, and recursively navigates through the foreign tables to fetch the data the registers are related to, in order
to include it in the final representation.

The repository also features a little viewer utility that, given a JSON file and an HTML template, it will combine both 
in order to preview the data in HTML. 

Its primary use cases are:
- Exporting MySQL rows to databases that use a JSON-like representation (like MongoDB).
- Archiving MySQL rows that are relatated to other entites in a JSON file.
