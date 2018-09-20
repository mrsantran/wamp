<?php

namespace Santran\WAMPServer;

use Illuminate\Support\ServiceProvider;
use Santran\WAMPServer\Console\GenerateCommand;
use Santran\WAMPServer\Console\ListenCommand;
use Santran\WAMPServer\Generators\Generator;

class WAMPServerServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        $source = realpath(__DIR__ . '/../config/wamp.php');
        $this->publishes([$source => config_path('wamp.php')]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->registerWAMPServer();
        $this->registerCommands();
    }

    /**
     * Register the application bindings.
     *
     * @return void
     */
    private function registerWAMPServer() {
        $this->app->singleton('wamp', function ($app) {
            return new WAMPServer($app);
        });
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    private function registerCommands() {

        $this->app->singleton('command.wamp.listen', function ($app) {
            return new ListenCommand($app);
        });

        $this->app->singleton('command.wamp.generate', function ($app) {
            $path = app_path('WAMPServer');

            $generator = new Generator($app['files']);

            return new GenerateCommand($generator, $path);
        });

        $this->commands('command.wamp.listen', 'command.wamp.generate');
    }

}
