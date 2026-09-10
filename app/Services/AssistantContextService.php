<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\Transaction;
use App\Models\User;
use App\Models\TravelCompanyRoute;

class AssistantContextService
{
    public function __construct(
        protected TransferNetworkService $networks,
        protected TravelCompanyService $travel,
        protected PromotionService $promotions,
    ) {}

    public function build(?User $user = null, ?string $departure = null, ?string $arrival = null): string
    {
        $sections = [
            $this->networksSection(),
            $this->feesSection(),
            $this->promotionsSection(),
            $this->travelSection($departure, $arrival),
        ];

        if ($user) {
            $sections[] = $this->userSection($user);
        }

        return implode("\n\n", array_filter($sections));
    }

    protected function networksSection(): string
    {
        $networks = $this->networks->listActive()->map(function ($network) {
            $code = $network->code?->value ?? (string) $network->code;

            return sprintf(
                '- %s (%s): envoi=%s, réception=%s%s',
                $network->name,
                $code,
                $network->can_send ? 'oui' : 'non',
                $network->can_receive ? 'oui' : 'non',
                filled($network->description) ? ', '.$network->description : '',
            );
        })->all();

        if ($networks === []) {
            return "Réseaux de transfert actifs:\n- Aucun réseau actif.";
        }

        return "Réseaux de transfert actifs:\n".implode("\n", $networks);
    }

    protected function feesSection(): string
    {
        $fees = Fee::query()
            ->active()
            ->with(['network:id,name,code', 'counterpartNetwork:id,name,code'])
            ->orderBy('transaction_type')
            ->orderBy('part')
            ->limit(40)
            ->get()
            ->map(function (Fee $fee) {
                $type = $fee->transaction_type?->value ?? (string) $fee->transaction_type;
                $part = $fee->part?->value ?? (string) $fee->part;
                $mode = $fee->mode?->value ?? (string) $fee->mode;
                $network = $fee->network?->name ?? 'tous';
                $counterpart = $fee->counterpartNetwork?->name ?? 'tous';

                return sprintf(
                    '- %s | type=%s | part=%s | mode=%s | valeur=%s | min=%s | max=%s | montant[%s-%s] | réseau=%s | contrepartie=%s',
                    $fee->name ?: 'Frais',
                    $type,
                    $part,
                    $mode,
                    (string) $fee->value,
                    $fee->min_fee ?? '—',
                    $fee->max_fee ?? '—',
                    $fee->min_amount ?? '—',
                    $fee->max_amount ?? '—',
                    $network,
                    $counterpart,
                );
            })
            ->all();

        if ($fees === []) {
            return "Grille tarifaire:\n- Aucune règle de frais active.";
        }

        return "Grille tarifaire (règles actives):\n".implode("\n", $fees);
    }

    protected function promotionsSection(): string
    {
        $promos = $this->promotions->listActive()->map(function ($promo) {
            return sprintf(
                '- %s%s',
                $promo->title,
                filled($promo->subtitle) ? ' — '.$promo->subtitle : '',
            );
        })->all();

        if ($promos === []) {
            return "Promotions:\n- Aucune promotion active.";
        }

        return "Promotions actives:\n".implode("\n", $promos);
    }

    protected function travelSection(?string $departure, ?string $arrival): string
    {
        $cities = $this->travel->cities();
        $corridors = $this->travel->popularCorridors(8);

        $lines = [
            'Voyage / billets:',
            '- Villes desservies: '.(empty($cities) ? 'aucune' : implode(', ', $cities)),
        ];

        if ($corridors !== []) {
            $lines[] = '- Corridors populaires:';
            foreach ($corridors as $corridor) {
                $lines[] = sprintf(
                    '  • %s → %s (à partir de %s)',
                    $corridor['departure'],
                    $corridor['arrival'],
                    $corridor['from_price'],
                );
            }
        }

        if ($departure && $arrival) {
            $trips = $this->travel->searchTrips($departure, $arrival);
            $lines[] = sprintf('- Trajets trouvés pour %s → %s:', $departure, $arrival);

            if ($trips->isEmpty()) {
                $lines[] = '  • Aucun trajet actif trouvé.';
            } else {
                foreach ($trips->take(12) as $trip) {
                    /** @var \App\Models\TravelCompanyTrip $trip */
                    $route = $trip->route;
                    $company = $route?->travelCompany?->name ?? 'Compagnie';
                    $price = $route?->price ?? '—';
                    $lines[] = sprintf(
                        '  • %s | départ %s | arrivée %s | sièges %s | prix %s',
                        $company,
                        $trip->formattedDeparture(),
                        $trip->formattedArrival(),
                        $trip->available_seats ?? '—',
                        $price,
                    );
                }
            }
        } else {
            $sampleRoutes = TravelCompanyRoute::query()
                ->where('is_active', true)
                ->with('travelCompany:id,name')
                ->orderBy('departure')
                ->limit(15)
                ->get();

            if ($sampleRoutes->isNotEmpty()) {
                $lines[] = '- Exemples de lignes actives:';
                foreach ($sampleRoutes as $route) {
                    $lines[] = sprintf(
                        '  • %s: %s → %s (%s) prix %s',
                        $route->travelCompany?->name ?? 'Compagnie',
                        $route->departure,
                        $route->arrival,
                        $route->travel_type?->value ?? (string) $route->travel_type,
                        $route->price,
                    );
                }
            }
        }

        return implode("\n", $lines);
    }

    protected function userSection(User $user): string
    {
        $transactions = Transaction::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get(['uuid', 'reference', 'type', 'payment_status', 'service_status', 'amount', 'currency', 'created_at']);

        $lines = [
            'Profil client connecté:',
            sprintf('- Nom: %s', filled($user->name) ? (string) $user->name : 'non renseigné'),
            sprintf('- Téléphone: %s', $user->phone ? $this->maskPhone((string) $user->phone) : 'non renseigné'),
            '- Dernières transactions:',
        ];

        if ($transactions->isEmpty()) {
            $lines[] = '  • Aucune transaction.';
        } else {
            foreach ($transactions as $tx) {
                $lines[] = sprintf(
                    '  • %s | type=%s | paiement=%s | service=%s | montant=%s %s | date=%s',
                    $tx->reference ?: $tx->uuid,
                    $tx->type?->value ?? (string) $tx->type,
                    $tx->payment_status?->value ?? (string) $tx->payment_status,
                    $tx->service_status?->value ?? (string) $tx->service_status,
                    $tx->amount,
                    $tx->currency ?? '',
                    optional($tx->created_at)?->toDateTimeString() ?? '—',
                );
            }
        }

        return implode("\n", $lines);
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }
}
