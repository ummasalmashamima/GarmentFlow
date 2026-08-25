<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'department_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The department this user belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The roles assigned to this user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * Determine whether the user has a named role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    /**
     * Determine whether any assigned role grants a permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', static fn ($query) => $query->where('slug', $permission))
            ->exists();
    }

    public function buyerOrders(): HasMany
    {
        return $this->hasMany(BuyerOrder::class, 'created_by');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'created_by');
    }

    public function salesOrderStatusHistories(): HasMany
    {
        return $this->hasMany(SalesOrderStatusHistory::class, 'changed_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'created_by');
    }

    public function deliveryTrackingHistories(): HasMany
    {
        return $this->hasMany(DeliveryTrackingHistory::class, 'changed_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function orderApprovals(): HasMany
    {
        return $this->hasMany(OrderApproval::class, 'reviewed_by');
    }

    public function orderStatusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'changed_by');
    }

    public function orderPlanningInputs(): HasMany
    {
        return $this->hasMany(OrderPlanningInput::class, 'prepared_by');
    }

    public function demandForecasts(): HasMany
    {
        return $this->hasMany(DemandForecast::class, 'created_by');
    }

    public function supplyPlans(): HasMany
    {
        return $this->hasMany(SupplyPlan::class, 'created_by');
    }

    public function mrpRuns(): HasMany
    {
        return $this->hasMany(MrpRun::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
