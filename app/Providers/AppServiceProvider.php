<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
use App\Support\MenuPermissionResolver;
use App\View\Composers\MenuComposer;
use App\Models\Shipment;
use App\Observers\ShipmentObserver;
use App\Models\StockMovement;
use App\Observers\StockMovementObserver;
use App\Models\Item;
use App\Observers\ItemObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MenuPermissionResolver::class, function () {
            return new MenuPermissionResolver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.partials._header', MenuComposer::class);

        Blade::if('menuCan', function (string $ability, ?string $routeName = null) {
            return app(MenuPermissionResolver::class)->userCan($ability, $routeName);
        });

        // Observe Shipment model to dispatch notifications when created
        Shipment::observe(ShipmentObserver::class);

        // Auto-compute ROP whenever stock movements change (no controller changes, no scheduler)
        StockMovement::observe(StockMovementObserver::class);
        // Recompute ROP when item settings affecting ROP change (e.g., koli/lead time)
        Item::observe(ItemObserver::class);
    }
}
