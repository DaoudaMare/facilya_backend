<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Promotion extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $inner): void {
                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $inner): void {
                $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image)) {
            return null;
        }

        $path = ltrim((string) $this->image, '/');

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relative = '/storage/'.$path;

        if (request()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/').$relative;
        }

        return Storage::disk('public')->url($path);
    }
}
