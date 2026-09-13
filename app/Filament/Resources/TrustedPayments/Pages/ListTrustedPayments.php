<?php

namespace App\Filament\Resources\TrustedPayments\Pages;

use App\Filament\Resources\TrustedPayments\TrustedPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListTrustedPayments extends ListRecords
{
    protected static string $resource = TrustedPaymentResource::class;
}
