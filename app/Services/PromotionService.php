<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

class PromotionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Promotion
    {
        return Promotion::query()->create($this->sanitize($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Promotion $promotion, array $attributes): Promotion
    {
        $promotion->update($this->sanitize($attributes));

        return $promotion->fresh() ?? $promotion;
    }

    /**
     * @return Collection<int, Promotion>
     */
    public function listActive(): Collection
    {
        return Promotion::query()
            ->active()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function sanitize(array $attributes): array
    {
        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? 0);
        $attributes['is_active'] ??= true;

        $link = trim((string) ($attributes['link_url'] ?? ''));
        $attributes['link_url'] = $link === '' ? null : $link;

        return $attributes;
    }
}
