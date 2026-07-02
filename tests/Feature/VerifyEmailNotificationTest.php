<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VerifyEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@verify-email.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
    }

    // =========================
    // NOTIFICATION CAN BE SENT (no fatal error)
    // =========================

    public function test_notification_sends_without_error(): void
    {
        Notification::fake();

        $this->user->notify(new VerifyEmailNotification());

        Notification::assertSentTo(
            $this->user,
            VerifyEmailNotification::class
        );
    }

    // =========================
    // MAIL CONTENT
    // =========================

    public function test_mail_has_correct_subject(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertEquals('Verify Your Email Address', $mail->subject);
    }

    public function test_mail_has_correct_greeting(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertEquals('Welcome!', $mail->greeting);
    }

    public function test_mail_has_intro_line(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertContains(
            'Please click the button below to verify your email address.',
            $mail->introLines
        );
    }

    public function test_mail_has_outro_line(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertContains(
            'If you did not create an account, no further action is required.',
            $mail->outroLines
        );
    }

    public function test_mail_has_action_button_with_correct_text(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertEquals('Verify Email Address', $mail->actionText);
    }

    public function test_mail_action_url_contains_frontend_url(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $frontendUrl = Config::get('app.frontend_url', 'http://localhost:3000');
        $this->assertStringContainsString($frontendUrl, $mail->actionUrl);
    }

    public function test_mail_action_url_contains_verify_email_path(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('/verify-email', $mail->actionUrl);
    }

    public function test_mail_action_url_contains_signed_verification_url(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        // The action URL should contain the verification URL (signed) as a query param
        $this->assertStringContainsString('url=', $mail->actionUrl);
        $this->assertStringContainsString('verify', $mail->actionUrl);
    }

    public function test_mail_uses_frontend_url_from_config(): void
    {
        // Override the config for this test
        Config::set('app.frontend_url', 'https://custom-frontend.com');

        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('https://custom-frontend.com', $mail->actionUrl);
    }

    public function test_mail_uses_frontend_url_from_env_default_when_no_custom_url(): void
    {
        // The config file defaults to http://localhost:3000 when FRONTEND_URL env is not set
        // This verifies the default from the config file is used correctly
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('http://localhost:3000', $mail->actionUrl);
    }

    // =========================
    // VERIFICATION URL IS VALID
    // =========================

    public function test_verification_url_contains_user_id(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        // The verification URL should reference the user ID in the path
        $this->assertStringContainsString((string) $this->user->getKey(), $mail->actionUrl);
    }

    public function test_verification_url_contains_signed_parameters_url_encoded(): void
    {
        $notification = new VerifyEmailNotification();
        $mail = $notification->toMail($this->user);

        // The signed URL parameters are URL-encoded inside the url= query param
        // '=' becomes '%3D' and '&' becomes '%26' when inside a query parameter value
        $this->assertStringContainsString('signature%3D', $mail->actionUrl);
        $this->assertStringContainsString('expires%3D', $mail->actionUrl);
    }

    // =========================
    // NOTIFICATION IS QUEUEABLE
    // =========================

    public function test_notification_uses_queueable_trait(): void
    {
        $notification = new VerifyEmailNotification();
        $this->assertContains(
            \Illuminate\Bus\Queueable::class,
            class_uses($notification)
        );
    }

    // =========================
    // FULL FLOW: SEND VIA CONTROLLER
    // =========================

    public function test_registration_triggers_verification_email(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'new-register@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'new-register@test.com')->first();

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function ($notification, $channels) {
                return in_array('mail', $channels);
            }
        );
    }

    public function test_registration_does_not_block_when_mail_fails(): void
    {
        // Force a mail failure by using an invalid mailer
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'unreachable-host');

        $response = $this->postJson('/api/register', [
            'first_name' => 'Resilient',
            'last_name' => 'User',
            'email' => 'resilient@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Registration should still succeed even if email sending fails
        $response->assertCreated()
            ->assertJsonStructure(['message', 'user', 'token']);
    }
}
