<?php

declare(strict_types=1);

namespace App\Requests\MasterData;

use App\Services\MasterData\MasterDataRegistry;
use Illuminate\Foundation\Http\FormRequest;

class MasterDataQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('master-data.view') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $resource = (string) $this->route('resource');
        $definition = MasterDataRegistry::get($resource);
        $rules = [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', 'in:'.implode(',', $definition['sortable'])],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        foreach ($definition['filterable'] as $field) {
            if ($field !== 'status') {
                $rules[$field] = ['nullable', 'integer', 'min:1'];
            }
        }

        return $rules;
    }
}
