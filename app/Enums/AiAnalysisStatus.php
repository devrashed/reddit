<?php

namespace App\Enums;

enum AiAnalysisStatus: string
{
    case NotAnalyzed = 'not_analyzed';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public static function fromDatabase(?string $status): self
    {
        if ($status === null || $status === '') {
            return self::NotAnalyzed;
        }

        if ($status === 'running') {
            return self::Processing;
        }

        return self::tryFrom($status) ?? self::NotAnalyzed;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotAnalyzed => 'Not analyzed',
            self::Pending => 'Pending',
            self::Processing => 'In progress',
            self::Completed => 'Analyzed',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotAnalyzed => 'badge-soft-secondary',
            self::Pending => 'badge-soft-warning',
            self::Processing => 'badge-soft-info',
            self::Completed => 'badge-soft-success',
            self::Failed => 'badge-soft-danger',
        };
    }

    public function isProcessing(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
