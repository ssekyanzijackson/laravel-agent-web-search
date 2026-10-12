<?php

namespace AgentWebSearch;

use AgentWebSearch\Console\InstallCommand;
use Illuminate\Support\ServiceProvider;

class WebSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/web-search.php', 'web-search');

        $this->app->singleton(DonsetchClient::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../config/web-search.php' => config_path('web-search.php'),
            ], 'web-search');
        }
    }
}
