<?php

declare(strict_types=1);

namespace App\Services\BOM;

use App\Models\BomHeader;
use App\Models\BomItem;
use App\Models\BomVersion;
use App\Models\Material;
use App\Models\Unit;
use App\Models\User;
use App\Services\MasterData\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BOMService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = BomHeader::query()->with(['product', 'activeVersion']);

        if ($filters['search'] ?? null) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('product', function (Builder $productQuery) use ($search): void {
                        $productQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderBy((string) ($filters['sort'] ?? 'id'), (string) ($filters['direction'] ?? 'desc'))
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    public function find(BomHeader $bom): BomHeader
    {
        return $bom->load([
            'product',
            'versions.items.material',
            'versions.items.unit',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): BomHeader
    {
        return DB::transaction(function () use ($attributes, $actor): BomHeader {
            $this->ensureActiveProduct((int) $attributes['product_id']);

            $bom = BomHeader::query()->create([
                'product_id' => $attributes['product_id'],
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'status' => 'draft',
                'description' => $attributes['description'] ?? null,
            ]);

            $version = $bom->versions()->create([
                'version_number' => 1,
                'effective_from' => $attributes['effective_from'],
                'effective_to' => $attributes['effective_to'] ?? null,
                'status' => 'draft',
                'notes' => $attributes['version_notes'] ?? null,
            ]);

            $this->auditLogService->record($actor, 'boms', $bom, 'created', null, $bom->attributesToArray());
            $this->auditLogService->record($actor, 'bom_versions', $version, 'version_created', null, $version->attributesToArray());

            return $this->find($bom);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(BomHeader $bom, array $attributes, User $actor): BomHeader
    {
        return DB::transaction(function () use ($bom, $attributes, $actor): BomHeader {
            $oldValues = $bom->attributesToArray();
            $bom->fill($attributes);
            $bom->save();
            $this->auditLogService->record($actor, 'boms', $bom, 'updated', $oldValues, $bom->attributesToArray());

            return $this->find($bom);
        });
    }

    public function delete(BomHeader $bom, User $actor): BomHeader
    {
        return DB::transaction(function () use ($bom, $actor): BomHeader {
            $oldValues = $bom->attributesToArray();
            $bom->forceFill(['status' => 'inactive'])->save();
            foreach ($bom->versions()->where('status', 'active')->get() as $version) {
                $versionOldValues = $version->attributesToArray();
                $version->forceFill(['status' => 'inactive'])->save();
                $this->auditLogService->record($actor, 'bom_versions', $version, 'version_deactivated', $versionOldValues, $version->attributesToArray());
            }
            $bom->delete();
            $this->auditLogService->record($actor, 'boms', $bom, 'deleted', $oldValues, $bom->attributesToArray());

            return $bom->load(['product', 'versions.items.material', 'versions.items.unit']);
        });
    }

    public function activate(BomHeader $bom, ?int $versionId, User $actor): BomHeader
    {
        return DB::transaction(function () use ($bom, $versionId, $actor): BomHeader {
            $version = $versionId === null
                ? $bom->versions()->whereIn('status', ['draft', 'inactive'])->latest('version_number')->first()
                : $bom->versions()->whereKey($versionId)->first();

            if ($version === null) {
                throw ValidationException::withMessages(['version_id' => 'A BOM version is required for activation.']);
            }

            $this->activateVersionWithinTransaction($version, $actor);

            return $this->find($bom->refresh());
        });
    }

    public function deactivate(BomHeader $bom, User $actor): BomHeader
    {
        return DB::transaction(function () use ($bom, $actor): BomHeader {
            $oldValues = $bom->attributesToArray();
            $bom->forceFill(['status' => 'inactive'])->save();
            foreach ($bom->versions()->where('status', 'active')->get() as $version) {
                $versionOldValues = $version->attributesToArray();
                $version->forceFill(['status' => 'inactive'])->save();
                $this->auditLogService->record($actor, 'bom_versions', $version, 'version_deactivated', $versionOldValues, $version->attributesToArray());
            }
            $this->auditLogService->record($actor, 'boms', $bom, 'deactivated', $oldValues, $bom->attributesToArray());

            return $this->find($bom->refresh());
        });
    }

    /**
     * @return Collection<int, BomVersion>
     */
    public function versions(BomHeader $bom): Collection
    {
        return $bom->versions()->withCount('items')->with('items.material')->get();
    }

    public function findVersion(BomHeader $bom, BomVersion $version): BomVersion
    {
        $this->ensureVersionBelongsToBom($bom, $version);

        return $version->load(['bomHeader.product', 'items.material', 'items.unit']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(BomHeader $bom, array $attributes, User $actor): BomVersion
    {
        return DB::transaction(function () use ($bom, $attributes, $actor): BomVersion {
            $version = $bom->versions()->create([
                'version_number' => ((int) $bom->versions()->withTrashed()->max('version_number')) + 1,
                'effective_from' => $attributes['effective_from'],
                'effective_to' => $attributes['effective_to'] ?? null,
                'status' => 'draft',
                'notes' => $attributes['notes'] ?? null,
            ]);
            $this->auditLogService->record($actor, 'bom_versions', $version, 'version_created', null, $version->attributesToArray());

            return $this->findVersion($bom, $version);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateVersion(BomHeader $bom, BomVersion $version, array $attributes, User $actor): BomVersion
    {
        return DB::transaction(function () use ($bom, $version, $attributes, $actor): BomVersion {
            $this->ensureVersionBelongsToBom($bom, $version);
            $this->ensureVersionEditable($version);
            $oldValues = $version->attributesToArray();
            $version->fill($attributes);
            $version->save();
            $this->auditLogService->record($actor, 'bom_versions', $version, 'version_updated', $oldValues, $version->attributesToArray());

            return $this->findVersion($bom, $version);
        });
    }

    public function activateVersion(BomHeader $bom, BomVersion $version, User $actor): BomVersion
    {
        return DB::transaction(function () use ($bom, $version, $actor): BomVersion {
            $this->ensureVersionBelongsToBom($bom, $version);
            $this->activateVersionWithinTransaction($version, $actor);

            return $this->findVersion($bom, $version->refresh());
        });
    }

    public function deactivateVersion(BomHeader $bom, BomVersion $version, User $actor): BomVersion
    {
        return DB::transaction(function () use ($bom, $version, $actor): BomVersion {
            $this->ensureVersionBelongsToBom($bom, $version);
            $oldValues = $version->attributesToArray();
            $version->forceFill(['status' => 'inactive'])->save();
            $this->auditLogService->record($actor, 'bom_versions', $version, 'version_deactivated', $oldValues, $version->attributesToArray());

            if ($bom->status === 'active' && ! $bom->versions()->where('status', 'active')->exists()) {
                $bom->forceFill(['status' => 'inactive'])->save();
                $this->auditLogService->record($actor, 'boms', $bom, 'deactivated', ['status' => 'active'], $bom->attributesToArray());
            }

            return $this->findVersion($bom, $version->refresh());
        });
    }

    /**
     * @return Collection<int, BomItem>
     */
    public function items(BomHeader $bom, BomVersion $version): Collection
    {
        $this->ensureVersionBelongsToBom($bom, $version);

        return $version->items()->with(['material', 'unit'])->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createItem(BomHeader $bom, BomVersion $version, array $attributes, User $actor): BomItem
    {
        return DB::transaction(function () use ($bom, $version, $attributes, $actor): BomItem {
            $this->ensureVersionBelongsToBom($bom, $version);
            $this->ensureVersionEditable($version);
            $this->ensureActiveMaterial((int) $attributes['material_id']);
            $this->ensureActiveUnit((int) $attributes['unit_id']);
            $this->ensureUniqueMaterial($version, (int) $attributes['material_id']);
            $lineNumber = $attributes['line_number'] ?? (((int) $version->items()->max('line_number')) + 1);
            $this->ensureUniqueLineNumber($version, (int) $lineNumber);

            $item = $version->items()->create([
                'material_id' => $attributes['material_id'],
                'unit_id' => $attributes['unit_id'],
                'quantity' => $attributes['quantity'],
                'wastage_percentage' => $attributes['wastage_percentage'] ?? 0,
                'line_number' => $lineNumber,
                'notes' => $attributes['notes'] ?? null,
            ]);
            $this->auditLogService->record($actor, 'bom_items', $item, 'item_created', null, $item->attributesToArray());

            return $item->load(['material', 'unit', 'bomVersion']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateItem(BomHeader $bom, BomVersion $version, BomItem $item, array $attributes, User $actor): BomItem
    {
        return DB::transaction(function () use ($bom, $version, $item, $attributes, $actor): BomItem {
            $this->ensureVersionBelongsToBom($bom, $version);
            $this->ensureItemBelongsToVersion($version, $item);
            $this->ensureVersionEditable($version);
            $this->ensureActiveMaterial((int) ($attributes['material_id'] ?? $item->material_id));
            $this->ensureActiveUnit((int) ($attributes['unit_id'] ?? $item->unit_id));

            if (isset($attributes['material_id']) && (int) $attributes['material_id'] !== (int) $item->material_id) {
                $this->ensureUniqueMaterial($version, (int) $attributes['material_id'], $item->getKey());
            }

            if (isset($attributes['line_number']) && (int) $attributes['line_number'] !== (int) $item->line_number) {
                $this->ensureUniqueLineNumber($version, (int) $attributes['line_number'], $item->getKey());
            }

            $oldValues = $item->attributesToArray();
            $item->fill($attributes);
            $item->save();
            $this->auditLogService->record($actor, 'bom_items', $item, 'item_updated', $oldValues, $item->attributesToArray());

            return $item->load(['material', 'unit', 'bomVersion']);
        });
    }

    public function deleteItem(BomHeader $bom, BomVersion $version, BomItem $item, User $actor): BomItem
    {
        return DB::transaction(function () use ($bom, $version, $item, $actor): BomItem {
            $this->ensureVersionBelongsToBom($bom, $version);
            $this->ensureItemBelongsToVersion($version, $item);
            $this->ensureVersionEditable($version);
            $oldValues = $item->attributesToArray();
            $item->delete();
            $this->auditLogService->record($actor, 'bom_items', $item, 'item_deleted', $oldValues, null);

            return $item->load(['material', 'unit', 'bomVersion']);
        });
    }

    private function activateVersionWithinTransaction(BomVersion $version, User $actor): void
    {
        $version->loadMissing('bomHeader');

        if (! $version->items()->exists()) {
            throw ValidationException::withMessages(['version' => 'A BOM version must contain at least one material item before activation.']);
        }

        $header = $version->bomHeader;
        $otherActiveVersions = $header->versions()
            ->where('status', 'active')
            ->whereKeyNot($version->getKey())
            ->get();

        foreach ($otherActiveVersions as $otherVersion) {
            $oldValues = $otherVersion->attributesToArray();
            $otherVersion->forceFill(['status' => 'inactive'])->save();
            $this->auditLogService->record($actor, 'bom_versions', $otherVersion, 'version_deactivated', $oldValues, $otherVersion->attributesToArray());
        }

        $oldVersionValues = $version->attributesToArray();
        $version->forceFill(['status' => 'active'])->save();
        $this->auditLogService->record($actor, 'bom_versions', $version, 'version_activated', $oldVersionValues, $version->attributesToArray());

        $oldHeaderValues = $header->attributesToArray();
        $header->forceFill(['status' => 'active'])->save();
        $this->auditLogService->record($actor, 'boms', $header, 'activated', $oldHeaderValues, $header->attributesToArray());
    }

    private function ensureVersionBelongsToBom(BomHeader $bom, BomVersion $version): void
    {
        if ((int) $version->bom_header_id !== (int) $bom->getKey()) {
            abort(404, 'The BOM version does not belong to this BOM.');
        }
    }

    private function ensureItemBelongsToVersion(BomVersion $version, BomItem $item): void
    {
        if ((int) $item->bom_version_id !== (int) $version->getKey()) {
            abort(404, 'The BOM item does not belong to this version.');
        }
    }

    private function ensureVersionEditable(BomVersion $version): void
    {
        if ($version->status === 'active') {
            throw ValidationException::withMessages([
                'version' => 'Active BOM versions are read-only. Create a new version before editing material lines.',
            ]);
        }
    }

    private function ensureUniqueMaterial(BomVersion $version, int $materialId, ?int $ignoreItemId = null): void
    {
        $query = $version->items()->where('material_id', $materialId);

        if ($ignoreItemId !== null) {
            $query->whereKeyNot($ignoreItemId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'material_id' => 'This material already exists in the selected BOM version.',
            ]);
        }
    }

    private function ensureUniqueLineNumber(BomVersion $version, int $lineNumber, ?int $ignoreItemId = null): void
    {
        $query = $version->items()->where('line_number', $lineNumber);

        if ($ignoreItemId !== null) {
            $query->whereKeyNot($ignoreItemId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'line_number' => 'This line number already exists in the selected BOM version.',
            ]);
        }
    }

    private function ensureActiveProduct(int $productId): void
    {
        $exists = DB::table('products')->where('id', $productId)->where('status', 'active')->whereNull('deleted_at')->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['product_id' => 'The selected product must exist and be active.']);
        }
    }

    private function ensureActiveMaterial(int $materialId): void
    {
        if (! Material::query()->whereKey($materialId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['material_id' => 'The selected material must exist and be active.']);
        }
    }

    private function ensureActiveUnit(int $unitId): void
    {
        if (! Unit::query()->whereKey($unitId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'The selected unit must exist and be active.']);
        }
    }
}
