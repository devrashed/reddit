<?php

namespace App\Services\Reddit;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedditSettings
{
    public const GROUP = 'reddit';

    public const KEY_ENABLED = 'reddit.regular_enabled';

    public const KEY_FREQUENCY = 'reddit.regular_frequency';

    public const KEY_TIMES = 'reddit.regular_times';

    public const KEY_HTTP_TIMEOUT = 'reddit.http_timeout';

    public const KEY_RETRY_ATTEMPTS = 'reddit.retry_attempts';

    public const KEY_HISTORICAL_MONTHS = 'reddit.historical_months';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolved = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = array_replace($this->defaults(), $this->stored());
    }

    public function regularEnabled(): bool
    {
        return (bool) $this->all()[self::KEY_ENABLED];
    }

    public function regularFrequency(): int
    {
        return max(1, min(6, (int) $this->all()[self::KEY_FREQUENCY]));
    }

    /**
     * @return list<string>
     */
    public function regularTimes(): array
    {
        $times = $this->all()[self::KEY_TIMES];

        if (! is_array($times)) {
            $times = [];
        }

        $times = array_values(array_unique(array_filter(
            array_map(static fn (mixed $time): string => trim((string) $time), $times),
            static fn (string $time): bool => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1
        )));

        sort($times);

        return $times !== [] ? $times : $this->defaults()[self::KEY_TIMES];
    }

    public function httpTimeout(): int
    {
        return max(5, min(60, (int) $this->all()[self::KEY_HTTP_TIMEOUT]));
    }

    public function retryAttempts(): int
    {
        return max(1, min(5, (int) $this->all()[self::KEY_RETRY_ATTEMPTS]));
    }

    public function historicalMonths(): int
    {
        return max(1, min(12, (int) $this->all()[self::KEY_HISTORICAL_MONTHS]));
    }

    /**
     * Persist collection settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $times = array_values(array_unique(array_map(
            static fn (mixed $time): string => trim((string) $time),
            (array) ($data['times'] ?? [])
        )));
        $times = array_values(array_filter($times, static fn (string $time): bool => $time !== ''));
        sort($times);

        $rows = [
            self::KEY_ENABLED => ['value' => $data['regular_enabled'] ? '1' : '0', 'type' => 'boolean'],
            self::KEY_FREQUENCY => ['value' => (string) $data['regular_frequency'], 'type' => 'integer'],
            self::KEY_TIMES => ['value' => json_encode($times, JSON_THROW_ON_ERROR), 'type' => 'json'],
            self::KEY_HTTP_TIMEOUT => ['value' => (string) $data['http_timeout'], 'type' => 'integer'],
            self::KEY_RETRY_ATTEMPTS => ['value' => (string) $data['retry_attempts'], 'type' => 'integer'],
            self::KEY_HISTORICAL_MONTHS => ['value' => (string) $data['historical_months'], 'type' => 'integer'],
        ];

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $key => $row) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['group' => self::GROUP, 'value' => $row['value'], 'type' => $row['type']]
                );
            }
        });

        $this->resolved = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $defaults = config('reddit.defaults', []);

        return [
            self::KEY_ENABLED => (bool) ($defaults['regular_enabled'] ?? true),
            self::KEY_FREQUENCY => (int) ($defaults['regular_frequency'] ?? 3),
            self::KEY_TIMES => array_values((array) ($defaults['regular_times'] ?? ['08:00', '14:00', '20:00'])),
            self::KEY_HTTP_TIMEOUT => (int) ($defaults['http_timeout'] ?? config('reddit.timeout', 10)),
            self::KEY_RETRY_ATTEMPTS => (int) ($defaults['retry_attempts'] ?? config('reddit.retry_attempts', 3)),
            self::KEY_HISTORICAL_MONTHS => (int) ($defaults['historical_months'] ?? 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        try {
            $rows = Setting::query()
                ->where('group', self::GROUP)
                ->get(['key', 'value', 'type']);
        } catch (Throwable) {
            return [];
        }

        $defaults = $this->defaults();
        $values = [];

        foreach ($rows as $row) {
            if (! array_key_exists($row->key, $defaults)) {
                continue;
            }

            $values[$row->key] = match ($row->type) {
                'boolean' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $row->value,
                'json' => $this->decodeJson($row->value, $defaults[$row->key]),
                default => $row->value,
            };
        }

        return $values;
    }

    private function decodeJson(?string $value, mixed $default): mixed
    {
        if ($value === null) {
            return $default;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $default;
        }

        return is_array($decoded) ? $decoded : $default;
    }
}
