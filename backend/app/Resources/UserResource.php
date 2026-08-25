<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
                'code' => $this->department?->code,
            ]),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('slug')->values()),
            'permissions' => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->roles
                    ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
                    ->unique()
                    ->values(),
            ),
        ];
    }
}
