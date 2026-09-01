<?php

namespace App\Repositories\Contracts;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

interface TransactionRepositoryInterface
{
    public function find(int $id): ?Transaction;

    public function findOrFail(int $id): Transaction;

    public function findByReference(string $reference): ?Transaction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Transaction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Transaction $transaction, array $attributes): Transaction;

    public function delete(Transaction $transaction): bool;

    public function countToday(): int;

    public function sumAmountToday(): string;

    public function countPendingPayment(): int;

    public function countAwaitingService(): int;

    /**
     * @return Collection<int, Transaction>
     */
    public function latest(int $limit = 10): Collection;

    /**
     * @return Collection<int, Transaction>
     */
    public function listForUser(int $userId, ?string $type = null, int $limit = 50): Collection;

    /**
     * @return array{month_total: string, exchanges_total: string, tickets_total: string, count: int}
     */
    public function monthlyStatsForUser(int $userId): array;

    public function findForUser(int $userId, int $id): ?Transaction;
}
