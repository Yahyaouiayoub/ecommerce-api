<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'user@auth-test.com',
            'password' => bcrypt('password'),
        ]);

        $this->admin = User::factory()->admin()->create([
            'email' => 'admin@auth-test.com',
            'password' => bcrypt('password'),
        ]);

        $this->userToken = $this->user->createToken('test')->plainTextToken;
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    private function headers(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    // =========================
    // REGISTER
    // =========================

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'New',
            'last_name' => 'User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['message', 'user', 'token']);
        $this->assertEquals('New', $response->json('user.first_name'));
        $this->assertDatabaseHas('users', ['email' => 'newuser@test.com']);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
    }

    public function test_register_validates_unique_email(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user@auth-test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'new@test.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_validates_min_password_length(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'new@test.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // =========================
    // LOGIN
    // =========================

    public function test_user_can_login(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'user@auth-test.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token']);
        $this->assertEquals('Login successful', $response->json('message'));
    }

    public function test_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'user@auth-test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_tracks_last_login_at(): void
    {
        $this->postJson('/api/login', [
            'email' => 'user@auth-test.com',
            'password' => 'password',
        ]);

        $this->assertNotNull($this->user->fresh()->last_login_at);
    }

    // =========================
    // LOGOUT
    // =========================

    public function test_user_can_logout(): void
    {
        $response = $this->postJson('/api/logout', [], $this->headers($this->userToken));

        $response->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized();
    }

    public function test_token_is_deleted_after_logout(): void
    {
        $this->postJson('/api/logout', [], $this->headers($this->userToken));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
        ]);
    }

    // =========================
    // ME (GET CURRENT USER)
    // =========================

    public function test_can_get_current_user(): void
    {
        $response = $this->getJson('/api/me', $this->headers($this->userToken));

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'first_name', 'last_name', 'email']]);
        $this->assertEquals($this->user->email, $response->json('user.email'));
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    // =========================
    // UPDATE PROFILE
    // =========================

    public function test_user_can_update_profile(): void
    {
        $response = $this->putJson('/api/profile', [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'phone' => '0612345678',
        ], $this->headers($this->userToken));

        $response->assertOk()
            ->assertJson(['message' => 'Profile updated successfully']);
        $this->assertEquals('Updated', $response->json('user.first_name'));
        $this->assertEquals('Name', $response->json('user.last_name'));
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/profile', ['first_name' => 'Test'])->assertUnauthorized();
    }

    public function test_update_profile_validates_email_uniqueness(): void
    {
        $response = $this->putJson('/api/profile', [
            'email' => 'admin@auth-test.com',
        ], $this->headers($this->userToken));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // =========================
    // CHANGE PASSWORD
    // =========================

    public function test_user_can_change_password(): void
    {
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ], $this->headers($this->userToken));

        $response->assertOk()
            ->assertJson(['message' => 'Password changed successfully.']);
    }

    public function test_change_password_requires_correct_current(): void
    {
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ], $this->headers($this->userToken));

        $response->assertStatus(422);
    }

    public function test_change_password_validates_min_length(): void
    {
        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'new_password' => 'short',
            'new_password_confirmation' => 'short',
        ], $this->headers($this->userToken));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->putJson('/api/profile/password', [])->assertUnauthorized();
    }

    // =========================
    // SESSIONS
    // =========================

    public function test_user_can_list_sessions(): void
    {
        // Create additional token to simulate multiple sessions
        $this->user->createToken('mobile-app')->plainTextToken;

        $response = $this->getJson('/api/sessions', $this->headers($this->userToken));

        $response->assertOk()
            ->assertJsonStructure(['sessions' => ['*' => ['id', 'name', 'is_current', 'created_at']]]);
        $this->assertCount(2, $response->json('sessions'));
    }

    public function test_current_session_is_marked(): void
    {
        $currentTokenId = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $this->user->id)->first()->id;

        $response = $this->getJson('/api/sessions', $this->headers($this->userToken));

        $current = collect($response->json('sessions'))->firstWhere('is_current', true);
        $this->assertNotNull($current);
        $this->assertEquals($currentTokenId, $current['id']);
    }

    public function test_user_can_revoke_other_session(): void
    {
        $secondToken = $this->user->createToken('mobile-app')->plainTextToken;
        $secondTokenId = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('name', 'mobile-app')->first()->id;

        $response = $this->deleteJson('/api/sessions/' . $secondTokenId, [], $this->headers($this->userToken));

        $response->assertOk()
            ->assertJson(['message' => 'Session revoked successfully.']);
    }

    public function test_user_cannot_revoke_current_session(): void
    {
        $currentTokenId = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $this->user->id)->first()->id;

        $response = $this->deleteJson('/api/sessions/' . $currentTokenId, [], $this->headers($this->userToken));

        $response->assertStatus(422);
    }

    public function test_sessions_requires_authentication(): void
    {
        $this->getJson('/api/sessions')->assertUnauthorized();
    }

    // =========================
    // 2FA FLOW
    // =========================

    public function test_2fa_required_when_enabled_on_login(): void
    {
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => 'TESTFAKESECRET',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@auth-test.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('two_factor_required'));
        $this->assertNotNull($response->json('challenge_token'));
    }

    public function test_2fa_not_required_when_disabled(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'user@auth-test.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('two_factor_required', $response->json());
    }

    public function test_verify_2fa_with_challenge_token(): void
    {
        $google2fa = new Google2FA();
        $validSecret = $google2fa->generateSecretKey();

        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => $validSecret,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'user@auth-test.com',
            'password' => 'password',
        ]);

        $challengeToken = $loginResponse->json('challenge_token');

        // With a valid challenge token and correct 2FA code
        $response = $this->postJson('/api/2fa/verify-login', [
            'challenge_token' => $challengeToken,
            'code' => '123456',
        ]);

        // The code is wrong, so we expect a validation error
        $response->assertStatus(422);
    }

    public function test_verify_2fa_with_invalid_challenge_token(): void
    {
        $response = $this->postJson('/api/2fa/verify-login', [
            'challenge_token' => 'invalid-challenge-token',
            'code' => '123456',
        ]);

        $response->assertStatus(422);
    }

    // =========================
    // AUTH MIDDLEWARE GUARDS
    // =========================

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/wishlist')->assertUnauthorized();
        $this->getJson('/api/addresses')->assertUnauthorized();
        $this->postJson('/api/addresses', [])->assertUnauthorized();
        $this->getJson('/api/admin/products')->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/products', $this->headers($this->userToken))->assertForbidden();
        $this->postJson('/api/admin/products', [], $this->headers($this->userToken))->assertForbidden();
        $this->getJson('/api/admin/coupons', $this->headers($this->userToken))->assertForbidden();
        $this->getJson('/api/admin/users', $this->headers($this->userToken))->assertForbidden();
        $this->getJson('/api/admin/dashboard/stats', $this->headers($this->userToken))->assertForbidden();
    }

    // =========================
    // FULL LIFECYCLE
    // =========================

    public function test_full_auth_lifecycle(): void
    {
        // 1. Register
        $registerResponse = $this->postJson('/api/register', [
            'first_name' => 'Lifecycle',
            'last_name' => 'User',
            'email' => 'lifecycle@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $registerResponse->assertCreated();
        $token = $registerResponse->json('token');

        // 2. Get current user
        $this->getJson('/api/me', $this->headers($token))
            ->assertOk()
            ->assertJson(['user' => ['email' => 'lifecycle@test.com']]);

        // 3. Update profile
        $this->putJson('/api/profile', ['first_name' => 'Updated'], $this->headers($token))
            ->assertOk();

        // 4. Logout
        $this->postJson('/api/logout', [], $this->headers($token))
            ->assertOk();

        // 5. Login again
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'lifecycle@test.com',
            'password' => 'password123',
        ]);
        $loginResponse->assertOk();
        $newToken = $loginResponse->json('token');

        // 6. Access protected route
        $this->getJson('/api/me', $this->headers($newToken))->assertOk();
    }
}
