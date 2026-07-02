<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends Controller
{
    /**
     * Run all health checks and return results.
     */
    public function index()
    {
        $checks = [
            'database'  => $this->checkDatabase(),
            'cache'     => $this->checkCache(),
            'storage'   => $this->checkStorage(),
            'mail'      => $this->checkMail(),
            'config'    => $this->checkConfig(),
        ];

        $overall = collect($checks)->every(fn ($c) => $c['status'] === 'pass');

        return response()->json([
            'overall' => $overall ? 'pass' : 'degraded',
            'checks'  => $checks,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check that the database connection works and is responsive.
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $dbDriver = DB::connection()->getDriverName();

            // Verify we can actually run a query
            DB::select('SELECT 1');

            $tableCount = $this->countTables();

            return [
                'status'  => 'pass',
                'message' => "Connected to {$dbDriver} database '{$dbName}'",
                'details' => [
                    'driver'      => $dbDriver,
                    'database'    => $dbName,
                    'table_count' => $tableCount,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'fail',
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check that the cache driver is operational.
     */
    private function checkCache(): array
    {
        try {
            $key = 'health:check:' . time();
            $value = 'ok';

            Cache::put($key, $value, 1);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            $driver = Config::get('cache.default', 'unknown');

            if ($retrieved === $value) {
                return [
                    'status'  => 'pass',
                    'message' => "Cache ({$driver}) is working",
                    'details' => ['driver' => $driver],
                ];
            }

            return [
                'status'  => 'warn',
                'message' => "Cache ({$driver}) store/retrieve mismatch",
                'details' => ['driver' => $driver],
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'fail',
                'message' => 'Cache check failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check that the storage/filesystem is writable.
     */
    private function checkStorage(): array
    {
        try {
            $disk = Config::get('filesystems.default', 'local');

            // Try writing a temp file
            $testFile = '_health_check_' . uniqid() . '.tmp';
            Storage::disk($disk)->put($testFile, 'health-check');
            $exists = Storage::disk($disk)->exists($testFile);

            if ($exists) {
                Storage::disk($disk)->delete($testFile);
            }

            if ($exists) {
                $freeSpace = disk_free_space(storage_path()) ?: 0;
                $freeMB = round($freeSpace / 1048576, 1);

                return [
                    'status'  => 'pass',
                    'message' => "Storage ({$disk}) is writable",
                    'details' => [
                        'disk'      => $disk,
                        'free_mb'   => $freeMB,
                    ],
                ];
            }

            return [
                'status'  => 'warn',
                'message' => "Storage ({$disk}) write/verify failed",
                'details' => ['disk' => $disk],
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'fail',
                'message' => 'Storage check failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check the mail configuration status.
     */
    private function checkMail(): array
    {
        try {
            $driver = Config::get('mail.default', 'log');
            $fromAddress = Config::get('mail.from.address', '');
            $fromName = Config::get('mail.from.name', '');

            // Check if SMTP is configured via dashboard settings
            $mailDriverSetting = Setting::getValue('mail_driver', '');
            $hasSmtpConfig = !empty(Setting::getValue('mail_host', ''));

            $details = [
                'driver'           => $driver,
                'from_address'     => $fromAddress ?: '(not set)',
                'from_name'        => $fromName ?: '(not set)',
                'dashboard_config' => $mailDriverSetting ?: 'using .env defaults',
            ];

            if ($driver === 'log') {
                return [
                    'status'  => 'warn',
                    'message' => 'Mail driver is set to "log" — emails are not actually sent',
                    'details' => $details,
                ];
            }

            if ($driver === 'smtp') {
                $host = Config::get('mail.mailers.smtp.host', '');

                if (empty($host)) {
                    return [
                        'status'  => 'warn',
                        'message' => 'SMTP driver selected but host is not configured',
                        'details' => $details,
                    ];
                }

                return [
                    'status'  => 'pass',
                    'message' => "SMTP configured ({$host})",
                    'details' => $details,
                ];
            }

            if (in_array($driver, ['sendmail', 'mailgun', 'ses', 'postmark'])) {
                return [
                    'status'  => 'pass',
                    'message' => "Mail driver '{$driver}' is configured",
                    'details' => $details,
                ];
            }

            return [
                'status'  => 'warn',
                'message' => "Mail driver '{$driver}' may not send real emails",
                'details' => $details,
            ];
        } catch (\Exception $e) {
            return [
                'status'  => 'fail',
                'message' => 'Mail check failed: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Check key application configuration values.
     */
    private function checkConfig(): array
    {
        $checks = [];

        // App URL
        $appUrl = Config::get('app.url', '');
        $checks[] = [
            'key'     => 'APP_URL',
            'value'   => $appUrl,
            'status'  => !empty($appUrl) && $appUrl !== 'http://localhost' ? 'pass' : 'warn',
        ];

        // Frontend URL
        $frontendUrl = Config::get('app.frontend_url', '');
        $checks[] = [
            'key'     => 'FRONTEND_URL',
            'value'   => $frontendUrl,
            'status'  => !empty($frontendUrl) ? 'pass' : 'warn',
        ];

        // App name
        $appName = Config::get('app.name', '');
        $checks[] = [
            'key'     => 'APP_NAME',
            'value'   => $appName,
            'status'  => !empty($appName) ? 'pass' : 'warn',
        ];

        // App environment
        $env = Config::get('app.env', '');
        $checks[] = [
            'key'     => 'APP_ENV',
            'value'   => $env,
            'status'  => in_array($env, ['production', 'local', 'testing']) ? 'pass' : 'warn',
        ];

        // Debug mode
        $debug = Config::get('app.debug', false);
        $checks[] = [
            'key'     => 'APP_DEBUG',
            'value'   => $debug ? 'true' : 'false',
            'status'  => $debug ? 'warn' : 'pass',
            'note'    => $debug ? 'Debug mode should be disabled in production' : null,
        ];

        // PHP version
        $checks[] = [
            'key'     => 'PHP_VERSION',
            'value'   => PHP_VERSION,
            'status'  => version_compare(PHP_VERSION, '8.1', '>=') ? 'pass' : 'fail',
        ];

        // Laravel version
        $checks[] = [
            'key'     => 'LARAVEL_VERSION',
            'value'   => app()->version(),
            'status'  => 'pass',
        ];

        return [
            'status'  => collect($checks)->every(fn ($c) => $c['status'] === 'pass') ? 'pass' : 'warn',
            'message' => count($checks) . ' configuration values checked',
            'details' => $checks,
        ];
    }

    /**
     * Run a targeted test for a specific service.
     */
    public function test(Request $request, string $service)
    {
        $result = match ($service) {
            'database' => $this->checkDatabase(),
            'cache'    => $this->checkCache(),
            'storage'  => $this->checkStorage(),
            default    => null,
        };

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => "Unknown service: {$service}",
            ], 422);
        }

        return response()->json([
            'success' => $result['status'] !== 'fail',
            'message' => $result['message'],
            'status'  => $result['status'],
            'details' => $result['details'],
        ]);
    }

    /**
     * Count the number of tables in the current database.
     */
    private function countTables(): int
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                return count($tables);
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $tables = DB::select('SHOW TABLES');
                return count($tables);
            }

            if ($driver === 'pgsql') {
                $tables = DB::select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'");
                return count($tables);
            }

            if ($driver === 'sqlsrv') {
                $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                return count($tables);
            }

            return 0;
        } catch (\Exception) {
            return 0;
        }
    }
}
