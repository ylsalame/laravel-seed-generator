# laravel-seed-generator
Artisan command for Laravel that will create Seeder files for the current data in the DB using a simple visual inteface

## Installation

1) Add `ylsalame/seedgenerator` to composer.json

```
    "require": {
		"ylsalame/laravelseedgenerator": "dev-master"
    }
```

2) Update Composer from the CLI:

	composer update

## Usage (Artisan)

```
php artisan seedgenerator
```

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

