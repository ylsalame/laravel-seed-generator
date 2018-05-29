<?php

namespace YLSalame\LaravelSeedGenerator;

use Illuminate\Support\ServiceProvider;

class SeederServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedGenerator::class
            ]);
        }
    }


/*
    public function register()
    {
        $this->app->singleton('seedgenerator', function($app) {
            return new SeedGenerator;
        });

        $this->app->booting(function() {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Iseed', 'Orangehill\Iseed\Facades\Iseed');
        });

        $this->app->singleton('command.iseed', function($app) {
            return new IseedCommand;
        });

        $this->commands('command.iseed');
    }

    public function provides()
    {
        return array('iseed');
    }
*/

}