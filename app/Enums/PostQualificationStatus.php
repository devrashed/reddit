<?php

namespace App\Enums;

enum PostQualificationStatus: string
{
    case Pending = 'pending';
    case Qualified = 'qualified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Qualified => 'Qualified',
            self::Rejected => 'Rejected',
        };
    }
}
