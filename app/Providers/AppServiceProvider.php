<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo, Operation $operation) {
            $namespace = $routeInfo->className();
            
            if (!$namespace) {
                return ['General'];
            }
            
            $controllerName = class_basename($namespace);
            $name = Str::replace('Controller', '', $controllerName);
            $name = Str::headline($name);
            
            if (Str::contains($namespace, '\\Mahasiswa\\')) {
                return ['Mahasiswa - ' . $name];
            }
            
            if (Str::contains($namespace, '\\Superadmin\\')) {
                return ['Superadmin - ' . $name];
            }

            if (Str::contains($namespace, '\\Admin\\')) {
                return ['Admin - ' . $name];
            }

            return [$name];
        });
    }
}
