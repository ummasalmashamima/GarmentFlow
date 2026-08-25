<?php

declare(strict_types=1);

namespace App\Requests\MasterData;

use App\Services\MasterData\MasterDataRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('master-data.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $resource = (string) $this->route('resource');
        $definition = MasterDataRegistry::get($resource);
        $rules = $definition['rules'];
        $recordId = $this->route('id');

        foreach ($rules as $field => $fieldRules) {
            if (in_array($field, ['code', 'sku'], true)) {
                $table = app($definition['model'])->getTable();
                $rules[$field][] = Rule::unique($table, $field)->ignore($recordId);
            }
        }

        return $rules;
    }
}
