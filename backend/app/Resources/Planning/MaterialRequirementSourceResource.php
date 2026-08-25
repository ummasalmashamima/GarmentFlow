<?php

declare(strict_types=1);

namespace App\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialRequirementSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supply_plan_id' => $this->supply_plan_id,
            'material_id' => $this->material_id,
            'unit_id' => $this->unit_id,
            'product' => $this->whenLoaded('product', fn (): array => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'product_variant' => $this->whenLoaded('productVariant', fn (): ?array => $this->productVariant === null ? null : [
                'id' => $this->productVariant->id,
                'code' => $this->productVariant->sku,
                'name' => $this->productVariant->variant_name,
            ]),
            'bom_version' => $this->whenLoaded('bomVersion', fn (): array => [
                'id' => $this->bomVersion->id,
                'version_number' => $this->bomVersion->version_number,
            ]),
            'bom_item' => $this->whenLoaded('bomItem', fn (): array => [
                'id' => $this->bomItem->id,
                'line_number' => $this->bomItem->line_number,
            ]),
            'material' => $this->whenLoaded('material', fn (): array => [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
            ]),
            'unit' => $this->whenLoaded('unit', fn (): array => [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'name' => $this->unit->name,
                'symbol' => $this->unit->symbol,
            ]),
            'planned_product_quantity' => (string) $this->planned_product_quantity,
            'bom_quantity' => (string) $this->bom_quantity,
            'wastage_percentage' => (string) $this->wastage_percentage,
            'gross_quantity' => (string) $this->gross_quantity,
        ];
    }
}
