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
}
