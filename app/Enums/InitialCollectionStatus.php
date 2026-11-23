<?php

namespace App\Enums;

enum InitialCollectionStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-soft-secondary',
            self::Queued => 'badge-soft-primary',
            self::Running => 'badge-soft-info',
            self::Completed => 'badge-soft-success',
            self::Failed => 'badge-soft-danger',
        };
    }
}
