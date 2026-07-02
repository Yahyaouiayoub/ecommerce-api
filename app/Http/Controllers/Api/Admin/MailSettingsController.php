<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class MailSettingsController extends Controller
{
    /**
     * Get current mail settings.
     *
     * Sensitive values (password) are masked and never exposed.
     * Also includes the active (runtime) driver from config — this is
     * what the MailConfigServiceProvider has applied, which may differ
     * from the stored DB value if validation failed.
     */
    public function index()
    {
        $settings = Setting::where('group', 'mail')->get()->keyBy('key')->map->value;

        $hasPassword = !empty($settings['mail_password'] ?? '');

        return response()->json([
            'mail_driver'       => (string) ($settings['mail_driver'] ?? 'log'),
            'active_driver'     => config('mail.default', 'log'),
            'mail_host'         => (string) ($settings['mail_host'] ?? ''),
            'mail_port'         => (string) ($settings['mail_port'] ?? '587'),
            'mail_encryption'   => (string) ($settings['mail_encryption'] ?? ''),
            'mail_username'     => (string) ($settings['mail_username'] ?? ''),
            'mail_has_password' => $hasPassword,
            'mail_from_address' => (string) ($settings['mail_from_address'] ?? ''),
            'mail_from_name'    => (string) ($settings['mail_from_name'] ?? ''),
        ]);
    }

    /**
     * Update mail settings.
     *
     * The SMTP password is encrypted before storage.
     * Sends back masked settings (password never returned).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'mail_driver'       => 'nullable|string|in:smtp,sendmail,log,mailgun,ses,postmark,array',
            'mail_host'         => 'nullable|string|max:255',
            'mail_port'         => 'nullable|string|max:10',
            'mail_encryption'   => 'nullable|string|in:tls,ssl,',
            'mail_username'     => 'nullable|string|max:255',
            'mail_password'     => 'nullable|string|max:500',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name'    => 'nullable|string|max:255',
        ]);

        foreach ($validated as $key => $value) {
            // Encrypt the password before storing
            if ($key === 'mail_password' && !empty($value)) {
                $value = Crypt::encryptString($value);
            }

            // Skip empty password (preserve existing if not provided)
            if ($key === 'mail_password' && empty($value)) {
                continue;
            }

            Setting::setValue($key, $value ?? '');
        }

        // Invalidate settings cache and SMTP reachability check
        app(CacheService::class)->invalidateSettings();

        // Clear cached SMTP reachability check so the next request re-validates
        $smtpHost = $validated['mail_host'] ?? Setting::getValue('mail_host', Config::get('mail.mailers.smtp.host', '127.0.0.1'));
        $smtpPort = $validated['mail_port'] ?? Setting::getValue('mail_port', Config::get('mail.mailers.smtp.port', 587));
        Cache::forget("smtp_reachable_{$smtpHost}_{$smtpPort}");

        return response()->json([
            'message'           => 'Mail settings saved successfully',
            'mail_driver'       => Setting::getValue('mail_driver', 'log'),
            'mail_host'         => Setting::getValue('mail_host', ''),
            'mail_port'         => Setting::getValue('mail_port', '587'),
            'mail_encryption'   => Setting::getValue('mail_encryption', ''),
            'mail_username'     => Setting::getValue('mail_username', ''),
            'mail_has_password' => !empty(Setting::getValue('mail_password', '')),
            'mail_from_address' => Setting::getValue('mail_from_address', ''),
            'mail_from_name'    => Setting::getValue('mail_from_name', ''),
        ]);
    }

    /**
     * Send a test email to verify the SMTP configuration.
     */
    public function test(Request $request)
    {
        $request->validate([
            'recipient_email' => 'required|email',
        ]);

        $recipient = $request->input('recipient_email');

        try {
            // Build a minimal inline mailable
            Mail::raw(
                'This is a test email from your e-commerce application. If you receive this, your SMTP configuration is working correctly.',
                function ($message) use ($recipient) {
                    $message->to($recipient)
                            ->subject('SMTP Test Email — ' . config('app.name'));
                }
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to ' . $recipient,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 422);
        }
    }
}
