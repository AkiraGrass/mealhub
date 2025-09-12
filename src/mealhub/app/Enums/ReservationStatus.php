<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
}

