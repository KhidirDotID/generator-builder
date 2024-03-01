<?php

namespace InfyOm\GeneratorBuilder\Providers;

use Illuminate\Support\ServiceProvider;
use InfyOm\GeneratorBuilder\Commands\GeneratorBuilderRoutesPublisherCommand;

class GeneratorBuilderServiceProvider extends ServiceProvider
{

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'generator_builder');

        $this->app->singleton('infyom.publish:generator-builder', function ($app) {
            return new GeneratorBuilderRoutesPublisherCommand($app);
        });

        $this->commands([
            'infyom.publish:generator-builder',
        ]);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            $this->getConfigPath() => config_path('generator_builder.php'),
        ], 'generator-builder-config');

        $this->publishes([
            $this->getViewPath() => resource_path('views/vendor/generator-builder'),
        ], 'generator-builder-templates');

        $this->loadViewsFrom($this->getViewPath(), 'generator-builder');
    }

    public function getConfigPath()
    {
        return __DIR__ . '/../../config/generator_builder.php';
    }

    public function getViewPath()
    {
        return __DIR__ . '/../../views';
    }
}
