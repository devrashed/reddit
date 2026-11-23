<?php

namespace App\Enums;

enum SubredditStatus: string
{
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge-soft-success',
            self::Paused => 'badge-soft-warning',
        };
    }

    public function toggle(): self
    {
        return $this === self::Active ? self::Paused : self::Active;
    }

    public function toggleLabel(): string
    {
        return $this === self::Active ? 'Pause' : 'Activate';
    }

    public function toggleIcon(): string
    {
        return $this === self::Active ? 'bi-pause-fill' : 'bi-play-fill';
    }
}
