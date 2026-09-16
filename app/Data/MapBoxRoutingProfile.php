<?php

namespace App\Data;

enum MapBoxRoutingProfile: string
{
    case Driving = 'driving';
    case DrivingTraffic = 'driving-traffic';
    case Walking = 'walking';
    case Cycling = 'cycling';
}
