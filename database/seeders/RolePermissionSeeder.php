<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->permissions() as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                $permission,
            );
        }

        $allPermissionIds = Permission::query()->pluck('id', 'slug');

        foreach ($this->roles() as $roleData) {
            $permissionSlugs = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::query()->updateOrCreate(
                ['slug' => $roleData['slug']],
                $roleData,
            );

            $ids = collect($permissionSlugs)
                ->map(fn (string $slug) => $allPermissionIds[$slug] ?? null)
                ->filter()
                ->values()
                ->all();

            $role->permissions()->sync($ids);
        }
    }

    /**
     * @return list<array{name: string, slug: string, group: string, description: ?string}>
     */
    protected function permissions(): array
    {
        return [
            ['name' => 'Voir les utilisateurs', 'slug' => 'users.view', 'group' => 'utilisateurs', 'description' => null],
            ['name' => 'Gérer les utilisateurs', 'slug' => 'users.manage', 'group' => 'utilisateurs', 'description' => null],
            ['name' => 'Voir les rôles', 'slug' => 'roles.view', 'group' => 'utilisateurs', 'description' => null],
            ['name' => 'Gérer les rôles', 'slug' => 'roles.manage', 'group' => 'utilisateurs', 'description' => null],
            ['name' => 'Voir les permissions', 'slug' => 'permissions.view', 'group' => 'utilisateurs', 'description' => null],
            ['name' => 'Gérer les permissions', 'slug' => 'permissions.manage', 'group' => 'utilisateurs', 'description' => null],
            ['name' => 'Voir les transactions', 'slug' => 'transactions.view', 'group' => 'finance', 'description' => null],
            ['name' => 'Gérer les transactions', 'slug' => 'transactions.manage', 'group' => 'finance', 'description' => null],
            ['name' => 'Voir les colis', 'slug' => 'parcels.view', 'group' => 'transport', 'description' => null],
            ['name' => 'Gérer les colis', 'slug' => 'parcels.manage', 'group' => 'transport', 'description' => null],
            ['name' => 'Voir les paiements confiants', 'slug' => 'trusted_payments.view', 'group' => 'finance', 'description' => null],
            ['name' => 'Gérer les paiements confiants', 'slug' => 'trusted_payments.manage', 'group' => 'finance', 'description' => null],
            ['name' => 'Voir les codes de déblocage escrow', 'slug' => 'trusted_payments.view_unlock_codes', 'group' => 'finance', 'description' => 'Afficher le code à 6 chiffres qui débloque les fonds, après confirmation du mot de passe.'],
            ['name' => 'Gérer les frais', 'slug' => 'fees.manage', 'group' => 'finance', 'description' => null],
            ['name' => 'Gérer les réseaux', 'slug' => 'networks.manage', 'group' => 'finance', 'description' => null],
            ['name' => 'Gérer le transport', 'slug' => 'travel.manage', 'group' => 'transport', 'description' => null],
            ['name' => 'Gérer les paramètres', 'slug' => 'settings.manage', 'group' => 'systeme', 'description' => null],
        ];
    }

    /**
     * @return list<array{name: string, slug: string, description: string, can_access_panel: bool, permissions: list<string>}>
     */
    protected function roles(): array
    {
        $all = array_column($this->permissions(), 'slug');

        $ops = [
            'transactions.view',
            'transactions.manage',
            'parcels.view',
            'parcels.manage',
            'trusted_payments.view',
            'trusted_payments.manage',
            'travel.manage',
            'users.view',
        ];

        return [
            [
                'name' => 'Super Admin',
                'slug' => 'admin',
                'description' => 'Accès complet au dashboard Filament.',
                'can_access_panel' => true,
                'permissions' => $all,
            ],
            [
                'name' => 'Opérations',
                'slug' => 'ops',
                'description' => 'Gestion opérationnelle (transactions, colis, travel).',
                'can_access_panel' => true,
                'permissions' => $ops,
            ],
            [
                'name' => 'Client',
                'slug' => 'client',
                'description' => 'Utilisateur application mobile. Pas d’accès admin.',
                'can_access_panel' => false,
                'permissions' => [],
            ],
        ];
    }
}
