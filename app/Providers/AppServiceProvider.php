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
use App\Models\Inventory;
use App\Models\ItemCategory;
use App\Models\Uom;
use App\Observers\ItemObserver;
use App\Observers\StockApiItemObserver;
use App\Observers\StockApiStockMovementObserver;
use App\Observers\StockApiInventoryObserver;
use App\Support\StockApiSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('stock-api', fn (Request $request) => Limit::perMinute(config('stock_api.rate_limit_per_minute'))
            ->by(hash('sha256', (string) $request->bearerToken().'|'.$request->ip())));
        View::composer('layouts.partials._header', MenuComposer::class);

        Blade::if('menuCan', function (string $ability, ?string $routeName = null) {
            return app(MenuPermissionResolver::class)->userCan($ability, $routeName);
        });

        // Observe Shipment model to dispatch notifications when created
        Shipment::observe(ShipmentObserver::class);

        // Auto-compute ROP whenever stock movements change (no controller changes, no scheduler)
        StockMovement::observe(StockMovementObserver::class);
        StockMovement::observe(StockApiStockMovementObserver::class);
        Inventory::observe(StockApiInventoryObserver::class);
        // Recompute ROP when item settings affecting ROP change (e.g., koli/lead time)
        Item::observe(ItemObserver::class);
        Item::observe(StockApiItemObserver::class);
        ItemCategory::saved(fn (ItemCategory $category) => DB::afterCommit(fn () => Item::where('item_category_id', $category->id)->orderBy('id')->each(fn (Item $item) => StockApiSyncService::syncItem($item->id))));
        Uom::saved(fn (Uom $uom) => DB::afterCommit(fn () => Item::where('uom_id', $uom->id)->orderBy('id')->each(fn (Item $item) => StockApiSyncService::syncItem($item->id))));
    }
}
