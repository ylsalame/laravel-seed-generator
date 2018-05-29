<?php

namespace YLSalame\SeedGenerator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use DB;

class SeedGenerator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seedgenerator
                            {--connection= : target connection to be used (defaults to the default one in /config/database)}
                            {--skipped_tables= : avoid specific tables (comma-delimited)} 
                            {--dont_overwrite : does not overwrite existing seeder classes in /database/seeds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Seeder Generator classes for all tables in a specific connection';

    private $connectionName;
    private $connection;
    private $schema;
    private $tables = [];
    private $skippedTables = [];
    private $database;

    private $seededTables = [];

    private $seederRecordCount;
    private $seederFileTableName;
    private $seederFileContent;
    private $seederFileName;

    private $seederDatabaseFileContent;
    private $seederDatabaseFilePath = './database/seeds/DatabaseSeeder.php';

    /**
     * Custom connection name to be used throughout the script. Passed by an option value.
     *
     * @var string
     */
    private $optionConnection;

    /**
     * List of Tables that must be skipped from creating the Seeder Class. Passed by an option value and comma-delimited.
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
            $this->raiseError('Could not find or access the connection '.$this->connectionName);
        }
    }

    private function setConnectionName($connectionName): void
    {
        $this->connectionName = $connectionName;
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
        $this->optionConnection = $this->option('connection');
        $this->optionSkippedTables = $this->option('skipped_tables');
        $this->optionDontOverwrite = $this->option('dont_overwrite');

        $this->skippedTables = explode(',', $this->optionSkippedTables);

        $this->setConnectionName(empty($this->optionConnection) ? Config::get('database.default') : $this->optionConnection);

        $this->doEcho();
        $this->doEcho(str_repeat('=', 50));
        $this->doEcho('Seeder Generator');
        $this->doEcho(str_repeat('=', 50));
        $this->doEcho('Connection name used: '.$this->connectionName.chr(10));

        $this->testConnection();
        $this->setSchema();
        $this->setTables();

        $this->doEcho('Tables to be scanned and have their seeder classes generated'.chr(10));
        foreach ($this->tables as $table)
            $this->doEcho(chr(9).$table);

        if (!empty($this->skippedTables))
        {
            $this->doEcho();
            $this->doEcho('Tables skipped'.chr(10));
            foreach ($this->skippedTables as $table)
                $this->doEcho(chr(9).$table);
        }

        $this->doEcho();
        $this->doEcho(count($this->tables).' files will be generated.');

        if ($this->optionDontOverwrite)
            $this->doEcho('Existing files WILL NOT be overwritten!');
        else
            $this->doEcho('Existing files WILL be overwritten!');

        if (!$this->confirm(chr(9).'Confirm?'))
            die('SeedGenerator aborted'.chr(10));
        else {
            if (!$this->optionDontOverwrite)
                if (!$this->confirm(chr(9).'Existing files WILL BE OVERWRITTEN! Re-Confirm?'))
                    die('SeedGenerator aborted'.chr(10));
        }

        $this->generateSeeders();
        $this->generateSeedersDatabaseFile();

        $this->doEcho();
        $this->doEcho('Seeder files generated sucessfully.');
        $this->doEcho();
    }

    private function raiseError(String $msg)
    {
        $this->error(chr(9).str_repeat('*', 50));
        $this->error(chr(9).$msg);
        $this->error(chr(9).str_repeat('*', 50));
        die();
    }

    private function doEcho(String $msg = '')
    {
        $this->line(chr(9).$msg);
    }

    private function generateSeeders()
    {

        foreach ($this->tables as $table)
        {
            $seederFile = new SeederFile;
            $seederFile->setConnectionName($this->connectionName);
            $seederFile->setSchema($this->schema);
            $seederFile->setTable($table);
            $seederFile->generate();
        }


die();

        $commandTabs = str_repeat(chr(9), 3);
        foreach ($this->tables as $table)
        {
            $this->seederFileTableName = $table;
            $records = DB::connection($this->connectionName)->table($table)->get();
//            if ($records->isEmpty())
//                continue;

            $recordCount = 0;
            $columns = $this->schema->listTableColumns($table);
            $insertCommands = "DB::connection('{$this->connectionName}')->table('{$table}')->insert([".chr(10);
            foreach ($records as $record)
            {
                $fieldsValues = [];
                foreach ($columns as $column)
                {
                    if ($column->getAutoIncrement())
                        continue;

                    if ($column->getType() == 'Boolean')
                    {
                        $value = ($record->{$column->getName()}) ? 'true' : 'false';
                    } else {
                        $value = str_replace("'", "/\'", $record->{$column->getName()});
                        $value = $value == '' ? 'null' : "'".$value."'";
                    }
                    $fieldsValues[] = "'{$column->getName()}' => {$value}";
                }
                $insertCommands .= $commandTabs.'['.chr(10).$commandTabs.chr(9).implode(', '.chr(10).$commandTabs.chr(9), $fieldsValues).chr(10).$commandTabs.'],'.chr(10);
                $recordCount++;
            }
            $insertCommands .= chr(9).chr(9).']);';                

            $this->setSeederRecordCount($recordCount);
            $this->setSeederFileContent($table, $insertCommands);
            $this->setSeederFileName($table);
            $this->saveSeederFile();

            $this->seededTables[] = $table;
        }
    }

    private function generateSeedersDatabaseFile()
    {
        $this->setSeederDatabaseFileContent();
        $this->saveDatabaseSeederFile();
    }

    private function saveDatabaseSeederFile()
    {
        if (file_exists($this->seederDatabaseFilePath) && $this->optionDontOverwrite) {
            $this->doEcho('DatabaseSeeder file already exists. File will not be overwritten (dont_overwrite flag in effect).');
            return;
        }

        $this->doEcho('Saved main DatabaseSeeder file in '.$this->seederDatabaseFilePath);
        file_put_contents($this->seederDatabaseFilePath, $this->seederDatabaseFileContent);
    }

    private function setSeederRecordCount($recordCount)
    {
        $this->seederRecordCount = $recordCount;
    }

    private function saveSeederFile()
    {
        if (file_exists($this->seederFileName) && $this->optionDontOverwrite) {
            $this->doEcho("Seeder file for {$this->seederFileTableName} already exists. File will not be overwritten (dont_overwrite flag in effect)");
            return;
        }

        $this->doEcho("Saved Seeder file for {$this->seederFileTableName} with {$this->seederRecordCount} records in ".$this->seederFileName);
        file_put_contents($this->seederFileName, $this->seederFileContent);
    }

    private function setSeederFileName(String $table)
    {
        $this->seederFileName = './database/seeds/'.$table.'Seeder.php';
    }

    private function setSeederFileContent(String $table, String $insertCommands)
    {
        $timestamp = date('Y-m-d H:i:s');
        $this->seederFileContent = <<<EOT
<?php
/*
 * Generated on {$timestamp}
 * Generated by SeedGenerator
 * Records seeded: {$this->seederRecordCount}
*/

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$table}Seeder extends Seeder
{
    public function run()
    {
        {$insertCommands}
    }
}

EOT;
    }

    private function setSeederDatabaseFileContent()
    {
        $timestamp = date('Y-m-d H:i:s');
        $seederCalls = implode('Seeder::class,'.chr(10).chr(9).chr(9).chr(9), $this->seededTables).'Seeder::class';
        $seededTablesCount = count($this->seededTables);

        $this->seederDatabaseFileContent = <<<EOT
<?php
/*
 * Generated on {$timestamp}
 * Generated by SeedGenerator
 * Tables seeded: {$seededTablesCount}
*/

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        \$this->call([
            {$seederCalls}
        ]);
    }
}

EOT;
    }

}
