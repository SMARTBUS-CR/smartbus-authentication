<?php

namespace App\Providers;

use App\Documentation\RuleTransformers\ConfirmedRule;
use App\Documentation\RuleTransformers\PasswordRuleTransformer;
use App\Enums\UserRoles;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Define a gate to check if the user has the SUPER_ADMIN role
        Gate::define('viewApiDocs', fn (User $user) => $user->hasRole(UserRoles::SUPER_ADMIN));

        // Configure Scramble to use custom rule transformers for API documentation
        Scramble::configure()
            ->withRuleTransformers([
                ConfirmedRule::class,
                PasswordRuleTransformer::class,
            ]);

        // Add a global header parameter for Accept-Language to all API operations in the generated OpenAPI documentation
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            foreach ($openApi->paths as $path) {
                foreach ($path->operations as $operation) {
                    $operation->addParameters([
                        Parameter::make('Accept-Language', 'header')
                            ->description('Language of the response. Supported values: en, es.')
                            ->setSchema(Schema::fromType((new StringType)->enum(['en', 'es'])->default('es'))),
                    ]);
                }
            }
        });
    }
}
