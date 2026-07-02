<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Crypt;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * Reads SMTP/email settings from the database and overrides
     * the runtime mail configuration so all outgoing emails
     * (verification, password reset, order confirmations, invoices, etc.)
     * use the dashboard-configured mailer.
     *
     * Before applying SMTP settings, the provider verifies the SMTP
     * server is reachable via a lightweight TCP socket check. If the
     * connection fails, the existing mail config is preserved and a
     * warning is logged — preventing accidental misconfiguration from
     * breaking all outgoing emails.
     */
    public function boot(): void
    {
        $this->overrideMailConfig();
    }

    private function overrideMailConfig(): void
    {
        try {
            $driver = Setting::getValue('mail_driver', '');
        } catch (\Exception) {
            return; // Database not ready yet (e.g. during tests or fresh migration)
        }

        if (empty($driver)) {
            return; // Not yet configured — keep default (log)
        }

        Config::set('mail.default', $driver);

        if ($driver === 'smtp') {
            $host = Setting::getValue('mail_host', Config::get('mail.mailers.smtp.host', '127.0.0.1'));
            $port = (int) Setting::getValue('mail_port', Config::get('mail.mailers.smtp.port', 587));

            // Verify SMTP server is reachable before applying the config
            if (!$this->isSmtpReachable($host, $port)) {
                logger()->warning('SMTP server is not reachable — keeping existing mail config', [
                    'host' => $host,
                    'port' => $port,
                ]);
                return;
            }

            $password = '';
            $encrypted = Setting::getValue('mail_password', '');
            if ($encrypted) {
                try {
                    $password = Crypt::decryptString($encrypted);
                } catch (\Exception) {
                    $password = '';
                }
            }

            Config::set('mail.mailers.smtp', [
                'transport'    => 'smtp',
                'host'         => $host,
                'port'         => $port,
                'encryption'   => Setting::getValue('mail_encryption', null),
                'username'     => Setting::getValue('mail_username', Config::get('mail.mailers.smtp.username', '')),
                'password'     => $password,
                'timeout'      => Config::get('mail.mailers.smtp.timeout'),
                'local_domain' => Config::get('mail.mailers.smtp.local_domain'),
            ]);
        }

        // Global from address & name
        $fromAddress = Setting::getValue('mail_from_address', '');
        $fromName    = Setting::getValue('mail_from_name', '');

        if (!empty($fromAddress)) {
            Config::set('mail.from.address', $fromAddress);
        }
        if (!empty($fromName)) {
            Config::set('mail.from.name', $fromName);
        }
    }

    /**
     * Lightweight SMTP reachability check via TCP socket connection.
     *
     * Attempts to open a TCP socket to the SMTP host:port with a 3-second
     * timeout. The result is cached for 5 minutes to avoid hammering the
     * SMTP server on every request.
     *
     * Cache is automatically invalidated when mail settings are saved
     * via MailSettingsController::update().
     */
    private function isSmtpReachable(string $host, int $port): bool
    {
        $cacheKey = "smtp_reachable_{$host}_{$port}";

        return Cache::remember($cacheKey, 300, function () use ($host, $port) {
            $connection = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errorCode,
                $errorMessage,
                3, // 3-second timeout
            );

            if ($connection) {
                fclose($connection);
                return true;
            }

            logger()->warning('SMTP server unreachable during reachability check', [
                'host'        => $host,
                'port'        => $port,
                'error_code'  => $errorCode,
                'error'       => $errorMessage,
            ]);

            return false;
        });
    }
}
