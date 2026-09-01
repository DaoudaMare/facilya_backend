<?php

namespace Tests\Feature;

use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_active_promotions(): void
    {
        Promotion::query()->create([
            'title' => 'Moov à 0,5 %',
            'subtitle' => 'Frais réduits ce week-end',
            'image' => 'promotions/moov.jpg',
            'link_url' => 'https://facilya.bf',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Promotion::query()->create([
            'title' => 'Inactive',
            'image' => 'promotions/off.jpg',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Moov à 0,5 %')
            ->assertJsonPath('data.0.subtitle', 'Frais réduits ce week-end')
            ->assertJsonPath('data.0.link_url', 'https://facilya.bf');

        $imageUrl = $this->getJson('/api/v1/promotions')->json('data.0.image_url');
        $this->assertStringContainsString('/storage/promotions/moov.jpg', (string) $imageUrl);
    }
}
