<?php

use App\Services\MapDirectionService;
use Illuminate\Support\Facades\Route;

Route::get('/get-directions', function () {
    $directions = app(MapDirectionService::class)->getDirections([
        'latitude' => 48.8566,
        'longitude' => 2.3522,
    ], [
        'latitude' => 48.8584,
        'longitude' => 2.3508,
    ]);
    return response()->json($directions);
});