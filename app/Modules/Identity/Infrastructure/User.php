<?php

namespace App\Modules\Identity\Infrastructure;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    protected $attributes = ['is_active' => true];

    protected $fillable = ['mobile'];

    protected $hidden = ['mobile'];

    protected function casts(): array
    {
        return ['mobile_verified_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && $this->mobile_verified_at !== null
            && $this->roles()->whereHas('permissions', fn ($query) => $query->where('name', $permission))->exists();
    }
}
