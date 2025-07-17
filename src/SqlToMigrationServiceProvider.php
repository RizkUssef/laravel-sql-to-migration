<?php 
namespace Rizkussef\LaravelSqlToMigration;

use Illuminate\Support\ServiceProvider;
use Rizkussef\LaravelSqlToMigration\Console\SqlToMigrationCommand;

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