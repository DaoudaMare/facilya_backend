<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_client_cannot_access_admin_panel(): void
    {
        $client = User::query()->where('phone', '0700000011')->firstOrFail();
        $this->assertSame('client', $client->role?->slug);
        $this->assertFalse($client->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertFalse($client->hasPermission('users.manage'));
    }

    public function test_admin_can_access_panel_and_manage_users(): void
    {
        $admin = User::query()->where('email', 'admin@facilya.local')->firstOrFail();
        $this->assertSame('admin', $admin->role?->slug);
        $this->assertTrue($admin->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertTrue($admin->hasPermission('users.manage'));
        $this->assertTrue($admin->hasPermission('roles.manage'));
    }

    public function test_ops_can_access_panel_but_not_manage_roles(): void
    {
        $opsRole = Role::query()->where('slug', 'ops')->firstOrFail();
        $ops = User::factory()->create([
            'phone' => '0700000099',
            'email' => 'ops@facilya.local',
            'role_id' => $opsRole->id,
        ]);

        $this->assertTrue($ops->canAccessPanel(Filament::getCurrentPanel()));
        $this->assertTrue($ops->hasPermission('transactions.manage'));
        $this->assertTrue($ops->hasPermission('parcels.view'));
        $this->assertFalse($ops->hasPermission('roles.manage'));
        $this->assertFalse($ops->hasPermission('permissions.manage'));
        $this->assertFalse($ops->hasPermission('fees.manage'));
        $this->assertFalse($ops->hasPermission('networks.manage'));
        $this->assertFalse($ops->hasPermission('settings.manage'));
    }

    public function test_resource_gates_respect_ops_permissions(): void
    {
        $opsRole = Role::query()->where('slug', 'ops')->firstOrFail();
        $ops = User::factory()->create([
            'phone' => '0700000088',
            'email' => 'ops2@facilya.local',
            'role_id' => $opsRole->id,
        ]);

        $this->actingAs($ops);

        $this->assertTrue(\App\Filament\Resources\Transactions\TransactionResource::canViewAny());
        $this->assertTrue(\App\Filament\Resources\ParcelShipments\ParcelShipmentResource::canViewAny());
        $this->assertTrue(\App\Filament\Resources\TravelCompanies\TravelCompanyResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\Roles\RoleResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\Fees\FeeResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\TransferNetworks\TransferNetworkResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\Promotions\PromotionResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\Users\UserResource::canCreate());
        $this->assertTrue(\App\Filament\Resources\Users\UserResource::canViewAny());
        $this->assertTrue(\App\Filament\Pages\Configuration::canAccess());
        $this->get(\App\Filament\Pages\Configuration::getUrl())
            ->assertOk()
            ->assertSee('Tarifs colis')
            ->assertDontSee('Mobile Money')
            ->assertDontSee('Parrainage');
    }

    public function test_admin_can_access_configuration_page(): void
    {
        $admin = User::query()->where('email', 'admin@facilya.local')->firstOrFail();
        $this->actingAs($admin);

        $this->assertTrue(\App\Filament\Pages\Configuration::canAccess());
        $this->get(\App\Filament\Pages\Configuration::getUrl())
            ->assertOk()
            ->assertSee('Mobile Money')
            ->assertSee('Support WhatsApp')
            ->assertSee('Parrainage')
            ->assertSee('Frais')
            ->assertSee('Tarifs colis');
    }
}
