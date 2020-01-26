<?php

declare(strict_types=1);

namespace Datashaman\Tongs\Plugins\Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->instance('path.config', __DIR__.'/config');

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
