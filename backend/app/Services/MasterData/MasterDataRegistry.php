<?php

declare(strict_types=1);

namespace App\Services\MasterData;

use App\Models\Buyer;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;

final class MasterDataRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            'buyers' => array_merge(self::partyDefinition(Buyer::class, 'Buyer', 'Buyers'), [
                'dependency_relations' => ['orders', 'salesOrders', 'invoices', 'payments'],
                'fields' => array_merge(
                    ['code' => ['label' => 'Code', 'type' => 'text', 'required' => true]],
                    ['name' => ['label' => 'Buyer Name', 'type' => 'text', 'required' => true]],
                    ['company' => ['label' => 'Company', 'type' => 'text']],
                    ['contact_name' => ['label' => 'Contact Person', 'type' => 'text']],
                    ['email' => ['label' => 'Email', 'type' => 'email']],
                    ['phone' => ['label' => 'Phone', 'type' => 'text']],
                    ['country' => ['label' => 'Country', 'type' => 'text']],
                    ['address' => ['label' => 'Address', 'type' => 'textarea']],
                    ['status' => ['label' => 'Status', 'type' => 'status', 'required' => true]],
                    ['notes' => ['label' => 'Notes', 'type' => 'textarea']],
                ),
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'company' => ['nullable', 'string', 'max:255'],
                    'contact_name' => ['nullable', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:50'],
                    'country' => ['nullable', 'string', 'max:100'],
                    'address' => ['nullable', 'string'],
                    'status' => ['required', 'in:active,inactive'],
                    'notes' => ['nullable', 'string'],
                ],
            ]),
            'customers' => array_merge(self::partyDefinition(Customer::class, 'Customer', 'Customers'), [
                'dependency_relations' => ['salesOrders', 'invoices', 'payments'],
            ]),
            'suppliers' => array_merge(self::partyDefinition(Supplier::class, 'Supplier', 'Suppliers'), [
                'relations' => ['materials'],
                'dependency_relations' => ['materials'],
            ]),
            'categories' => [
                'model' => Category::class,
                'label' => 'Categories',
                'singular' => 'Category',
                'searchable' => ['code', 'name', 'status'],
                'sortable' => ['id', 'code', 'name', 'status', 'created_at'],
                'filterable' => ['status'],
                'relations' => ['parent', 'children'],
                'dependency_relations' => ['children', 'products'],
                'fields' => [
                    'parent_id' => ['label' => 'Parent category', 'type' => 'relation', 'nullable' => true, 'relation' => 'categories'],
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                ],
                'rules' => [
                    'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'description' => ['nullable', 'string'],
                    'status' => ['required', 'in:active,inactive'],
                ],
            ],
            'products' => [
                'model' => Product::class,
                'label' => 'Products',
                'singular' => 'Product',
                'searchable' => ['code', 'name', 'product_type', 'status'],
                'sortable' => ['id', 'code', 'name', 'standard_price', 'status', 'created_at'],
                'filterable' => ['status', 'category_id'],
                'relations' => ['category', 'unit', 'variants', 'boms'],
                'dependency_relations' => ['variants', 'boms'],
                'fields' => [
                    'category_id' => ['label' => 'Category', 'type' => 'relation', 'required' => true, 'relation' => 'categories'],
                    'unit_id' => ['label' => 'Unit', 'type' => 'relation', 'required' => true, 'relation' => 'units'],
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'product_type' => ['label' => 'Product type', 'type' => 'text'],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                    'standard_cost' => ['label' => 'Standard cost', 'type' => 'number', 'required' => true],
                    'standard_price' => ['label' => 'Standard price', 'type' => 'number', 'required' => true],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                ],
                'rules' => [
                    'category_id' => ['required', 'integer', 'exists:categories,id'],
                    'unit_id' => ['required', 'integer', 'exists:units,id'],
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'product_type' => ['nullable', 'string', 'max:50'],
                    'description' => ['nullable', 'string'],
                    'standard_cost' => ['required', 'numeric', 'min:0'],
                    'standard_price' => ['required', 'numeric', 'min:0'],
                    'status' => ['required', 'in:active,inactive'],
                ],
            ],
            'product-variants' => [
                'model' => ProductVariant::class,
                'label' => 'Product Variants',
                'singular' => 'Product Variant',
                'searchable' => ['sku', 'variant_name', 'status'],
                'sortable' => ['id', 'sku', 'variant_name', 'selling_price', 'status', 'created_at'],
                'filterable' => ['status', 'product_id', 'size_id', 'color_id'],
                'relations' => ['product', 'size', 'color'],
                'dependency_relations' => [],
                'fields' => [
                    'product_id' => ['label' => 'Product', 'type' => 'relation', 'required' => true, 'relation' => 'products'],
                    'size_id' => ['label' => 'Size', 'type' => 'relation', 'nullable' => true, 'relation' => 'sizes'],
                    'color_id' => ['label' => 'Color', 'type' => 'relation', 'nullable' => true, 'relation' => 'colors'],
                    'sku' => ['label' => 'SKU', 'type' => 'text', 'required' => true],
                    'variant_name' => ['label' => 'Variant name', 'type' => 'text'],
                    'cost_price' => ['label' => 'Cost price', 'type' => 'number'],
                    'selling_price' => ['label' => 'Selling price', 'type' => 'number'],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                ],
                'rules' => [
                    'product_id' => ['required', 'integer', 'exists:products,id'],
                    'size_id' => ['nullable', 'integer', 'exists:sizes,id'],
                    'color_id' => ['nullable', 'integer', 'exists:colors,id'],
                    'sku' => ['required', 'string', 'max:80'],
                    'variant_name' => ['nullable', 'string', 'max:255'],
                    'cost_price' => ['nullable', 'numeric', 'min:0'],
                    'selling_price' => ['nullable', 'numeric', 'min:0'],
                    'status' => ['required', 'in:active,inactive'],
                    'description' => ['nullable', 'string'],
                ],
            ],
            'sizes' => self::dimensionDefinition(Size::class, 'Size', 'Sizes', [
                'sort_order' => ['label' => 'Sort order', 'type' => 'number', 'required' => true],
            ], [
                'sort_order' => ['required', 'integer', 'min:0'],
            ], ['productVariants']),
            'colors' => self::dimensionDefinition(Color::class, 'Color', 'Colors', [
                'hex_code' => ['label' => 'Hex color', 'type' => 'text'],
            ], [
                'hex_code' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ], ['productVariants']),
            'materials' => [
                'model' => Material::class,
                'label' => 'Materials',
                'singular' => 'Material',
                'searchable' => ['code', 'name', 'material_type', 'status'],
                'sortable' => ['id', 'code', 'name', 'material_type', 'status', 'created_at'],
                'filterable' => ['status', 'material_category_id', 'unit_id'],
                'relations' => ['materialCategory', 'unit', 'suppliers', 'bomItems'],
                'dependency_relations' => ['suppliers', 'bomItems'],
                'fields' => [
                    'material_category_id' => ['label' => 'Material category', 'type' => 'relation', 'required' => true, 'relation' => 'material-categories'],
                    'unit_id' => ['label' => 'Unit', 'type' => 'relation', 'required' => true, 'relation' => 'units'],
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'material_type' => ['label' => 'Material type', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                ],
                'rules' => [
                    'material_category_id' => ['required', 'integer', 'exists:material_categories,id'],
                    'unit_id' => ['required', 'integer', 'exists:units,id'],
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'material_type' => ['nullable', 'string', 'max:50'],
                    'status' => ['required', 'in:active,inactive'],
                    'description' => ['nullable', 'string'],
                ],
            ],
            'material-categories' => [
                'model' => MaterialCategory::class,
                'label' => 'Material Categories',
                'singular' => 'Material Category',
                'searchable' => ['code', 'name', 'status'],
                'sortable' => ['id', 'code', 'name', 'status', 'created_at'],
                'filterable' => ['status'],
                'relations' => ['materials'],
                'dependency_relations' => ['materials'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                ],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'description' => ['nullable', 'string'],
                    'status' => ['required', 'in:active,inactive'],
                ],
            ],
            'units' => [
                'model' => Unit::class,
                'label' => 'Units',
                'singular' => 'Unit',
                'searchable' => ['code', 'name', 'symbol', 'status'],
                'sortable' => ['id', 'code', 'name', 'decimal_places', 'status', 'created_at'],
                'filterable' => ['status'],
                'relations' => ['materials', 'products', 'bomItems'],
                'dependency_relations' => ['materials', 'products', 'bomItems'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'symbol' => ['label' => 'Symbol', 'type' => 'text'],
                    'decimal_places' => ['label' => 'Decimal places', 'type' => 'number', 'required' => true],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                ],
                'rules' => [
                    'code' => ['required', 'string', 'max:30'],
                    'name' => ['required', 'string', 'max:255'],
                    'symbol' => ['nullable', 'string', 'max:20'],
                    'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
                    'status' => ['required', 'in:active,inactive'],
                    'description' => ['nullable', 'string'],
                ],
            ],
            'warehouses' => [
                'model' => Warehouse::class,
                'label' => 'Warehouses',
                'singular' => 'Warehouse',
                'searchable' => ['code', 'name', 'country', 'status'],
                'sortable' => ['id', 'code', 'name', 'country', 'status', 'created_at'],
                'filterable' => ['status', 'country'],
                'relations' => ['locations'],
                'dependency_relations' => ['locations'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'contact_name' => ['label' => 'Contact name', 'type' => 'text'],
                    'phone' => ['label' => 'Phone', 'type' => 'text'],
                    'country' => ['label' => 'Country', 'type' => 'text'],
                    'address' => ['label' => 'Address', 'type' => 'textarea'],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                    'notes' => ['label' => 'Notes', 'type' => 'textarea'],
                ],
                'rules' => [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'contact_name' => ['nullable', 'string', 'max:255'],
                    'phone' => ['nullable', 'string', 'max:50'],
                    'country' => ['nullable', 'string', 'max:100'],
                    'address' => ['nullable', 'string'],
                    'status' => ['required', 'in:active,inactive'],
                    'notes' => ['nullable', 'string'],
                ],
            ],
            'warehouse-locations' => [
                'model' => WarehouseLocation::class,
                'label' => 'Warehouse Locations',
                'singular' => 'Warehouse Location',
                'searchable' => ['code', 'name', 'location_type', 'status'],
                'sortable' => ['id', 'code', 'name', 'location_type', 'status', 'created_at'],
                'filterable' => ['status', 'warehouse_id', 'location_type'],
                'relations' => ['warehouse'],
                'dependency_relations' => [],
                'fields' => [
                    'warehouse_id' => ['label' => 'Warehouse', 'type' => 'relation', 'required' => true, 'relation' => 'warehouses'],
                    'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                    'location_type' => ['label' => 'Location type', 'type' => 'text', 'required' => true],
                    'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                    'description' => ['label' => 'Description', 'type' => 'textarea'],
                ],
                'rules' => [
                    'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'location_type' => ['required', 'string', 'max:30'],
                    'status' => ['required', 'in:active,inactive'],
                    'description' => ['nullable', 'string'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $resource): array
    {
        $definitions = self::definitions();

        if (! isset($definitions[$resource])) {
            abort(404, "Unknown master-data resource [{$resource}].");
        }

        return $definitions[$resource];
    }

    /**
     * @return array<string, mixed>
     */
    private static function partyDefinition(string $model, string $singular, string $label): array
    {
        return [
            'model' => $model,
            'label' => $label,
            'singular' => $singular,
            'searchable' => ['code', 'name', 'email', 'country', 'status'],
            'sortable' => ['id', 'code', 'name', 'country', 'status', 'created_at'],
            'filterable' => ['status', 'country'],
            'relations' => [],
            'dependency_relations' => [],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'contact_name' => ['label' => 'Contact name', 'type' => 'text'],
                'email' => ['label' => 'Email', 'type' => 'email'],
                'phone' => ['label' => 'Phone', 'type' => 'text'],
                'country' => ['label' => 'Country', 'type' => 'text'],
                'address' => ['label' => 'Address', 'type' => 'textarea'],
                'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                'notes' => ['label' => 'Notes', 'type' => 'textarea'],
            ],
            'rules' => [
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'contact_name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'country' => ['nullable', 'string', 'max:100'],
                'address' => ['nullable', 'string'],
                'status' => ['required', 'in:active,inactive'],
                'notes' => ['nullable', 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $extraFields
     * @param  array<string, array<int, string>>  $extraRules
     * @param  array<int, string>  $dependencies
     * @return array<string, mixed>
     */
    private static function dimensionDefinition(string $model, string $singular, string $label, array $extraFields, array $extraRules, array $dependencies): array
    {
        return [
            'model' => $model,
            'label' => $label,
            'singular' => $singular,
            'searchable' => ['code', 'name', 'status'],
            'sortable' => ['id', 'code', 'name', 'sort_order', 'status', 'created_at'],
            'filterable' => ['status'],
            'relations' => $dependencies,
            'dependency_relations' => $dependencies,
            'fields' => array_merge([
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true],
            ], $extraFields, [
                'status' => ['label' => 'Status', 'type' => 'status', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
            ]),
            'rules' => array_merge([
                'code' => ['required', 'string', 'max:30'],
                'name' => ['required', 'string', 'max:255'],
            ], $extraRules, [
                'status' => ['required', 'in:active,inactive'],
                'description' => ['nullable', 'string'],
            ]),
        ];
    }
}
