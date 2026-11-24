<?php

namespace App\Enums;

enum CommentCollectionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-soft-secondary',
            self::Running => 'badge-soft-info',
            self::Completed => 'badge-soft-success',
            self::Failed => 'badge-soft-danger',
        };
    }
}
