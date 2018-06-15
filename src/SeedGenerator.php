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
                            {--dont_overwrite : does not overwrite existing seeder classes in /database/seeds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Seeder Generator classes for all tables in a specific connection';

    /**
     * Connection name used by the entire script. Can be overridden in the command line options
     * to use an alternative one
     *
     * @var string
     */
    private $connectionName;

    /**
     * Connection object used to access the DB
     *
     * @var string
     */
    private $connection;

    /**
     * Dabatase ORM schema object used to fetch the database structure
     *
     * @var string
     */
    private $schema;

    /**
     * Array of tables available in the database
     *
     * @var array
     */
    private $tables = [];

    /**
     * List of skipped tables defined in the command line option
     *
     * @var string
     */
    private $skippedTables = [];

    /**
     * Custom connection name to be used throughout the script. Passed by an option value.
     *
     * @var string
     */
    private $optionConnection;

    /**
     * List of Tables that must be skipped from creating the Seeder Class. Passed by an option
     * value and comma-delimited.
     *
     * @var string
     */
    private $optionSkippedTables;

    /**
     * Boolean flag defining if existing files whould be overwritten. Passed by an option value.
     *
     * @var boolean
     */
    private $optionDontOverwrite;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function testConnection()
    {
        try {
            $this->connection = DB::connection($this->connectionName)->getDoctrineConnection();
        } catch (\Exception $e) {
            $this->raiseError('Could not find or access the connection named "'.$this->connectionName.'"');
        }
    }

    private function setSchema()
    {
        try {
            $this->schema = $this->connection->getSchemaManager();
        } catch (\Exception $e) {
            $this->raiseError('Could not fetch the schema for the connection '.$this->connectionName);
        }
    }

    private function setTables()
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

    private function showInterfaceBlock(int $interfaceBlock): void
    {
        switch ($interfaceBlock) {
            case 1:
                $this->doEcho();
                $this->doEcho(str_repeat('=', 60));
                $this->doEcho('Seeder Generator');
                $this->doEcho(str_repeat('=', 60));

                if ($this->connectionName == Config::get('database.default')) {
                    $this->doEcho('No custom connection defined. Connection used will be the default one configured in Config::database.default!');
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
    
    private function fetchOptions()
    {
        $this->optionConnection = $this->option('connection');
        $this->optionSkippedTables = $this->option('skipped_tables');
        $this->optionDontOverwrite = $this->option('dont_overwrite');
        $this->skippedTables = explode(',', $this->optionSkippedTables);
        $this->connectionName = empty($this->optionConnection) ? Config::get('database.default') : $this->optionConnection;
    }

    private function raiseError(String $msg)
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

    private function doEcho(String $msg = '')
    {
        $this->line(chr(9).$msg);
    }

    private function generateSeeders()
    {
        foreach ($this->tables as $table) {
            $seederFile = new SeederFile;
            $seederFile->optionDontOverwrite = $this->optionDontOverwrite;
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

    private function generateSeedersDatabaseFile()
    {
        if (empty($this->tables)) {
            $this->doEcho('No tables present to include in the Database Seeder file.');
            return;
        }

        $seederDatabase = new SeederDatabase;
        $seederDatabase->optionDontOverwrite = $this->optionDontOverwrite;
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
