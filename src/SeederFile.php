<?php

namespace YLSalame\LaravelSeedGenerator;

use YLSalame\LaravelSeedGenerator\Exceptions\NotificationException;
use YLSalame\LaravelSeedGenerator\Exceptions\FaltalException;
use DB;

class SeederFile
{
    private $connectionName;
    private $schema;
    private $table;
    private $records;
    private $recordCount;
    private $content;
    private $fileName;
    private $stubFileName;

    private $optionDontOverwrite;
    private $optionLimitRecords;
    private $optionTargetDirectory;

    private $insertCommands;

    private $singleTab = '    ';

    /**
     * Checks if the class has all necessary data to proceed
     *
     * @return boolean
     */
    private function verify()
    {
        if (!empty($this->connectionName) && !empty($this->table) && !empty($this->schema)) {
            return true;
        }

        return false;
    }
 
    /**
        Generates the inster queries for the current Table

        @return bool
     */
    public function generate(): bool
    {
        if (!$this->verify()) {
            throw new Exceptions\NotificationException("Invalid configuration for seeder file ".$this->table);
        }

        $tableObject = DB::connection($this->connectionName)->table($this->table);

        if (!empty($this->optionLimitRecords)) {
            $this->records = $tableObject->limit($this->optionLimitRecords)->get();
        } else {
            $this->records = $tableObject->get();
        }

        if ($this->records->isEmpty()) {
            throw new Exceptions\NotificationException("Table ".$this->table." has no records. Skipping file generation for it.");
        }

        $this->recordCount = 0;
        $columns = $this->schema->listTableColumns($this->table);

        $commandTabs = str_repeat($this->singleTab, 4);
        $this->insertCommands = "DB::connection('{$this->connectionName}')->table('{$this->table}')->insert(".chr(10).$this->singleTab.$this->singleTab.$this->singleTab."[".chr(10);
        foreach ($this->records as $record) {
            $fieldsValues = [];
            foreach ($columns as $column) {
                if ($column->getAutoIncrement()) {
                    continue;
                }

                if ($column->getType() == 'Boolean') {
                    $value = ($record->{$column->getName()}) ? 'true' : 'false';
                } else {
                    $value = str_replace("'", "/\'", $record->{$column->getName()});
                    $value = $value == '' ? 'null' : "'".$value."'";
                }
                $fieldsValues[] = "'{$column->getName()}' => {$value}";
            }
            $this->insertCommands .= $commandTabs.
                '['.
                chr(10).
                $commandTabs.
                $this->singleTab.
                implode(', '.chr(10).$commandTabs.$this->singleTab, $fieldsValues).
                chr(10).
                $commandTabs.
                '],'.
                chr(10);
            $this->recordCount++;
        }
        $this->insertCommands .= $this->singleTab.$this->singleTab.$this->singleTab.']'.chr(10).$this->singleTab.$this->singleTab.');';

        $this->setFileName();
        $this->generateContent();
        $this->saveFile();

        return true;
    }

    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
    }

    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }

    protected function setFileName(): void
    {
        $this->fileName = '.'.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'seeds'.DIRECTORY_SEPARATOR.$this->table.'Seeder.php';
        if (!empty($this->optionTargetDirectory)) {
            $this->fileName = $this->optionTargetDirectory.DIRECTORY_SEPARATOR.$this->table.'Seeder.php';
        }

        $this->stubFileName = __DIR__.DIRECTORY_SEPARATOR.'Stubs'.DIRECTORY_SEPARATOR.'SeederFile.php';
    }

    protected function saveFile(): void
    {
        if (file_exists($this->fileName) && $this->optionDontOverwrite) {
            throw new Exceptions\NotificationException("Seeder file for {$this->table} already exists. 
                File will not be overwritten (dont_overwrite flag in effect)");
        }

        try {
            file_put_contents($this->fileName, $this->content);
        } catch (\Exception $e) {
            throw new Exception('Error while writing contents to :'.$this->fileName.' Error : '.$e->getMessage());
        }
    }

    /**
        Generates the content for the current File

        @return void
     */
    protected function generateContent(): void
    {
        try {
            $content = file_get_contents($this->stubFileName);
        } catch (\Exception $e) {
            throw new Exception('SeederFile - Error while fetching contents for :'.$this->stubFileName.' Error : '.$e->getMessage());
        }

        $placeholders = [
            '{{$timestamp}}',
            '{{$table}}',
            '{{$recordCount}}',
            '{{$insertCommands}}'
        ];
        $values = [
            date('Y-m-d H:i:s'),
            $this->table,
            $this->recordCount,
            $this->insertCommands
        ];

        $this->content = str_replace($placeholders, $values, $content);
    }
}
