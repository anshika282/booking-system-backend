<?php

namespace App\Providers;

use App\Services\TenantManager;
use Illuminate\Support\ServiceProvider;
use App\Models\User; 
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register TenantManager as a singleton
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // --- THIS IS THE CORRECT PLACE FOR GATES AND POLICIES ---

        // This Gate defines a permission called 'manage-team'.
        // It will return 'true' only if the authenticated user's role is 'owner'.
        Gate::define('manage-team', function (User $user) {
            return $user->role === 'owner';
        });

        // You would also register your model policies here.
        // For example:
        // Gate::policy(BookableService::class, BookableServicePolicy::class);
    }
}
