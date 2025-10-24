<?php

namespace App\Enum;

enum NeedType: string
{
    case Skill    = 'skill';
    case Task     = 'task';
    case Material = 'material';
    case Advice   = 'advice';
    case Area     = 'area';
    case Money    = 'money';
    case Other    = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    
}
