<?php

use App\EnvKit\EnvKitDebugServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\EnvKitTrustProxies;

return [
    EnvKitTrustProxies::class,
    AppServiceProvider::class,
    EnvKitDebugServiceProvider::class,
];
