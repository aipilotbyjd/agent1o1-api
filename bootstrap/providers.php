<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\NodeRegistryServiceProvider;
use App\Providers\SuperAdminServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    NodeRegistryServiceProvider::class,
    SuperAdminServiceProvider::class,
];
