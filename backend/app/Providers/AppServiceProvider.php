<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Policies\BomPolicy;
use App\Policies\BuyerOrderPolicy;
use App\Policies\DeliveryPolicy;
use App\Policies\FinancePolicy;
use App\Policies\InventoryPolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\PlanningPolicy;
use App\Policies\ProcurementPolicy;
use App\Policies\ProductionPolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        Gate::define('dashboard.view', [WorkspacePolicy::class, 'viewDashboard']);
        Gate::define('reports.view', [WorkspacePolicy::class, 'viewReport']);
        Gate::define('reports.export', [WorkspacePolicy::class, 'exportReport']);
        Gate::define('alerts.view', [WorkspacePolicy::class, 'viewAlert']);
        Gate::define('alerts.manage', [WorkspacePolicy::class, 'manageAlert']);
        foreach (['executive', 'supply-chain', 'production', 'procurement', 'warehouse'] as $dashboard) {
            Gate::define('dashboard.'.$dashboard.'.view', fn (User $user): bool => $user->hasPermission('dashboard.'.$dashboard.'.view'));
        }
        Gate::define('master-data.view', [MasterDataPolicy::class, 'view']);
        Gate::define('master-data.manage', [MasterDataPolicy::class, 'manage']);
        Gate::define('bom.view', [BomPolicy::class, 'view']);
        Gate::define('bom.manage', [BomPolicy::class, 'manage']);
        Gate::define('buyer-order.view', [BuyerOrderPolicy::class, 'view']);
        Gate::define('buyer-order.manage', [BuyerOrderPolicy::class, 'manage']);
        Gate::define('buyer-order.approve', [BuyerOrderPolicy::class, 'approve']);
        Gate::define('buyer-order.confirm', [BuyerOrderPolicy::class, 'confirm']);
        Gate::define('planning.view', [PlanningPolicy::class, 'view']);
        Gate::define('planning.manage', [PlanningPolicy::class, 'manage']);
        Gate::define('procurement.view', [ProcurementPolicy::class, 'view']);
        Gate::define('procurement.manage', [ProcurementPolicy::class, 'manage']);
        Gate::define('procurement.approve', [ProcurementPolicy::class, 'approve']);
        Gate::define('inventory.view', [InventoryPolicy::class, 'view']);
        Gate::define('inventory.manage', [InventoryPolicy::class, 'manage']);
        Gate::define('inventory.adjust', [InventoryPolicy::class, 'adjust']);
        Gate::define('production.view', [ProductionPolicy::class, 'view']);
        Gate::define('production.manage', [ProductionPolicy::class, 'manage']);
        Gate::define('production.approve', [ProductionPolicy::class, 'approve']);
        Gate::define('production.override', [ProductionPolicy::class, 'override']);
        Gate::define('sales.view', [SalesOrderPolicy::class, 'view']);
        Gate::define('sales.manage', [SalesOrderPolicy::class, 'manage']);
        Gate::define('sales.confirm', [SalesOrderPolicy::class, 'confirm']);
        Gate::define('sales.override', [SalesOrderPolicy::class, 'override']);
        Gate::define('delivery.view', [DeliveryPolicy::class, 'view']);
        Gate::define('delivery.manage', [DeliveryPolicy::class, 'manage']);
        Gate::define('delivery.dispatch', [DeliveryPolicy::class, 'dispatch']);
        Gate::define('delivery.override', [DeliveryPolicy::class, 'override']);
        Gate::define('finance.view', [FinancePolicy::class, 'view']);
        Gate::define('finance.manage', [FinancePolicy::class, 'manage']);
        Gate::define('finance.pay', [FinancePolicy::class, 'pay']);
        Gate::define('finance.override', [FinancePolicy::class, 'override']);
    }
}
