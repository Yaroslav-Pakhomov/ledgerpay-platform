<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\PrometheusServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    HorizonServiceProvider::class,
    PrometheusServiceProvider::class,
];
