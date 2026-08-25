<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_list_supports_search_filter_pagination_and_sorting(): void
    {
        $user = $this->administrator();
        Category::query()->create(['code' => 'APPAREL', 'name' => 'Apparel', 'status' => 'active']);
        Category::query()->create(['code' => 'HOME', 'name' => 'Home goods', 'status' => 'inactive']);

        $token = $user->createToken('test-master-data', ['master-data.view', 'master-data.manage'])->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/master-data/categories?search=app&status=active&sort=name&direction=asc&per_page=1');

        $response->assertOk()
            ->assertJsonPath('data.0.code', 'APPAREL')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_master_data_requires_permission_and_validates_payloads(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-dashboard-only', ['dashboard.view'])->plainTextToken;

        $this->withToken($token)->getJson('/api/master-data/categories')->assertForbidden();
        app('auth')->forgetGuards();

        $administrator = $this->administrator();
        $adminToken = $administrator->createToken('test-master-data-validation', ['master-data.view', 'master-data.manage'])->plainTextToken;

        $this->withToken($adminToken)
            ->postJson('/api/master-data/categories', ['code' => '', 'status' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'status']);
    }

    public function test_master_data_crud_is_audited_and_referenced_records_are_deactivated(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('test-master-data-crud', ['master-data.view', 'master-data.manage'])->plainTextToken;

        $createResponse = $this->withToken($token)->postJson('/api/master-data/categories', [
            'code' => 'TOPS',
            'name' => 'Tops',
            'status' => 'active',
        ]);

        $createResponse->assertCreated()->assertJsonPath('data.code', 'TOPS');
        $categoryId = $createResponse->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['module' => 'categories', 'record_id' => $categoryId, 'action' => 'created']);

        $this->withToken($token)->putJson("/api/master-data/categories/{$categoryId}", [
            'code' => 'TOPS',
            'name' => 'Tops and tees',
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.name', 'Tops and tees');
        $this->assertDatabaseHas('audit_logs', ['module' => 'categories', 'record_id' => $categoryId, 'action' => 'updated']);

        Unit::query()->create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'decimal_places' => 0, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->withToken($token)->deleteJson("/api/master-data/categories/{$categoryId}")->assertOk()->assertJsonPath('message', 'Record deleted successfully.');
        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
    }

    public function test_referenced_master_data_is_deactivated_instead_of_deleted(): void
    {
        $user = $this->administrator();
        $token = $user->createToken('test-master-data-dependency', ['master-data.view', 'master-data.manage'])->plainTextToken;
        $category = Category::query()->create(['code' => 'APPAREL', 'name' => 'Apparel', 'status' => 'active']);
        $unit = Unit::query()->create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs', 'decimal_places' => 0, 'status' => 'active']);
        Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'TEE-001',
            'name' => 'Classic tee',
            'standard_cost' => 5,
            'standard_price' => 12,
            'status' => 'active',
        ]);

        $this->withToken($token)->deleteJson("/api/master-data/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('message', 'Record deactivated because it is referenced by other records.');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'status' => 'inactive', 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'categories', 'record_id' => $category->id, 'action' => 'deactivated']);
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
