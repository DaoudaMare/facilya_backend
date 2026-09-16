<?php

namespace App\Filament\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesPermissions
{
    protected static function authUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    protected static function userHasPermission(string ...$slugs): bool
    {
        $user = static::authUser();

        return $user !== null && $user->hasAnyPermission(...$slugs);
    }

    /**
     * Lecture autorisée si view OU manage.
     */
    protected static function canAccessView(string $view, string $manage): bool
    {
        return static::userHasPermission($view, $manage);
    }

    /**
     * Écriture autorisée uniquement avec manage.
     */
    protected static function canAccessManage(string $manage): bool
    {
        return static::userHasPermission($manage);
    }

    /**
     * Branche canViewAny / canView / canCreate / canEdit / canDelete
     * pour une paire view/manage.
     *
     * @return array{viewAny: bool, view: bool, create: bool, edit: bool, delete: bool}
     */
    protected static function permissionMatrix(string $view, string $manage): array
    {
        $canView = static::canAccessView($view, $manage);
        $canManage = static::canAccessManage($manage);

        return [
            'viewAny' => $canView,
            'view' => $canView,
            'create' => $canManage,
            'edit' => $canManage,
            'delete' => $canManage,
        ];
    }
}
