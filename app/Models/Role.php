<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'can_access_panel',
    ];

    protected function casts(): array
    {
        return [
            'can_access_panel' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains(fn (Permission $permission) => $permission->slug === $slug);
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }
}
