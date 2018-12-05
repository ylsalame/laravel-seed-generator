<?php

namespace YLSalame\LaravelSeedGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use YLSalame\LaravelSeedGenerator\Exceptions\NotificationException;
use YLSalame\LaravelSeedGenerator\Exceptions\FatalException;
use DB;

class SeedGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seedgenerator
                            {--connection= : connection to be used (defaults to the default one in /config/database)}
                            {--skipped_tables= : avoid specific tables (comma-delimited)} 
                            {--dont_overwrite= : does not overwrite existing seeder classes in /database/seeds}
                            {--limit_records= : limits the amount of records to fetch from tables}
                            {--target_directory= : specify a directory other than database/seeds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Seeder classes with existing records for tables in a specific connection';

    /**
     * Connection name used by the entire script. Can be overridden in the 
     * command line options to use an alternative one
     *
     * @var string
     */
    protected $connectionName;

    /**
     * Connection object used to access the DB
     *
     * @var string
     */
    protected $connection;

    /**
     * Dabatase ORM schema object used to fetch the database structure
     *
     * @var string
     */
    protected $schema;

    /**
     * Array of tables available in the database
     *
     * @var array
     */
    protected $tables = [];

    /**
     * List of skipped tables defined in the command line option
     *
     * @var string
     */
    protected $skippedTables = [];

    /**
     * Custom connection name to be used throughout the script. Passed by 
     * an option value.
     *
     * @var string
     */
    protected $optionConnection;

    /**
     * List of Tables that must be skipped from creating the Seeder Class. Passed by 
     * an option value and comma-delimited.
     *
     * @var string
     */
    protected $optionSkippedTables;

    /**
     * Boolean flag defining if existing files whould be overwritten. Passed by 
     * an option value.
     *
     * @var boolean
     */
    protected $optionDontOverwrite;

    /**
     * Limits the amount of records that will be fecthes from the tables
     *
     * @var int
     */
    protected $optionLimitRecords;

    /**
     * Limits the amount of records that will be fecthes from the tables
     *
     * @var int
     */
    protected $optionTargetDirectory;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
        Fetches the current connection and validates it

        @return void
     */
    protected function testConnection(): void
    {
        try {
            $this->connection = DB::connection($this->connectionName)->getDoctrineConnection();
        } catch (\Exception $e) {
            $this->raiseError('Could not find or access the connection named "'.$this->connectionName.'"'.chr(10).chr(10).chr(9).'error message: '.$e->getMessage());
        }
    }

    /**
        Fetches the table schema from the connection

        @return void
     */
    protected function setSchema(): void
    {
        try {
            $this->schema = $this->connection->getSchemaManager();
        } catch (\Exception $e) {
            $this->raiseError('Could not fetch the schema for the connection '.$this->connectionName);
        }
    }

    /**
        Triggers the "show tables" on the schema and removed skipped tables from it

        @return void
     */
    protected function setTables(): void
    {
        try {
            $this->tables = $this->schema->listTableNames();
        } catch (\Exception $e) {
            $this->raiseError('Could not fetch the tables for the schema in the connection '.$this->connectionName);
        }

        //first match the tables found with the ones in the command option to make sure they exist and can be removed
        $this->skippedTables = array_intersect($this->skippedTables, $this->tables);

        //then remove tables that are set to not be run on
        $this->tables = (array_diff($this->tables, $this->skippedTables));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $this->fetchOptions();

            $this->showInterfaceBlock(1);

            $this->testConnection();
            $this->setSchema();
            $this->setTables();

            $this->showInterfaceBlock(2);

            $this->generateSeeders();
            $this->generateSeedersDatabaseFile();

            $this->doEcho();
            $this->doEcho('Seeder routine finished');
            $this->doEcho();
        } catch (FatalException $e) {
            $this->doEcho('Fatal Exception happened - '.$e->getMessage());
            die();
        }
    }

    /**
        Manages the interface and the state it must be in

        @param in $interfaceBlock The indentifier of block to be shown

        @return void
     */
    protected function showInterfaceBlock(int $interfaceBlock): void
    {
        switch ($interfaceBlock) {
        case 1:
            $this->doEcho();
            $this->doEcho(str_repeat('=', 60));
            $this->doEcho('Seeder Generator');
            $this->doEcho(str_repeat('=', 60));

            if ($this->connectionName == Config::get('database.default')) {
                $this->doEcho('No custom connection defined. '.chr(10).chr(9).'Connection used will be the default one configured in Config::database.default!');

                if (!empty(env('DB_CONNECTION'))) {
                    $this->doEcho('Connection defined in ENV variable DB_CONNECTION. Value: '.env('DB_CONNECTION'));
                } else {
                    $this->doEcho('Connection name used: '.Config::get('database.default'));
                }

            } else {
                $this->doEcho('Connection used will be the custom one: "'.$this->optionConnection.'"');
            }
            $this->doEcho();
            break;

        case 2:
            $this->doEcho('Tables to be scanned and have their seeder classes generated'.chr(10));

            foreach ($this->tables as $table) {
                $this->doEcho(chr(9).$table);
            }

            if (!empty($this->skippedTables)) {
                $this->doEcho();
                $this->doEcho('Tables skipped'.chr(10));
                foreach ($this->skippedTables as $table) {
                    $this->doEcho(chr(9).$table);
                }
            }

            $this->doEcho();
            $this->doEcho(chr(9).count($this->tables).' seeder files will be generated.');
            $this->doEcho(chr(9).'Database seeder file will also be generated');
            $this->doEcho();

            if ($this->optionDontOverwrite) {
                $this->doEcho('Existing files WILL NOT be overwritten!');
            } else {
                $this->doEcho('Existing files WILL be overwritten!');
            }

            if (!$this->confirm(chr(9).'Confirm?')) {
                throw new FatalException('SeedGenerator aborted');
            } else {
                if (!$this->optionDontOverwrite) {
                    if (!$this->confirm(chr(9).'Existing files WILL BE OVERWRITTEN! Re-Confirm?')) {
                        throw new FatalException('SeedGenerator aborted');
                    }
                }
            }
            break;
        }
    }
    
    /**
        Fetches the options from the command line and assigns them to their 
        related class attribute

        @return void
     */
    protected function fetchOptions(): void
    {
        $this->optionConnection = $this->option('connection');
        $this->optionSkippedTables = $this->option('skipped_tables');
        $this->optionDontOverwrite = $this->option('dont_overwrite');
        $this->optionLimitRecords = $this->option('limit_records');
        $this->optionTargetDirectory = $this->option('target_directory');

        $this->skippedTables = explode(',', $this->optionSkippedTables);
        $this->connectionName = empty($this->optionConnection) ? Config::get('database.default') : $this->optionConnection;

        if (!empty($this->optionTargetDirectory) && !is_dir($this->optionTargetDirectory)) {
            throw new FatalException('Invalid directory specified');
        }

    }

    /**
        Outputs a formatted error message to the terminal

        @param String $msg The message to be outputted

        @return void
     */
    protected function raiseError(String $msg): void
    {
        $this->doEcho();
        $this->error(chr(9).str_repeat('*', 60));
        $this->error(chr(9).str_repeat(' ', 60));
        $this->error(chr(9).$msg);
        $this->error(chr(9).str_repeat(' ', 60));
        $this->error(chr(9).str_repeat('*', 60));
        $this->doEcho();
        die();
    }

    /**
        Outputs a single line to the terminal

        @param String $msg The text to be outputted

        @return void
     */
    protected function doEcho(String $msg = ''): void
    {
        $this->line(chr(9).$msg);
    }

    /**
        Triggers the generation of a seeder for each table found in the schema

        @return void
     */
    protected function generateSeeders(): void
    {
        foreach ($this->tables as $table) {

            if ($table == 'migrations') {
                continue;
            }

            $seederFile = new SeederFile;
            $seederFile->optionDontOverwrite = $this->optionDontOverwrite;
            $seederFile->optionLimitRecords = $this->optionLimitRecords;
            $seederFile->optionTargetDirectory = $this->optionTargetDirectory;
            $seederFile->connectionName = $this->connectionName;
            $seederFile->schema = $this->schema;
            $seederFile->table = $table;

            try {
                if ($seederFile->generate()) {
                    $this->doEcho("Table {$seederFile->table} has {$seederFile->recordCount} records. Saved in ".$seederFile->fileName);
                }
            } catch (Exceptions\NotificationException $e) {
                $this->line(chr(9).'WARNING '.$e->getMessage());
                continue;
            }
        }
    }

    /**
        Generates a single database seeder master file that will trigger all seeders

        @return void
     */
    protected function generateSeedersDatabaseFile(): void
    {
        if (empty($this->tables)) {
            $this->doEcho('No tables present to include in the Database Seeder file.');
            return;
        }

        $seederDatabase = new SeederDatabase;
        $seederDatabase->optionDontOverwrite = $this->optionDontOverwrite;
        $seederDatabase->optionTargetDirectory = $this->optionTargetDirectory;
        $seederDatabase->tables = $this->tables;

        try {
            if ($seederDatabase->generate()) {
                $this->doEcho('Database seeder file generated. Save in: '.$seederDatabase->__get('fileName'));
            }
        } catch (Exceptions\NotificationException $e) {
            $this->line(chr(9).'WARNING '.$e->getMessage());
        }
    }
}
