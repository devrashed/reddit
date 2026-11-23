<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemStatusService
{
    /**
     * Build a safe, read-only snapshot of the application health.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $databaseConnected = false;
        $databaseVersion = null;

        try {
            $connection = DB::connection();
            $connection->getPdo();
            $databaseConnected = true;

            $versionQuery = $connection->getDriverName() === 'sqlite'
                ? 'select sqlite_version() as version'
                : 'select version() as version';

            $databaseVersion = $connection->selectOne($versionQuery)->version;
        } catch (Throwable $exception) {
            Log::warning('Database health check failed.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return [
            'status' => $databaseConnected ? 'healthy' : 'degraded',
            'application' => config('app.name'),
            'environment' => app()->environment(),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'timezone' => config('app.timezone'),
            'database' => [
                'driver' => config('database.default'),
                'connected' => $databaseConnected,
                'version' => $databaseVersion,
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
