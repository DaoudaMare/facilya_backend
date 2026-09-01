<?php

namespace Tests\Unit;

use App\Data\FeeModeEnum;
use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Fee;
use App\Repositories\Contracts\FeeRepositoryInterface;
use App\Repositories\Contracts\TravelCompanyRouteRepositoryInterface;
use App\Services\FeeQuoteService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeeQuoteServiceTest extends TestCase
{
    #[Test]
    public function percentage_fee_is_based_on_service_price(): void
    {
        $network = $this->makeFee(FeeModeEnum::PERCENTAGE, '2');
        $platform = $this->makeFee(FeeModeEnum::FIXED, '100');

        $fees = Mockery::mock(FeeRepositoryInterface::class);
        $fees->shouldReceive('findCandidates')
            ->once()
            ->with(TransactionTypeEnum::TICKET_PURCHASE, FeePartEnum::NETWORK, '16000.00')
            ->andReturn(new Collection([$network]));
        $fees->shouldReceive('findCandidates')
            ->once()
            ->with(TransactionTypeEnum::TICKET_PURCHASE, FeePartEnum::PLATFORM, '16000.00')
            ->andReturn(new Collection([$platform]));

        $service = new FeeQuoteService(
            $fees,
            Mockery::mock(TravelCompanyRouteRepositoryInterface::class),
        );

        $quote = $service->quote(TransactionTypeEnum::TICKET_PURCHASE, '16000');

        $this->assertSame('320.00', $quote->networkFee);
        $this->assertSame('100.00', $quote->platformFee);
        $this->assertSame('420.00', $quote->totalFee());
        $this->assertSame('16420.00', $quote->totalAmount());
    }

    #[Test]
    public function corridor_rule_wins_over_global_rule(): void
    {
        $global = $this->makeFee(FeeModeEnum::FIXED, '500');
        $corridor = $this->makeFee(FeeModeEnum::FIXED, '150', networkId: 1, counterpartId: 2);

        $fees = Mockery::mock(FeeRepositoryInterface::class);
        $fees->shouldReceive('findCandidates')
            ->once()
            ->andReturn(new Collection([$global, $corridor]));
        $fees->shouldReceive('findCandidates')
            ->once()
            ->andReturn(new Collection);

        $service = new FeeQuoteService(
            $fees,
            Mockery::mock(TravelCompanyRouteRepositoryInterface::class),
        );

        $quote = $service->quote(TransactionTypeEnum::NETWORK_TRANSFER, '10000', 1, 2);

        $this->assertSame('150.00', $quote->networkFee);
        $this->assertSame('0.00', $quote->platformFee);
    }

    #[Test]
    public function ticket_service_amount_is_price_times_seats(): void
    {
        $routes = Mockery::mock(TravelCompanyRouteRepositoryInterface::class);
        $routes->shouldReceive('priceOf')->once()->with(5)->andReturn('8000.00');

        $service = new FeeQuoteService(
            Mockery::mock(FeeRepositoryInterface::class),
            $routes,
        );

        $this->assertSame('16000.00', $service->serviceAmountForTicket(5, 2));
    }

    #[Test]
    public function percentage_respects_min_and_max_fee(): void
    {
        $fee = $this->makeFee(FeeModeEnum::PERCENTAGE, '2', minFee: '200', maxFee: '400');
        $service = new FeeQuoteService(
            Mockery::mock(FeeRepositoryInterface::class),
            Mockery::mock(TravelCompanyRouteRepositoryInterface::class),
        );

        $this->assertSame('200.00', $service->compute($fee, '5000'));
        $this->assertSame('300.00', $service->compute($fee, '15000'));
        $this->assertSame('400.00', $service->compute($fee, '50000'));
    }

    protected function makeFee(
        FeeModeEnum $mode,
        string $value,
        ?int $networkId = null,
        ?int $counterpartId = null,
        ?string $minFee = null,
        ?string $maxFee = null,
    ): Fee {
        return new Fee([
            'mode' => $mode,
            'value' => $value,
            'network_id' => $networkId,
            'counterpart_network_id' => $counterpartId,
            'min_fee' => $minFee,
            'max_fee' => $maxFee,
        ]);
    }
}
