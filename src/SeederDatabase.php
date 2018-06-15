<?php

namespace YLSalame\LaravelSeedGenerator;

use YLSalame\LaravelSeedGenerator\Exceptions\NotificationException;
use YLSalame\LaravelSeedGenerator\Exceptions\FaltalException;
use DB;

class SeederDatabase
{
    private $tables;
    private $content;
    private $fileName;

    private $optionDontOverwrite;

    public function generate()
    {
        $this->setFileName();
        $this->generateContent();
        $this->saveFile();

        return true;
    }

    public function __set(String $name, $value):void
    {
        if (method_exists($this, $name)) {
            $this->$name($value);
        } else {
            $this->$name = $value;
        }
    }

    public function __get($name)
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        } elseif (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }

    private function setFileName(): void
    {
        $this->fileName = './database/seeds/DatabaseSeeder.php';
        $this->stubFileName = __DIR__.DIRECTORY_SEPARATOR.'Stubs'.DIRECTORY_SEPARATOR.'SeederDatabaseFile.php';
    }

    private function saveFile(): void
    {
        if (file_exists($this->fileName) && $this->optionDontOverwrite) {
            throw new Exceptions\NotificationException("Database Seeder file already exists. File will not be overwritten (dont_overwrite flag in effect)");
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
            throw new Exception('Error while fetching contents for :'.$this->stubFileName.' Error : '.$e->getMessage());
        }

        $seederCalls = implode('Seeder::class,'.chr(10).chr(9).chr(9).chr(9), $this->tables).'Seeder::class';

        $placeholders = [
            '{{$timestamp}}',
            '{{$tablesCount}}',
            '{{$seederCalls}}'
        ];
        $values = [
            date('Y-m-d H:i:s'),
            count($this->tables),
            $seederCalls
        ];

        $this->content = str_replace($placeholders, $values, $content);
    }
}
