<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Material;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BOMApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bom_crud_versions_items_calculation_and_audit_are_supported(): void
    {
        $user = $this->administrator();
        $this->seed(MasterDataSeeder::class);
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $material = Material::query()->where('code', 'FAB-COT-001')->firstOrFail();
        $unit = Unit::query()->where('code', 'KG')->firstOrFail();
        $secondMaterial = Material::query()->create([
            'material_category_id' => $material->material_category_id,
            'unit_id' => $unit->id,
            'code' => 'BUTTON-001',
            'name' => 'Button',
            'material_type' => 'Trim',
            'status' => 'active',
        ]);
        $token = $user->createToken('test-bom-crud', ['bom.view', 'bom.manage'])->plainTextToken;

        $createResponse = $this->withToken($token)->postJson('/api/boms', [
            'product_id' => $product->id,
            'code' => 'BOM-TEE-001',
            'name' => 'Classic tee engineering BOM',
            'description' => 'Test BOM',
            'effective_from' => '2026-01-01',
            'version_notes' => 'Initial draft.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.versions.0.version_number', 1)
            ->assertJsonPath('data.versions.0.status', 'draft');
        $bomId = $createResponse->json('data.id');
        $versionId = $createResponse->json('data.versions.0.id');
        $this->assertDatabaseHas('audit_logs', ['module' => 'boms', 'record_id' => $bomId, 'action' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'bom_versions', 'record_id' => $versionId, 'action' => 'version_created']);

        $itemResponse = $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/items", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 1.5,
            'wastage_percentage' => 5,
            'line_number' => 1,
            'notes' => 'Body fabric.',
        ]);
        $itemResponse->assertCreated()->assertJsonPath('data.quantity', 1.5);
        $itemId = $itemResponse->json('data.id');

        $secondItemResponse = $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/items", [
            'material_id' => $secondMaterial->id,
            'unit_id' => $unit->id,
            'quantity' => 3,
            'wastage_percentage' => 0,
            'line_number' => 2,
        ]);
        $secondItemResponse->assertCreated();
        $secondItemId = $secondItemResponse->json('data.id');

        $this->withToken($token)->putJson("/api/boms/{$bomId}/versions/{$versionId}/items/{$itemId}", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 1.5,
            'wastage_percentage' => 5,
            'line_number' => 1,
            'notes' => 'Updated body fabric.',
        ])->assertOk()->assertJsonPath('data.notes', 'Updated body fabric.');

        $calculationResponse = $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/calculate", [
            'order_quantity' => 100,
        ]);
        $calculationResponse->assertOk()
            ->assertJsonPath('data.lines.0.bom_quantity', 1.5)
            ->assertJsonPath('data.lines.0.wastage_percentage', 5)
            ->assertJsonPath('data.lines.0.required_quantity', 157.5);

        $this->withToken($token)->deleteJson("/api/boms/{$bomId}/versions/{$versionId}/items/{$secondItemId}")
            ->assertOk()
            ->assertJsonPath('message', 'BOM item deleted successfully.');
        $this->assertDatabaseMissing('bom_items', ['id' => $secondItemId]);

        $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('bom_headers', ['id' => $bomId, 'status' => 'active']);
        $this->assertDatabaseHas('bom_versions', ['id' => $versionId, 'status' => 'active']);

        $versionTwoResponse = $this->withToken($token)->postJson("/api/boms/{$bomId}/versions", [
            'effective_from' => '2026-06-01',
            'notes' => 'Second engineering revision.',
        ]);
        $versionTwoResponse->assertCreated()->assertJsonPath('data.version_number', 2);
        $versionTwoId = $versionTwoResponse->json('data.id');

        $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionTwoId}/items", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 1.6,
            'wastage_percentage' => 4,
            'line_number' => 1,
        ])->assertCreated();

        $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionTwoId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('bom_versions', ['id' => $versionId, 'status' => 'inactive']);
        $this->assertDatabaseHas('bom_versions', ['id' => $versionTwoId, 'status' => 'active']);

        $this->withToken($token)->postJson("/api/boms/{$bomId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('bom_versions', ['id' => $versionTwoId, 'status' => 'inactive']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'bom_items', 'record_id' => $itemId, 'action' => 'item_updated']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'bom_items', 'record_id' => $secondItemId, 'action' => 'item_deleted']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'bom_versions', 'record_id' => $versionTwoId, 'action' => 'version_activated']);
    }

    public function test_bom_authorization_validation_duplicate_lines_and_active_version_rules(): void
    {
        $user = $this->administrator();
        $this->seed(MasterDataSeeder::class);
        $product = Product::query()->where('code', 'TEE-CLASSIC')->firstOrFail();
        $material = Material::query()->where('code', 'FAB-COT-001')->firstOrFail();
        $unit = Unit::query()->where('code', 'KG')->firstOrFail();
        $dashboardOnlyUser = User::factory()->create();
        $dashboardToken = $dashboardOnlyUser->createToken('test-dashboard-only', ['dashboard.view'])->plainTextToken;

        $this->withToken($dashboardToken)->getJson('/api/boms')->assertForbidden();
        app('auth')->forgetGuards();

        $token = $user->createToken('test-bom-validation', ['bom.view', 'bom.manage'])->plainTextToken;
        $this->withToken($token)->postJson('/api/boms', [
            'product_id' => $product->id,
            'code' => 'BOM-INVALID',
            'name' => 'Invalid BOM',
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        $invalidCreate = $this->withToken($token)->postJson('/api/boms', [
            'product_id' => $product->id,
            'code' => 'BOM-DUPLICATE-PRODUCT',
            'name' => 'Duplicate product BOM',
            'effective_from' => '2026-01-01',
        ]);
        $invalidCreate->assertUnprocessable()->assertJsonValidationErrors(['product_id']);
        $bomId = $this->withToken($token)->getJson('/api/boms')->json('data.0.id');
        $versionId = $this->withToken($token)->getJson("/api/boms/{$bomId}")->json('data.versions.0.id');

        $invalidItem = $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/items", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 0,
            'wastage_percentage' => 101,
        ]);
        $invalidItem->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'wastage_percentage']);

        $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/items", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 1,
            'wastage_percentage' => 0,
            'line_number' => 1,
        ])->assertCreated();

        $duplicateMaterial = $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/items", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 2,
            'wastage_percentage' => 0,
            'line_number' => 2,
        ]);
        $duplicateMaterial->assertUnprocessable()->assertJsonValidationErrors(['material_id']);

        $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/calculate", ['order_quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_quantity']);

        $this->withToken($token)->postJson("/api/boms/{$bomId}/versions/{$versionId}/activate")
            ->assertOk();
        $itemId = $this->withToken($token)->getJson("/api/boms/{$bomId}/versions/{$versionId}/items")->json('data.0.id');
        $this->withToken($token)->putJson("/api/boms/{$bomId}/versions/{$versionId}/items/{$itemId}", [
            'material_id' => $material->id,
            'unit_id' => $unit->id,
            'quantity' => 1.2,
            'wastage_percentage' => 2,
            'line_number' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['version']);
    }

    private function administrator(): User
    {
        $this->seed(AuthorizationSeeder::class);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'administrator')->firstOrFail();
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }
}
