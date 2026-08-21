<?php

use App\Providers\AppServiceProvider;

return [
    App\Providers\EnvKitTrustProxies::class,
    AppServiceProvider::class,
    App\EnvKit\EnvKitDebugServiceProvider::class,
];
