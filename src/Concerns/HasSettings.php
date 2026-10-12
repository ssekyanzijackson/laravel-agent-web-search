<?php

namespace Ssekyanzijackson\WebSearch\Concerns;

use Illuminate\Container\Container;

trait HasSettings
{
    /**
     * Read a package setting through whichever container is present: the Laravel application in
     * production, a bound container in the framework-free test suite, or the given default.
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        $container = function_exists('app') ? app() : Container::getInstance();

        return $container->bound('config')
            ? $container->make('config')->get($key, $default)
            : $default;
    }
}
