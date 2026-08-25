<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\MaterialRequirement;
use App\Models\ProcurementStatusHistory;
use App\Models\PurchaseApproval;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseRequisitionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProcurementWorkflow $workflow,
        private readonly ProcurementReferenceService $referenceService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = PurchaseRequisition::query()->with(['requester', 'department']);
        if (($filters['search'] ?? null) !== null && $filters['search'] !== '') {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('requisition_number', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('requester', fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
        foreach (['status', 'priority', 'department_id'] as $field) {
            if (($filters[$field] ?? null) !== null && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        foreach ([
            'request_date_from' => ['request_date', '>='],
            'request_date_to' => ['request_date', '<='],
            'required_date_from' => ['required_date', '>='],
            'required_date_to' => ['required_date', '<='],
        ] as $filter => [$column, $operator]) {
            if (($filters[$filter] ?? null) !== null && $filters[$filter] !== '') {
                $query->where($column, $operator, $filters[$filter]);
            }
        }
        $sort = in_array(($filters['sort'] ?? 'id'), ['id', 'requisition_number', 'request_date', 'required_date', 'priority', 'status'], true)
            ? (string) ($filters['sort'] ?? 'id') : 'id';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();
    }

    public function find(PurchaseRequisition $requisition): PurchaseRequisition
    {
        return $requisition->load([
            'requester',
            'department',
            'items.material',
            'items.unit',
            'items.materialRequirement',
            'approvals.requester',
            'approvals.reviewer',
            'statusHistories.changer',
        ]);
    }

    public function create(array $attributes, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($attributes, $actor): PurchaseRequisition {
            $this->validateItems($attributes['items'] ?? []);
            $requisition = PurchaseRequisition::query()->create([
                'requisition_number' => $this->generateNumber(),
                'request_date' => $attributes['request_date'],
                'requested_by' => $actor->getKey(),
                'department_id' => $attributes['department_id'] ?? null,
                'source' => $attributes['source'] ?? null,
                'required_date' => $attributes['required_date'],
                'priority' => $attributes['priority'] ?? 'normal',
                'status' => ProcurementWorkflow::DRAFT,
                'remarks' => $attributes['remarks'] ?? null,
            ]);
            $this->replaceItems($requisition, $attributes['items'], $actor);
            $this->recordStatus($requisition, null, ProcurementWorkflow::DRAFT, $actor, 'Purchase Requisition created.');
            $this->auditLogService->record($actor, 'procurement-requisitions', $requisition, 'created', null, $requisition->attributesToArray());

            return $this->find($requisition->refresh());
        });
    }

    public function update(PurchaseRequisition $requisition, array $attributes, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $attributes, $actor): PurchaseRequisition {
            $this->assertDraft($requisition);
            $this->validateItems($attributes['items'] ?? []);
            $oldValues = $requisition->attributesToArray();
            $requisition->fill([
                'request_date' => $attributes['request_date'],
                'department_id' => $attributes['department_id'] ?? null,
                'source' => $attributes['source'] ?? null,
                'required_date' => $attributes['required_date'],
                'priority' => $attributes['priority'] ?? 'normal',
                'remarks' => $attributes['remarks'] ?? null,
            ])->save();
            $this->replaceItems($requisition, $attributes['items'], $actor);
            $this->auditLogService->record($actor, 'procurement-requisitions', $requisition, 'updated', $oldValues, $requisition->attributesToArray());

            return $this->find($requisition->refresh());
        });
    }

    public function submit(PurchaseRequisition $requisition, ?string $remarks, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $remarks, $actor): PurchaseRequisition {
            $this->assertDraft($requisition);
            $this->ensureIntegrity($requisition);
            $this->transition($requisition, ProcurementWorkflow::SUBMITTED, $remarks ?: 'Purchase Requisition submitted for approval.', $actor);
            $approval = $requisition->approvals()->create([
                'document_type' => PurchaseApproval::REQUISITION,
                'requested_by' => $actor->getKey(),
                'status' => 'pending',
                'remarks' => $remarks,
                'requested_at' => now(),
            ]);
            $this->auditLogService->record($actor, 'procurement-approvals', $approval, 'requested', null, $approval->attributesToArray());

            return $this->find($requisition->refresh());
        });
    }

    public function approve(PurchaseRequisition $requisition, ?string $remarks, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $remarks, $actor): PurchaseRequisition {
            if ($requisition->status !== ProcurementWorkflow::SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only submitted Purchase Requisitions can be approved.']);
            }
            $approval = $requisition->approvals()->where('status', 'pending')->latest('id')->first();
            if ($approval === null) {
                throw ValidationException::withMessages(['approval' => 'A pending Purchase Requisition approval is required.']);
            }
            $oldApproval = $approval->attributesToArray();
            $approval->forceFill(['status' => 'approved', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'remarks' => $remarks ?? $approval->remarks])->save();
            $this->auditLogService->record($actor, 'procurement-approvals', $approval, 'approved', $oldApproval, $approval->attributesToArray());
            $this->transition($requisition, ProcurementWorkflow::APPROVED, $remarks ?: 'Purchase Requisition approved.', $actor);

            return $this->find($requisition->refresh());
        });
    }

    public function reject(PurchaseRequisition $requisition, ?string $remarks, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $remarks, $actor): PurchaseRequisition {
            if ($requisition->status !== ProcurementWorkflow::SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only submitted Purchase Requisitions can be rejected.']);
            }
            $approval = $requisition->approvals()->where('status', 'pending')->latest('id')->first();
            if ($approval === null) {
                throw ValidationException::withMessages(['approval' => 'A pending Purchase Requisition approval is required.']);
            }
            $oldApproval = $approval->attributesToArray();
            $approval->forceFill(['status' => 'rejected', 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'remarks' => $remarks ?? $approval->remarks])->save();
            $this->auditLogService->record($actor, 'procurement-approvals', $approval, 'rejected', $oldApproval, $approval->attributesToArray());
            $this->transition($requisition, ProcurementWorkflow::REJECTED, $remarks ?: 'Purchase Requisition rejected.', $actor);

            return $this->find($requisition->refresh());
        });
    }

    public function convert(PurchaseRequisition $requisition, array $attributes, User $actor, PurchaseOrderService $purchaseOrderService): PurchaseOrder
    {
        if ($requisition->status !== ProcurementWorkflow::APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only approved Purchase Requisitions can be converted to a Purchase Order.']);
        }

        return $purchaseOrderService->createFromRequisition($requisition, $attributes, $actor);
    }

    public function history(PurchaseRequisition $requisition): array
    {
        return [
            'status_history' => $requisition->statusHistories()->with('changer')->get(),
            'approvals' => $requisition->approvals()->with(['requester', 'reviewer'])->latest('id')->get(),
            'audit_logs' => AuditLog::query()->where('module', 'procurement-requisitions')->where('record_id', $requisition->getKey())->latest('id')->get(),
        ];
    }

    private function assertDraft(PurchaseRequisition $requisition): void
    {
        if ($requisition->status !== ProcurementWorkflow::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft Purchase Requisitions can be edited.']);
        }
    }

    private function ensureIntegrity(PurchaseRequisition $requisition): void
    {
        $requisition->loadMissing('items');
        if ($requisition->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'A Purchase Requisition must contain at least one item.']);
        }
        if ($requisition->required_date->lt($requisition->request_date)) {
            throw ValidationException::withMessages(['required_date' => 'The required date must be on or after the request date.']);
        }
        $this->validateItems($requisition->items->map(fn (PurchaseRequisitionItem $item): array => [
            'material_id' => $item->material_id,
            'unit_id' => $item->unit_id,
            'material_requirement_id' => $item->material_requirement_id,
            'quantity' => $item->quantity,
        ])->all());
    }

    private function validateItems(array $items): void
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'At least one Purchase Requisition item is required.']);
        }
        foreach ($items as $index => $item) {
            $this->referenceService->material((int) ($item['material_id'] ?? 0));
            $this->referenceService->unit((int) ($item['unit_id'] ?? 0));
            if (isset($item['material_requirement_id']) && $item['material_requirement_id'] !== null) {
                if (! MaterialRequirement::query()->whereKey($item['material_requirement_id'])->exists()) {
                    throw ValidationException::withMessages(["items.{$index}.material_requirement_id" => 'The selected Material Requirement does not exist.']);
                }
            }
            if ((float) ($item['quantity'] ?? 0) <= 0) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => 'The quantity must be greater than zero.']);
            }
        }
    }

    private function replaceItems(PurchaseRequisition $requisition, array $items, User $actor): void
    {
        $requisition->items()->delete();
        foreach (array_values($items) as $index => $item) {
            $created = $requisition->items()->create([
                'material_id' => $item['material_id'],
                'unit_id' => $item['unit_id'],
                'material_requirement_id' => $item['material_requirement_id'] ?? null,
                'quantity' => $item['quantity'],
                'converted_quantity' => 0,
                'remarks' => $item['remarks'] ?? null,
                'line_number' => $index + 1,
            ]);
            $this->auditLogService->record($actor, 'procurement-requisition-items', $created, 'created', null, $created->attributesToArray());
        }
    }

    private function transition(PurchaseRequisition $requisition, string $newStatus, string $remarks, User $actor): void
    {
        $oldStatus = $requisition->status;
        $this->workflow->assertRequisitionTransition($requisition, $newStatus);
        $requisition->forceFill(['status' => $newStatus])->save();
        $requisition->statusHistories()->create([
            'document_type' => ProcurementStatusHistory::REQUISITION,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
        $this->auditLogService->record($actor, 'procurement-requisitions', $requisition, 'status_changed', ['status' => $oldStatus], ['status' => $newStatus, 'remarks' => $remarks]);
    }

    private function recordStatus(PurchaseRequisition $requisition, ?string $oldStatus, string $newStatus, User $actor, string $remarks): void
    {
        $requisition->statusHistories()->create([
            'document_type' => ProcurementStatusHistory::REQUISITION,
            'previous_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $actor->getKey(),
            'remarks' => $remarks,
        ]);
    }

    private function generateNumber(): string
    {
        $prefix = 'PR-'.now()->format('Ymd');
        $sequence = PurchaseRequisition::withTrashed()->where('requisition_number', 'like', "{$prefix}-%")->count() + 1;
        do {
            $candidate = sprintf('%s-%04d', $prefix, $sequence++);
        } while (PurchaseRequisition::withTrashed()->where('requisition_number', $candidate)->exists());

        return $candidate;
    }
}
