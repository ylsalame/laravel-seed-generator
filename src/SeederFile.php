<?php

namespace YLSalame\SeedGenerator;

use DB;

class SeederFile
{
    private $connectionName;
    private $schema;
    private $table;
    private $records;
    private $fileName;
    private $recordCount;
    private $content;

    private $optionDontOverwrite;

    public function generateContent()
    {

        $this->records = DB::connection($this->connectionName)->table($this->table)->get();
//            if ($records->isEmpty())
//                continue;

        $recordCount = 0;
        $columns = $this->schema->listTableColumns($table);

        $commandTabs = str_repeat(chr(9), 3);
        $insertCommands = "DB::connection('{$this->connectionName}')->table('{$this->table}')->insert([".chr(10);
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

        $this->setRecordCount($recordCount);
        $this->setSeederFileContent($table, $insertCommands);
        $this->setSeederFileName($table);
        $this->saveFile();

        $this->seededTables[] = $table;
    }

    public function setTable(String $table)
    {
        $this->table = $table;
    }

    public function getTable(): String
    {
        return $table;
    }

    private function setFileName()
    {
        $this->fileName = './database/seeds/'.$this->getTable().'Seeder.php';
    }

    private function getFileName(): String
    {
        return $this->fileName;
    }

    private function setRecordCount(Integer $recordCount)
    {
        $this->recordCount = $recordCount;
    }

    public function getRecordCount(): Integer
    {
        return $this->recordCount;
    }

    public function setOptionDontOverwrite(Boolean $optionDontOverwrite)
    {
        $this->optionDontOverwrite = $optionDontOverwrite;
    }

    public function getOptionDontOverwrite(): Boolean
    {
        return $this->optionDontOverwrite;
    }



    public function saveFile()
    {
        if (file_exists($this->getFileName()) && $this->getOptionDontOverwrite()) {
            $this->doEcho("Seeder file for {$this->seederFileTableName} already exists. File will not be overwritten (dont_overwrite flag in effect)");
            return;
        }

        $this->doEcho("Saved Seeder file for {$this->seederFileTableName} with {$this->seederRecordCount} records in ".$this->getFileName());
        file_put_contents($this->getFileName(), $this->getFileContent());
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


}