<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private string $adminToken;
    private string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin@mail-test.com',
        ]);

        $this->client = User::factory()->create([
            'email' => 'client@mail-test.com',
        ]);

        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->clientToken = $this->client->createToken('test')->plainTextToken;
    }

    // =========================
    // HELPERS
    // =========================

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function clientHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->clientToken];
    }

    // =========================
    // INDEX (GET /admin/settings/mail)
    // =========================

    public function test_admin_can_get_mail_settings_with_defaults(): void
    {
        $response = $this->getJson('/api/admin/settings/mail', $this->adminHeaders());

        $response->assertOk()
            ->assertExactJson([
                'mail_driver'       => 'log',
                'active_driver'     => 'array',
                'mail_host'         => '',
                'mail_port'         => '587',
                'mail_encryption'   => '',
                'mail_username'     => '',
                'mail_has_password' => false,
                'mail_from_address' => '',
                'mail_from_name'    => '',
            ]);
    }

    public function test_admin_can_get_mail_settings_with_stored_values(): void
    {
        Setting::setValue('mail_driver', 'smtp');
        Setting::setValue('mail_host', 'smtp.gmail.com');
        Setting::setValue('mail_port', '465');
        Setting::setValue('mail_encryption', 'ssl');
        Setting::setValue('mail_username', 'user@gmail.com');
        Setting::setValue('mail_password', Crypt::encryptString('secret123'));
        Setting::setValue('mail_from_address', 'noreply@myapp.com');
        Setting::setValue('mail_from_name', 'My App');

        $response = $this->getJson('/api/admin/settings/mail', $this->adminHeaders());

        $response->assertOk()
            ->assertJson([
                'mail_driver'       => 'smtp',
                'mail_host'         => 'smtp.gmail.com',
                'mail_port'         => '465',
                'mail_encryption'   => 'ssl',
                'mail_username'     => 'user@gmail.com',
                'mail_has_password' => true,
                'mail_from_address' => 'noreply@myapp.com',
                'mail_from_name'    => 'My App',
            ]);

        // Verify password is NEVER returned in the response
        $response->assertJsonMissing(['mail_password']);
    }

    public function test_get_mail_settings_requires_authentication(): void
    {
        $this->getJson('/api/admin/settings/mail')->assertUnauthorized();
    }

    public function test_get_mail_settings_requires_admin_role(): void
    {
        $this->getJson('/api/admin/settings/mail', $this->clientHeaders())
            ->assertForbidden();
    }

    // =========================
    // UPDATE (PUT /admin/settings/mail)
    // =========================

    public function test_admin_can_update_all_mail_settings(): void
    {
        $response = $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.gmail.com',
            'mail_port'         => '587',
            'mail_encryption'   => 'tls',
            'mail_username'     => 'user@gmail.com',
            'mail_password'     => 'my-secret-password',
            'mail_from_address' => 'noreply@myapp.com',
            'mail_from_name'    => 'My Store',
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJson([
                'message'           => 'Mail settings saved successfully',
                'mail_driver'       => 'smtp',
                'mail_host'         => 'smtp.gmail.com',
                'mail_port'         => '587',
                'mail_encryption'   => 'tls',
                'mail_username'     => 'user@gmail.com',
                'mail_has_password' => true,
                'mail_from_address' => 'noreply@myapp.com',
                'mail_from_name'    => 'My Store',
            ]);

        // Verify password is never returned
        $response->assertJsonMissing(['mail_password']);

        // Verify the password was encrypted in the database
        $storedPassword = Setting::getValue('mail_password');
        $this->assertNotEmpty($storedPassword);
        $this->assertNotEquals('my-secret-password', $storedPassword);
        $this->assertEquals('my-secret-password', Crypt::decryptString($storedPassword));

        // Verify other settings stored as plain text
        $this->assertEquals('smtp', Setting::getValue('mail_driver'));
        $this->assertEquals('smtp.gmail.com', Setting::getValue('mail_host'));
        $this->assertEquals('user@gmail.com', Setting::getValue('mail_username'));
    }

    public function test_admin_can_update_partial_settings(): void
    {
        // Set initial values
        Setting::setValue('mail_driver', 'smtp');
        Setting::setValue('mail_host', 'smtp.gmail.com');
        Setting::setValue('mail_password', Crypt::encryptString('original-password'));

        // Update only host and from name
        $response = $this->putJson('/api/admin/settings/mail', [
            'mail_host'      => 'smtp.mailgun.org',
            'mail_from_name' => 'Updated Store',
        ], $this->adminHeaders());

        $response->assertOk();

        // Check that updated values changed
        $this->assertEquals('smtp.mailgun.org', Setting::getValue('mail_host'));
        $this->assertEquals('Updated Store', Setting::getValue('mail_from_name'));

        // Check that untouched values remain
        $this->assertEquals('smtp', Setting::getValue('mail_driver'));

        // Check that the original password is preserved (not overwritten)
        $this->assertEquals('original-password', Crypt::decryptString(Setting::getValue('mail_password')));
    }

    public function test_empty_password_preserves_existing(): void
    {
        // Set an initial encrypted password
        Setting::setValue('mail_password', Crypt::encryptString('existing-password'));

        // Send update without password
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'smtp',
        ], $this->adminHeaders());

        // Password should still be the original
        $this->assertEquals('existing-password', Crypt::decryptString(Setting::getValue('mail_password')));
    }

    public function test_update_validates_invalid_driver(): void
    {
        $response = $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'invalid-driver',
        ], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mail_driver']);
    }

    public function test_update_validates_invalid_encryption(): void
    {
        $response = $this->putJson('/api/admin/settings/mail', [
            'mail_encryption' => 'invalid',
        ], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mail_encryption']);
    }

    public function test_update_validates_from_email_format(): void
    {
        $response = $this->putJson('/api/admin/settings/mail', [
            'mail_from_address' => 'not-an-email',
        ], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mail_from_address']);
    }

    public function test_update_allows_empty_encryption(): void
    {
        $response = $this->putJson('/api/admin/settings/mail', [
            'mail_encryption' => '',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('', Setting::getValue('mail_encryption'));
    }

    public function test_update_settings_requires_authentication(): void
    {
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'smtp',
        ])->assertUnauthorized();
    }

    public function test_update_settings_requires_admin_role(): void
    {
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'smtp',
        ], $this->clientHeaders())->assertForbidden();
    }

    // =========================
    // TEST (POST /admin/settings/mail/test)
    // =========================

    public function test_admin_can_send_test_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Test email sent successfully to admin@example.com',
            ]);
    }

    public function test_test_email_validates_required_recipient(): void
    {
        $response = $this->postJson('/api/admin/settings/mail/test', [], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_email']);
    }

    public function test_test_email_validates_email_format(): void
    {
        $response = $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'invalid-email',
        ], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_email']);
    }

    public function test_test_email_requires_authentication(): void
    {
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'test@example.com',
        ])->assertUnauthorized();
    }

    public function test_test_email_requires_admin_role(): void
    {
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'test@example.com',
        ], $this->clientHeaders())->assertForbidden();
    }

    // =========================
    // END-TO-END: FULL LIFECYCLE
    // =========================

    public function test_full_mail_settings_lifecycle(): void
    {
        // 1. Start with defaults
        $this->getJson('/api/admin/settings/mail', $this->adminHeaders())
            ->assertOk()
            ->assertJson([
                'mail_driver'       => 'log',
                'mail_has_password' => false,
            ]);

        // 2. Update with all settings
        $updateResponse = $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.sendgrid.net',
            'mail_port'         => '587',
            'mail_encryption'   => 'tls',
            'mail_username'     => 'apikey',
            'mail_password'     => 'SG.sendgrid-api-key',
            'mail_from_address' => 'orders@mystore.com',
            'mail_from_name'    => 'My Store',
        ], $this->adminHeaders());

        $updateResponse->assertOk()
            ->assertJson([
                'mail_driver'       => 'smtp',
                'mail_host'         => 'smtp.sendgrid.net',
                'mail_port'         => '587',
                'mail_encryption'   => 'tls',
                'mail_username'     => 'apikey',
                'mail_has_password' => true,
                'mail_from_address' => 'orders@mystore.com',
                'mail_from_name'    => 'My Store',
            ]);

        // Verify no password leaked
        $updateResponse->assertJsonMissing(['mail_password']);

        // 3. Verify stored in DB correctly
        $this->assertEquals('smtp', Setting::getValue('mail_driver'));
        $this->assertEquals('smtp.sendgrid.net', Setting::getValue('mail_host'));

        $storedPassword = Setting::getValue('mail_password');
        $this->assertEquals('SG.sendgrid-api-key', Crypt::decryptString($storedPassword));

        // 4. Read back — must show same values (password masked)
        $this->getJson('/api/admin/settings/mail', $this->adminHeaders())
            ->assertOk()
            ->assertJson([
                'mail_driver'       => 'smtp',
                'mail_host'         => 'smtp.sendgrid.net',
                'mail_port'         => '587',
                'mail_encryption'   => 'tls',
                'mail_username'     => 'apikey',
                'mail_has_password' => true,
                'mail_from_address' => 'orders@mystore.com',
                'mail_from_name'    => 'My Store',
            ]);
    }

    // =========================
    // SAVE-AND-TEST: SEQUENTIAL FLOW
    // =========================

    public function test_save_then_test_sequential_flow_succeeds(): void
    {
        // 1. Save SMTP settings
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.sendgrid.net',
            'mail_port'         => '587',
            'mail_encryption'   => 'tls',
            'mail_username'     => 'apikey',
            'mail_password'     => 'SG.sendgrid-key',
            'mail_from_address' => 'orders@mystore.com',
            'mail_from_name'    => 'My Store',
        ], $this->adminHeaders())
            ->assertOk()
            ->assertJson(['message' => 'Mail settings saved successfully']);

        // 2. Send test email using the freshly saved settings
        $response = $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Test email sent successfully to admin@example.com',
            ]);

        // 3. Verify settings are unchanged after the test
        $this->assertEquals('smtp', Setting::getValue('mail_driver'));
        $this->assertEquals('smtp.sendgrid.net', Setting::getValue('mail_host'));
    }

    public function test_save_then_test_preserves_settings_after_test(): void
    {
        Mail::fake();

        // 1. Save SMTP settings
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.mailgun.org',
            'mail_port'         => '587',
            'mail_username'     => 'postmaster@mg.org',
            'mail_password'     => 'mg-key',
            'mail_from_address' => 'noreply@store.com',
            'mail_from_name'    => 'Store',
        ], $this->adminHeaders())->assertOk();

        // 2. Send test email
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'test@example.com',
        ], $this->adminHeaders())->assertOk();

        // 3. Verify settings are unchanged after the test (not corrupted)
        $this->assertEquals('smtp', Setting::getValue('mail_driver'));
        $this->assertEquals('smtp.mailgun.org', Setting::getValue('mail_host'));
        $this->assertEquals('noreply@store.com', Setting::getValue('mail_from_address'));
        $this->assertEquals('Store', Setting::getValue('mail_from_name'));
    }

    public function test_save_with_invalid_driver_then_test_fails_gracefully(): void
    {
        // 1. Attempt to save with invalid driver — should fail validation
        $saveResponse = $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'invalid-driver',
            'mail_host'   => 'smtp.spam.com',
        ], $this->adminHeaders());

        $saveResponse->assertStatus(422)
            ->assertJsonValidationErrors(['mail_driver']);

        // 2. Test email should still work (settings weren't saved)
        $testResponse = $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ], $this->adminHeaders());

        $testResponse->assertOk()
            ->assertJson(['success' => true]);

        // 3. Verify DB was not polluted by the failed save
        $this->assertEmpty(Setting::getValue('mail_host', ''));
    }

    public function test_save_successful_but_test_returns_error_on_connection_failure(): void
    {
        // 1. Save valid SMTP settings
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => '192.0.2.1',
            'mail_port'         => '1',
            'mail_username'     => 'apikey',
            'mail_password'     => 'SG.key',
            'mail_from_address' => 'orders@store.com',
        ], $this->adminHeaders())->assertOk();

        // 2. Override runtime config to use the unreachable SMTP host
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', '192.0.2.1');
        Config::set('mail.mailers.smtp.port', 1);
        Config::set('mail.mailers.smtp.timeout', 2);

        // 3. Test email should fail — connection to 192.0.2.1:1 will time out
        $response = $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_save_then_test_with_non_smtp_driver_uses_default_mailer(): void
    {
        // 1. Save with 'log' driver (stays on default testing 'array' driver)
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'log',
            'mail_from_address' => 'noreply@store.com',
            'mail_from_name'    => 'My Store',
        ], $this->adminHeaders())->assertOk();

        // 2. Test email should still succeed — uses the runtime driver
        $response = $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJson(['success' => true]);

        // 3. Verify from address was correctly saved
        $this->assertEquals('noreply@store.com', Setting::getValue('mail_from_address'));
    }

    public function test_save_then_test_without_recipient_returns_validation_error(): void
    {
        // 1. Save valid settings
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'smtp',
            'mail_host'   => 'smtp.gmail.com',
        ], $this->adminHeaders())->assertOk();

        // 2. Test without recipient email — should fail validation
        $response = $this->postJson('/api/admin/settings/mail/test', [], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_email']);
    }

    public function test_save_then_test_requires_auth_for_both_endpoints(): void
    {
        // Save without auth — should fail
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'smtp',
        ])->assertUnauthorized();

        // Test without auth — should fail
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ])->assertUnauthorized();
    }

    public function test_save_then_test_requires_admin_for_both_endpoints(): void
    {
        // Save as non-admin — should fail
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver' => 'smtp',
        ], $this->clientHeaders())->assertForbidden();

        // Test as non-admin — should fail
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'admin@example.com',
        ], $this->clientHeaders())->assertForbidden();
    }

    public function test_save_then_test_multiple_times_preserves_state(): void
    {
        // 1. Save initial settings
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.mailgun.org',
            'mail_from_address' => 'first@store.com',
        ], $this->adminHeaders())->assertOk();

        // 2. First test
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'a@example.com',
        ], $this->adminHeaders())->assertOk();

        // 3. Update settings
        $this->putJson('/api/admin/settings/mail', [
            'mail_host'         => 'smtp.sendgrid.net',
            'mail_from_address' => 'updated@store.com',
        ], $this->adminHeaders())->assertOk();

        // 4. Second test — should still succeed with updated settings
        $this->postJson('/api/admin/settings/mail/test', [
            'recipient_email' => 'b@example.com',
        ], $this->adminHeaders())->assertOk();

        // 5. Verify the final settings are the updated ones
        $this->assertEquals('smtp.sendgrid.net', Setting::getValue('mail_host'));
        $this->assertEquals('updated@store.com', Setting::getValue('mail_from_address'));
    }

    // =========================
    // END-TO-END: SAVE + NEXT-REQUEST READINESS
    // =========================

    public function test_saved_mail_settings_are_read_by_provider_on_next_boot(): void
    {
        // This test verifies the full pipeline:
        //   API save → DB persist → Cache invalidation → Provider can read on next boot
        //
        // MailConfigServiceProvider runs at app boot (before each request).
        // After saving via the API, the provider hasn't run yet for the current
        // boot cycle — the new values take effect on the NEXT request.
        // We verify all the intermediate state is correct.

        // Pre-populate SMTP reachability cache so we can prove it gets cleared.
        // (The cache key normally doesn't exist in testing since the default
        //  mail driver is 'log' and the reachability check never runs.)
        Cache::put('smtp_reachable_smtp.mailgun.org_465', true, 300);
        $this->assertTrue(
            Cache::has('smtp_reachable_smtp.mailgun.org_465'),
            'Pre-populated cache before save'
        );

        // 1. Save SMTP settings via the API
        $this->putJson('/api/admin/settings/mail', [
            'mail_driver'       => 'smtp',
            'mail_host'         => 'smtp.mailgun.org',
            'mail_port'         => '465',
            'mail_encryption'   => 'ssl',
            'mail_username'     => 'postmaster@mg.mystore.com',
            'mail_password'     => 'mg-secret-key-12345',
            'mail_from_address' => 'noreply@mystore.com',
            'mail_from_name'    => 'My E-Commerce',
        ], $this->adminHeaders())->assertOk();

        // 2. Verify SMTP reachability cache was cleared after save
        $this->assertFalse(
            Cache::has('smtp_reachable_smtp.mailgun.org_465'),
            'SMTP reachability cache cleared after save'
        );

        // 3. Verify DB has the correct stored values — these are what
        //    MailConfigServiceProvider::overrideMailConfig() reads on the
        //    next boot cycle.
        $this->assertEquals('smtp', Setting::getValue('mail_driver'), 'DB: mail_driver');
        $this->assertEquals('smtp.mailgun.org', Setting::getValue('mail_host'), 'DB: mail_host');
        $this->assertEquals('465', Setting::getValue('mail_port'), 'DB: mail_port');
        $this->assertEquals('ssl', Setting::getValue('mail_encryption'), 'DB: mail_encryption');
        $this->assertEquals('postmaster@mg.mystore.com', Setting::getValue('mail_username'), 'DB: mail_username');
        $this->assertEquals('noreply@mystore.com', Setting::getValue('mail_from_address'), 'DB: mail_from_address');
        $this->assertEquals('My E-Commerce', Setting::getValue('mail_from_name'), 'DB: mail_from_name');

        // Verify password was encrypted in transit
        $storedPassword = Setting::getValue('mail_password');
        $this->assertNotEmpty($storedPassword);
        $this->assertNotEquals('mg-secret-key-12345', $storedPassword);
        $this->assertEquals('mg-secret-key-12345', Crypt::decryptString($storedPassword));

        // 4. Verify the GET response shows the split between DB-stored and
        //    runtime-active drivers — proving the concept works:
        //      • mail_driver    = what's in the DB (smtp)
        //      • active_driver  = what's actually active at runtime (array
        //        in testing env, since the provider ran at boot before our save)
        $getResponse = $this->getJson('/api/admin/settings/mail', $this->adminHeaders());
        $getResponse->assertOk()
            ->assertJson([
                'mail_driver'       => 'smtp',
                'active_driver'     => 'array',
                'mail_host'         => 'smtp.mailgun.org',
                'mail_port'         => '465',
                'mail_encryption'   => 'ssl',
                'mail_username'     => 'postmaster@mg.mystore.com',
                'mail_has_password' => true,
                'mail_from_address' => 'noreply@mystore.com',
                'mail_from_name'    => 'My E-Commerce',
            ]);
    }
}
