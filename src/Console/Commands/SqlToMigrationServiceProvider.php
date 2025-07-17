<?php 
namespace Rizkussef\LaravelSqlToMigration\Console\Commands;

use Illuminate\Support\ServiceProvider;
use Rizkussef\LaravelSqlToMigration\Console\Commands\SqlToMigrationCommand;

class SqlToMigrationServiceProvider extends ServiceProvider
{
    public function register()
    {
        // No bindings for now
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SqlToMigrationCommand::class,
            ]);
        }
    }
}