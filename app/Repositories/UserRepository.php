<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function find(int $id): ?User
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): User
    {
        return parent::findOrFail($id);
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->query()->where('phone', $phone)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    public function create(array $attributes): User
    {
        return parent::create($attributes);
    }

    public function update(User $user, array $attributes): User
    {
        return $this->persist($user, $attributes);
    }
}
