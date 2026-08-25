<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\BomVersion;
use App\Models\Buyer;
use App\Models\BuyerOrder;
use App\Models\BuyerOrderItem;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SupplyPlan;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\BOMSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanningApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_average_forecast_preview_crud_and_activation_are_supported(): void
    {
        [$user, $product, $variant] = $this->fixtures();
        $token = $this->token($user, ['planning.view', 'planning.manage']);
        $this->confirmedOrder($user, $product, $variant, 100, '2026-01-15');

        $payload = [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'method' => 'historical_average',
            'lookback_periods' => 2,
            'forecast_date' => '2026-03-01',
        ];

        $this->withToken($token)->postJson('/api/planning/forecasts/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.forecast_quantity', 50)
            ->assertJsonPath('data.historical_periods.0.demand_quantity', 0)
            ->assertJsonPath('data.historical_periods.1.demand_quantity', 100)
            ->assertJsonPath('data.product_variant.code', $variant->sku);

        $forecastId = $this->withToken($token)->postJson('/api/planning/forecasts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.forecast_quantity', '50.0000')
            ->assertJsonPath('data.calculation_snapshot.forecast_quantity', 50)
            ->json('data.id');

        $this->withToken($token)->postJson('/api/planning/forecasts', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_start']);

        $this->withToken($token)->postJson("/api/planning/forecasts/{$forecastId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withToken($token)->putJson("/api/planning/forecasts/{$forecastId}", [...$payload, 'method' => 'manual', 'forecast_quantity' => 60])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('demand_forecasts', [
            'id' => $forecastId,
            'method' => 'historical_average',
            'status' => 'active',
            'forecast_quantity' => '50.0000',
        ]);
    }

    public function test_forecast_validation_and_planning_permissions_are_enforced(): void
    {
        [$user, $product, $variant] = $this->fixtures();
        $viewToken = $this->token($user, ['planning.view']);
        $manageToken = $this->token($user, ['planning.view', 'planning.manage']);
        $dashboardToken = $this->token($user, ['dashboard.view']);

        $payload = [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-02',
            'period_end' => '2026-03-31',
            'method' => 'manual',
        ];

        $this->withToken($dashboardToken)->getJson('/api/planning/forecasts')->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->getJson('/api/planning/forecasts')->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($viewToken)->postJson('/api/planning/forecasts', [...$payload, 'forecast_quantity' => 10])
            ->assertForbidden();
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson('/api/planning/forecasts', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['forecast_quantity']);
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson('/api/planning/forecasts', [...$payload, 'forecast_quantity' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period_start']);
        app('auth')->forgetGuards();
        $this->withToken($manageToken)->postJson('/api/planning/forecasts', [...$payload, 'period_start' => '2026-03-01', 'forecast_quantity' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['forecast_quantity']);
    }

    public function test_supply_plan_combines_confirmed_orders_and_active_forecasts(): void
    {
        [$user, $product, $variant] = $this->fixtures();
        $token = $this->token($user, ['planning.view', 'planning.manage']);
        $this->confirmedOrder($user, $product, $variant, 100, '2026-03-15');

        $forecastPayload = [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'method' => 'manual',
            'forecast_quantity' => 40,
        ];
        $forecastId = $this->withToken($token)->postJson('/api/planning/forecasts', $forecastPayload)
            ->assertCreated()->json('data.id');
        $this->withToken($token)->postJson("/api/planning/forecasts/{$forecastId}/activate")->assertOk();

        $supplyPayload = [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
        ];

        $this->withToken($token)->postJson('/api/planning/supply-plans/preview', $supplyPayload)
            ->assertOk()
            ->assertJsonPath('data.confirmed_order_quantity', 100)
            ->assertJsonPath('data.forecast_quantity', 40)
            ->assertJsonPath('data.required_quantity', 140)
            ->assertJsonPath('data.planned_production_quantity', 140)
            ->assertJsonPath('data.status', 'pending_inventory');
        $this->withToken($token)->postJson('/api/planning/supply-plans/preview', [...$supplyPayload, 'product_variant_id' => null])
            ->assertOk()
            ->assertJsonPath('data.forecast_quantity', 40)
            ->assertJsonPath('data.required_quantity', 140);

        $planId = $this->withToken($token)->postJson('/api/planning/supply-plans/generate', [...$supplyPayload, 'available_quantity' => 20])
            ->assertOk()
            ->assertJsonPath('data.0.required_quantity', '140.0000')
            ->assertJsonPath('data.0.available_quantity', '20.0000')
            ->assertJsonPath('data.0.planned_production_quantity', '120.0000')
            ->assertJsonPath('data.0.status', 'calculated')
            ->json('data.0.id');

        $this->withToken($token)->getJson("/api/planning/supply-plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data.product_variant.code', $variant->sku);
        $this->assertDatabaseHas('supply_plans', ['id' => $planId, 'required_quantity' => '140.0000']);
    }

    public function test_mrp_reuses_active_bom_wastage_aggregates_materials_and_calculates_net_requirement(): void
    {
        [$user, $product, $variant] = $this->fixtures();
        $token = $this->token($user, ['planning.view', 'planning.manage']);
        $material = Material::query()->where('code', 'FAB-COT-001')->firstOrFail();
        $unit = Unit::query()->where('code', 'KG')->firstOrFail();

        $secondProduct = Product::query()->create([
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'code' => 'PLN-HOOD-001',
            'name' => 'Planning test hoodie',
            'product_type' => 'Finished garment',
            'standard_cost' => 8,
            'standard_price' => 20,
            'status' => 'active',
        ]);
        $secondVariant = ProductVariant::query()->create([
            'product_id' => $secondProduct->id,
            'size_id' => $variant->size_id,
            'color_id' => $variant->color_id,
            'sku' => 'PLN-HOOD-001-M-NAVY',
            'variant_name' => 'Planning hoodie / Medium / Navy',
            'cost_price' => 8,
            'selling_price' => 20,
            'status' => 'active',
        ]);
        $bom = BomHeader::query()->create([
            'product_id' => $secondProduct->id,
            'code' => 'BOM-PLN-HOOD-001',
            'name' => 'Planning hoodie BOM',
            'status' => 'active',
        ]);
        $version = BomVersion::query()->create([
            'bom_header_id' => $bom->id,
            'version_number' => 1,
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);
        BomItem::query()->create([
            'bom_version_id' => $version->id,
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 2,
            'wastage_percentage' => 0,
            'line_number' => 1,
        ]);

        $firstPlan = SupplyPlan::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'confirmed_order_quantity' => 100,
            'forecast_quantity' => 0,
            'required_quantity' => 100,
            'available_quantity' => null,
            'planned_production_quantity' => 100,
            'status' => 'pending_inventory',
            'created_by' => $user->id,
        ]);
        $secondPlan = SupplyPlan::query()->create([
            'product_id' => $secondProduct->id,
            'product_variant_id' => $secondVariant->id,
            'period_type' => 'monthly',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'confirmed_order_quantity' => 50,
            'forecast_quantity' => 0,
            'required_quantity' => 50,
            'available_quantity' => null,
            'planned_production_quantity' => 50,
            'status' => 'pending_inventory',
            'created_by' => $user->id,
        ]);

        $previewPayload = [
            'supply_plan_ids' => [$firstPlan->id, $secondPlan->id],
            'availability' => [[
                'material_id' => $material->id,
                'unit_id' => $unit->id,
                'available_quantity' => 7.5,
                'allocated_quantity' => 10,
            ]],
        ];
        $this->withToken($token)->postJson('/api/planning/material-requirements/preview', $previewPayload)
            ->assertOk()
            ->assertJsonPath('data.total_gross_quantity', 257.5)
            ->assertJsonPath('data.total_net_quantity', 240)
            ->assertJsonPath('data.lines.0.gross_quantity', 257.5)
            ->assertJsonPath('data.lines.0.net_quantity', 240)
            ->assertJsonPath('data.lines.0.status', 'calculated')
            ->assertJsonCount(2, 'data.lines.0.sources');

        $runId = $this->withToken($token)->postJson('/api/planning/material-requirements/generate', [...$previewPayload, 'planning_date' => '2026-03-01'])
            ->assertCreated()
            ->assertJsonPath('data.inventory_data_available', true)
            ->assertJsonPath('data.total_gross_quantity', '257.5000')
            ->assertJsonPath('data.total_net_quantity', '240.0000')
            ->assertJsonPath('data.material_requirements.0.gross_quantity', '257.5000')
            ->assertJsonPath('data.material_requirements.0.net_quantity', '240.0000')
            ->assertJsonCount(2, 'data.material_requirements.0.sources')
            ->assertJsonPath('data.material_requirements.0.sources.0.material_id', $material->id)
            ->assertJsonPath('data.material_requirements.0.sources.0.unit_id', $unit->id)
            ->assertJsonPath('data.material_requirements.0.sources.0.material.code', $material->code)
            ->assertJsonPath('data.material_requirements.0.sources.0.unit.code', $unit->code)
            ->json('data.id');

        $runDetail = $this->withToken($token)->getJson("/api/planning/material-requirements/{$runId}")
            ->assertOk()
            ->assertJsonCount(1, 'data.material_requirements');
        $this->assertStringStartsWith('MRP-', $runDetail->json('data.run_number'));
        $this->assertDatabaseHas('material_requirements', ['mrp_run_id' => $runId, 'gross_quantity' => '257.5000']);
        $this->assertDatabaseCount('material_requirement_sources', 2);
    }

    /**
     * @return array{0: User, 1: Product, 2: ProductVariant}
     */
    private function fixtures(): array
    {
        $this->seed([AuthorizationSeeder::class, MasterDataSeeder::class, BOMSeeder::class]);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->attach($role);
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $variant = ProductVariant::query()->where('sku', 'TEE-CLASSIC-M-NAVY')->firstOrFail();

        return [$user->load('roles.permissions'), $product, $variant];
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function token(User $user, array $abilities): string
    {
        return $user->createToken('planning-test', $abilities)->plainTextToken;
    }

    private function confirmedOrder(User $user, Product $product, ProductVariant $variant, int $quantity, string $deliveryDate): BuyerOrder
    {
        $buyer = Buyer::query()->where('code', 'BUY-001')->firstOrFail();
        $order = BuyerOrder::query()->create([
            'buyer_id' => $buyer->id,
            'order_number' => 'BO-PLN-'.str()->upper(str()->random(10)),
            'order_date' => '2026-01-01',
            'delivery_date' => $deliveryDate,
            'status' => 'confirmed',
            'total_quantity' => $quantity,
            'total_amount' => $quantity * 10,
            'created_by' => $user->id,
        ]);
        BuyerOrderItem::query()->create([
            'buyer_order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => 10,
            'item_total' => $quantity * 10,
        ]);

        return $order;
    }
}
