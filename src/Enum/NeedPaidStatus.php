<?php

namespace App\Enum;

enum NeedPaidStatus: string
{
    case Yes   = 'yes';
    case Maybe = 'maybe';
    case No    = 'no';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
