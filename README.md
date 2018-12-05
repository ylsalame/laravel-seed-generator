# Laravel Seed Generator

Artisan command for Laravel that will create Seeder files for the current data in the DB using a simple visual interface

## Installation

1) Add `ylsalame/seedgenerator` to composer.json

```
composer require ylsalame/laravelseedgenerator
```

or

```
"require": {
	"ylsalame/laravelseedgenerator": "~0.4"
}
```

2) Update Composer from the CLI:

```
composer update
```

## Usage (Artisan)

```
php artisan seedgenerator
```
Seed Generator is a scripted tool with a visual interface. This means that you will not have any action executed before you visual confirm and ackowledge what is going to be done. Here is an example of what the inface looks like:

### Command options/flags

#### connection
	[optional] The name of the connection to be used. 
	[default] fetched from Config::database.default
	If no connection is passed and no conneciton is configured in the Laravel config the script will raise an exception.

#### skipped_tables
	[optional] A list of tables for the script to skip delimited by commas
	[default] {empty}

#### dont_overwrite
	[optional] Boolean flag that will make the script avoid overwritting exiting files
	[default] false

#### limit_records
	[optional] The amount of records to limit when generating the seeder file for a table. If ommited, all records will be fetched and added to the seeder file
	[default] {empty}

## Output

### Table seeder files

Each table detected in the schema will have its own seeder file generated. This seeder file will contain the entire data the table contains at the time.

	Path: \database\seeds
	Naming convention: {table_name}Seeder.php

### Database seeder file

The database seeder file will contain the trigger for all the table seeder files generated. Skipped table will not be included in this file.

	Path: \database\seeds
	Naming convention: It will be saved with the name DatabaseSeeder.php to maintain the Laravel standard.
