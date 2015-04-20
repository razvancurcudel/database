# KoolKode Database Connections

[![Build Status](https://travis-ci.org/koolkode/database.svg?branch=master)](https://travis-ci.org/koolkode/database)

Supported DB platforms:
- Sqlite
- MySQL
- PostgreSQL

Provides a database API that has support for LIMIT / OFFSET queries, nested transactions, database object prefixes,
identifier quoting and unified retrieval of auto-increment values. Provides schema migrations and related commands
for supported DB platforms.

## Doctrine DBAL Integration

KoolKode Database ships with a DBAL connection that can be used instead of the PDO connection. API exceptions are
available when using a Doctrine PDO driver. Other Doctrine drivers must implement `ExceptionConverterDriver`
(and as such require DBAL version **2.5** or newer) to be able to convert exceptions into API exceptions.
