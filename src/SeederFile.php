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
 
    public function generate()
    {
        if (!$this->verify()) {
            throw new Exceptions\NotificationException("Invalid configuration for seeder file ".$this->table);
            return;
        }

        $this->records = DB::connection($this->connectionName)->table($this->table)->get();

        if ($this->records->isEmpty()) {
            throw new Exceptions\NotificationException("Table ".$this->table." has no records. Skipping file generation for it.");
        }

        $this->recordCount = 0;
        $columns = $this->schema->listTableColumns($this->table);

        $commandTabs = str_repeat(chr(9), 3);
        $this->insertCommands = "DB::connection('{$this->connectionName}')->table('{$this->table}')->insert([".chr(10);
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
            $this->insertCommands .= $commandTabs.'['.chr(10).$commandTabs.chr(9).implode(', '.chr(10).
                $commandTabs.chr(9), $fieldsValues).chr(10).$commandTabs.'],'.chr(10);
            $this->recordCount++;
        }
        $this->insertCommands .= chr(9).chr(9).']);';

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

    private function setFileName(): void
    {
        $this->fileName = './database/seeds/'.$this->table.'Seeder.php';
        $this->stubFileName = __DIR__.DIRECTORY_SEPARATOR.'Stubs'.DIRECTORY_SEPARATOR.'SeederFile.php';
    }

    private function saveFile(): void
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

    private function generateContent(): void
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
