<?php

namespace App\Enums;

enum PostQualificationStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Qualified => 'Qualified',
        };
    }
}
