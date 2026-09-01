<?php

namespace App\Repositories;

use App\Data\PaymentStatusEnum;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Transaction>
 */
class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    protected function model(): string
    {
        return Transaction::class;
    }

    public function find(int $id): ?Transaction
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): Transaction
    {
        return parent::findOrFail($id);
    }

    public function findByReference(string $reference): ?Transaction
    {
        return $this->query()->where('reference', $reference)->first();
    }

    public function create(array $attributes): Transaction
    {
        return parent::create($attributes);
    }

    public function update(Transaction $transaction, array $attributes): Transaction
    {
        return $this->persist($transaction, $attributes);
    }

    public function delete(Transaction $transaction): bool
    {
        return $this->destroy($transaction);
    }

    public function countToday(): int
    {
        return $this->query()->whereDate('created_at', today())->count();
    }

    public function sumAmountToday(): string
    {
        return number_format(
            (float) $this->query()->whereDate('created_at', today())->sum('amount'),
            2,
            '.',
            '',
        );
    }

    public function countPendingPayment(): int
    {
        return $this->query()
            ->where('payment_status', PaymentStatusEnum::PENDING)
            ->count();
    }

    public function countAwaitingService(): int
    {
        return $this->query()->awaitingService()->count();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function latest(int $limit = 10): Collection
    {
        return $this->query()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function listForUser(int $userId, ?string $type = null, int $limit = 50): Collection
    {
        return $this->query()
            ->with([
                'sourceNetwork',
                'destinationNetwork',
                'paymentNetwork',
                'route.travelCompany',
                'trip.station',
            ])
            ->where('user_id', $userId)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{month_total: string, exchanges_total: string, tickets_total: string, count: int}
     */
    public function monthlyStatsForUser(int $userId): array
    {
        $query = $this->query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth());

        $exchanges = (clone $query)->networkTransfers()->sum('amount');
        $tickets = (clone $query)->ticketPurchases()->sum('amount');

        return [
            'month_total' => number_format((float) $query->sum('amount'), 2, '.', ''),
            'exchanges_total' => number_format((float) $exchanges, 2, '.', ''),
            'tickets_total' => number_format((float) $tickets, 2, '.', ''),
            'count' => $query->count(),
        ];
    }

    public function findForUser(int $userId, int $id): ?Transaction
    {
        return $this->query()
            ->with([
                'sourceNetwork',
                'destinationNetwork',
                'paymentNetwork',
                'route.travelCompany',
                'trip.station',
            ])
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();
    }
}
