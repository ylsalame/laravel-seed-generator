# Laravel Seed Generator

Artisan command for Laravel that will create Seeder files for the current data in the DB using a simple visual inteface

## Installation

1) Add `ylsalame/seedgenerator` to composer.json

```
"require": {
	"ylsalame/laravelseedgenerator": "dev-master"
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

### Command options/flags

#### connection
	[optional] The name of the connection to be used. 

	[default] fetched from `Config::database.default`

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

	The path used will be the default one : `\database\seeds`
	The naming convention is `{table_name}Seeder.php`

### Database seeder file

	The database seeder file will contain the trigger for all the table seeder files generated. Skipped table will not be included in this file.

	The path used will be the default one : `\database\seeds`
	Since there is only one file generated it will be saved with the name `DatabaseSeeder.php` to maitain the Laravel standard.
