<?php

namespace App\Repositories;

use App\Models\TravelCompanyRoute;
use App\Repositories\Contracts\TravelCompanyRouteRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TravelCompanyRoute>
 */
class TravelCompanyRouteRepository extends BaseRepository implements TravelCompanyRouteRepositoryInterface
{
    protected function model(): string
    {
        return TravelCompanyRoute::class;
    }

    public function find(int $id): ?TravelCompanyRoute
    {
        return parent::find($id);
    }

    public function findOrFail(int $id): TravelCompanyRoute
    {
        return parent::findOrFail($id);
    }

    public function create(array $attributes): TravelCompanyRoute
    {
        return parent::create($attributes);
    }

    public function update(TravelCompanyRoute $route, array $attributes): TravelCompanyRoute
    {
        return $this->persist($route, $attributes);
    }

    public function delete(TravelCompanyRoute $route): bool
    {
        return $this->destroy($route);
    }

    public function priceOf(int $id): ?string
    {
        $price = $this->query()->whereKey($id)->value('price');

        return $price === null ? null : (string) $price;
    }

    /**
     * @return Collection<int, TravelCompanyRoute>
     */
    public function listActive(?int $companyId = null): Collection
    {
        return $this->query()
            ->with('travelCompany')
            ->where('is_active', true)
            ->when($companyId, fn ($query) => $query->where('travel_company_id', $companyId))
            ->orderBy('departure')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function distinctCities(): array
    {
        $departures = $this->query()->where('is_active', true)->pluck('departure');
        $arrivals = $this->query()->where('is_active', true)->pluck('arrival');

        return $departures
            ->merge($arrivals)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<array{departure: string, arrival: string, from_price: string}>
     */
    public function popularCorridors(int $limit = 6): array
    {
        return $this->query()
            ->where('is_active', true)
            ->selectRaw('departure, arrival, MIN(price) as from_price')
            ->groupBy('departure', 'arrival')
            ->orderBy('from_price')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'departure' => (string) $row->departure,
                'arrival' => (string) $row->arrival,
                'from_price' => number_format((float) $row->from_price, 0, '.', ''),
            ])
            ->all();
    }
}
