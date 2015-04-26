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

## DB Migrations

KoolKode Database ships with a command line tool called **kkdb** that can be used to create and apply DB migrations.
Every command (except for `migration:generate`) requires a DB connection to be configured. The tool requires a
file called `.kkdb.php` to be present in the same directory as the `composer.json` of your project. The file
is being used to configure DB connections and migration directories.

The tool will help you (interactively) with the generation of **.kkdb.php** when the file does not exist as soon as you try to
execute one of the commands that require a DB connection.

### flush

Will remove all tables and views from a configured DB. It has an optional flag `t` that will truncate all data instead
of dropping schema objects.

### migration:generate

Generates a migration file (PHP-script file) in a directory called `migration` that must be present on the same level
as the `composer.json` of your project.

### migration:up

Applies all UP migrations using all migration directories configured in you `.kkdb.php` file.
