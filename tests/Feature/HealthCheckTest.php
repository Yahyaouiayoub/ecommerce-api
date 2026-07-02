<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HealthCheckTest extends TestCase
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
            'email' => 'admin@health-test.com',
        ]);

        $this->client = User::factory()->create([
            'email' => 'client@health-test.com',
        ]);

        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->clientToken = $this->client->createToken('test')->plainTextToken;
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function clientHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->clientToken];
    }

    // =========================
    // INDEX (GET /admin/health)
    // =========================

    public function test_health_check_index_returns_all_checks(): void
    {
        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'overall',
                'checks' => [
                    'database' => ['status', 'message', 'details'],
                    'cache'    => ['status', 'message', 'details'],
                    'storage'  => ['status', 'message', 'details'],
                    'mail'     => ['status', 'message', 'details'],
                    'config'   => ['status', 'message', 'details'],
                ],
                'timestamp',
            ]);
    }

    public function test_health_check_mail_check_returns_warn_with_log_driver(): void
    {
        // Default config uses 'log' driver in testing
        Config::set('mail.default', 'log');

        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('warn', $response->json('checks.mail.status'));
        $this->assertStringContainsString('log', strtolower($response->json('checks.mail.message')));
    }

    public function test_health_check_mail_check_shows_from_address(): void
    {
        Config::set('mail.from.address', 'noreply@myapp.com');
        Config::set('mail.from.name', 'My App');

        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('noreply@myapp.com', $response->json('checks.mail.details.from_address'));
        $this->assertEquals('My App', $response->json('checks.mail.details.from_name'));
    }

    public function test_health_check_mail_check_detects_smtp_without_host_as_warn(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', '');

        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('warn', $response->json('checks.mail.status'));
        $this->assertStringContainsString('host is not configured', $response->json('checks.mail.message'));
    }

    public function test_health_check_mail_check_detects_smtp_with_host_as_pass(): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.sendgrid.net');

        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('pass', $response->json('checks.mail.status'));
        $this->assertStringContainsString('smtp.sendgrid.net', $response->json('checks.mail.message'));
    }

    public function test_health_check_mail_check_detects_dashboard_config(): void
    {
        Setting::setValue('mail_driver', 'smtp');

        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('smtp', $response->json('checks.mail.details.dashboard_config'));
    }

    public function test_health_check_mail_check_reports_unknown_driver_as_warn(): void
    {
        Config::set('mail.default', 'some-unknown-driver');

        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('warn', $response->json('checks.mail.status'));
    }

    public function test_health_check_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/health')->assertUnauthorized();
    }

    public function test_health_check_index_requires_admin_role(): void
    {
        $this->getJson('/api/admin/health', $this->clientHeaders())
            ->assertForbidden();
    }

    // =========================
    // TEST ENDPOINT (POST /admin/health/test/{service})
    // =========================

    public function test_health_test_endpoint_runs_database_check(): void
    {
        $response = $this->postJson('/api/admin/health/test/database', [], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'status', 'details']);
        $this->assertEquals('pass', $response->json('status'));
        $this->assertTrue($response->json('success'));
    }

    public function test_health_test_endpoint_runs_cache_check(): void
    {
        $response = $this->postJson('/api/admin/health/test/cache', [], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'status', 'details']);
        $this->assertEquals('pass', $response->json('status'));
        $this->assertTrue($response->json('success'));
    }

    public function test_health_test_endpoint_runs_storage_check(): void
    {
        $response = $this->postJson('/api/admin/health/test/storage', [], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'status', 'details']);
        $this->assertEquals('pass', $response->json('status'));
        $this->assertTrue($response->json('success'));
    }

    public function test_health_test_endpoint_returns_422_for_unknown_service(): void
    {
        $response = $this->postJson('/api/admin/health/test/unknown-service', [], $this->adminHeaders());

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Unknown service: unknown-service',
            ]);
    }

    public function test_health_test_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/admin/health/test/database')->assertUnauthorized();
    }

    public function test_health_test_endpoint_requires_admin_role(): void
    {
        $this->postJson('/api/admin/health/test/database', [], $this->clientHeaders())
            ->assertForbidden();
    }

    // =========================
    // STORAGE CHECK
    // =========================

    public function test_health_check_storage_detects_disk(): void
    {
        $response = $this->getJson('/api/admin/health', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('local', $response->json('checks.storage.details.disk'));
        $this->assertIsNumeric($response->json('checks.storage.details.free_mb'));
    }
}
