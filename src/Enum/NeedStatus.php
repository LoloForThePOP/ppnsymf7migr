<?php

namespace App\Enum;

enum NeedStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Fulfilled = 'fulfilled';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
