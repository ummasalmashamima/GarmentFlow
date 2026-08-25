<?php

declare(strict_types=1);

namespace App\Resources\Production;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'production_plan' => $this->whenLoaded('productionPlan', fn (): array => [
                'id' => $this->productionPlan->id,
                'plan_number' => $this->productionPlan->plan_number,
                'status' => $this->productionPlan->status,
            ]),
            'buyer_order' => $this->whenLoaded('buyerOrder', fn (): ?array => $this->buyerOrder === null ? null : [
                'id' => $this->buyerOrder->id,
                'order_number' => $this->buyerOrder->order_number,
                'status' => $this->buyerOrder->status,
            ]),
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
                'status' => $this->bomVersion->status,
            ]),
            'planned_quantity' => (string) $this->planned_quantity,
            'completed_quantity' => (string) $this->completed_quantity,
            'rejected_quantity' => (string) $this->rejected_quantity,
            'remaining_quantity' => number_format(max((float) $this->planned_quantity - (float) $this->completed_quantity, 0), 4, '.', ''),
            'progress_percentage' => number_format((float) $this->planned_quantity > 0 ? ((float) $this->completed_quantity / (float) $this->planned_quantity) * 100 : 0, 4, '.', ''),
            'start_date' => $this->start_date?->toDateString(),
            'expected_completion_date' => $this->expected_completion_date?->toDateString(),
            'completed_date' => $this->completed_date?->toDateString(),
            'issue_warehouse' => $this->whenLoaded('issueWarehouse', fn (): array => [
                'id' => $this->issueWarehouse->id,
                'code' => $this->issueWarehouse->code,
                'name' => $this->issueWarehouse->name,
            ]),
            'issue_warehouse_location' => $this->whenLoaded('issueWarehouseLocation', fn (): ?array => $this->issueWarehouseLocation === null ? null : [
                'id' => $this->issueWarehouseLocation->id,
                'code' => $this->issueWarehouseLocation->code,
                'name' => $this->issueWarehouseLocation->name,
            ]),
            'status' => $this->status,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'completer' => $this->whenLoaded('completer', fn (): ?array => $this->completer === null ? null : [
                'id' => $this->completer->id,
                'name' => $this->completer->name,
            ]),
            'items' => ProductionOrderItemResource::collection($this->whenLoaded('items')),
            'progress' => ProductionProgressResource::collection($this->whenLoaded('progress')),
            'consumptions' => MaterialConsumptionResource::collection($this->whenLoaded('consumptions')),
            'finished_goods' => $this->whenLoaded('finishedGoods', fn (): ?array => $this->finishedGoods === null ? null : (new FinishedGoodsResource($this->finishedGoods))->toArray($request)),
            'remarks' => $this->remarks,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
