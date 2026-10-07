<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_then_choosing_languages(): void
    {
        $registered = $this->graphQL(
            'mutation {
                register(name: "Анна", email: "anna@example.com", password: "password12", native_language: "ru") {
                    token user { name native_language studied_languages }
                }
            }',
        )->json('data.register');

        $this->assertNotEmpty($registered['token']);
        $this->assertSame('ru', $registered['user']['native_language']);
        $this->assertSame([], $registered['user']['studied_languages'], 'languages are picked on the next step');

        $user = $this->withHeader('Authorization', 'Bearer '.$registered['token'])->graphQL(
            'mutation { updateLanguages(native_language: "ru", studied_languages: ["en", "de"]) { native_language studied_languages } }',
        )->json('data.updateLanguages');

        $this->assertSame(['en', 'de'], $user['studied_languages']);
    }

    public function test_the_native_language_cannot_also_be_learnt(): void
    {
        $this->signIn('ru', ['en']);

        $this->graphQL('mutation { updateLanguages(native_language: "ru", studied_languages: ["ru"]) { id } }')
            ->assertGraphQLErrorMessage('Your native language cannot be one you are learning.');
    }

    public function test_dropping_a_language_keeps_the_order_of_the_rest(): void
    {
        $user = $this->signIn('ru', ['en', 'de', 'uk']);

        $languages = $this->graphQL(
            'mutation { updateLanguages(native_language: "ru", studied_languages: ["uk", "en"]) { studied_languages } }',
        )->json('data.updateLanguages.studied_languages');

        $this->assertSame(['uk', 'en'], $languages);
        $this->assertSame(2, $user->languages()->count());
    }

    public function test_errors_speak_the_interface_language(): void
    {
        User::factory()->create(['email' => 'anna@example.com']);

        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.5')
            ->graphQL('mutation { login(email: "anna@example.com", password: "wrong-one") { token } }')
            ->assertGraphQLErrorMessage('Неверный email или пароль.');

        $this->withHeader('Accept-Language', 'fr-FR')
            ->graphQL('mutation { login(email: "anna@example.com", password: "wrong-one") { token } }')
            ->assertGraphQLErrorMessage('These credentials do not match our records.');
    }

    public function test_a_forgotten_password_is_reset_on_the_page_the_mail_links_to(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com', 'native_language' => 'uk']);
        $user->createToken('phone');

        $this->graphQL('mutation { forgotPassword(email: "nobody@example.com") }')->assertJsonPath('data.forgotPassword', true);
        $this->withHeader('Accept-Language', 'de')
            ->graphQL('mutation { forgotPassword(email: "anna@example.com") }')
            ->assertJsonPath('data.forgotPassword', true);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail, array $channels, User $notifiable, ?string $locale) use (&$token) {
            $token = $mail->token;

            return $locale === 'uk';
        });
        Notification::assertCount(1);

        $this->get("/reset-password/{$token}?email=anna@example.com")
            ->assertOk()
            ->assertSee('Новий пароль');

        $this->post('/reset-password', ['token' => 'forged', 'email' => 'anna@example.com', 'password' => 'new-password'])
            ->assertSessionHasErrors('password');

        $this->post('/reset-password', ['token' => $token, 'email' => 'anna@example.com', 'password' => 'new-password'])
            ->assertOk()
            ->assertSee('Пароль змінено');

        $this->assertSame(0, $user->tokens()->count(), 'every device signs in again');
        $this->graphQL('mutation { login(email: "anna@example.com", password: "new-password") { user { email } } }')
            ->assertJsonPath('data.login.user.email', 'anna@example.com');
    }

    public function test_the_reset_link_points_at_app_url_whatever_host_the_request_names(): void
    {
        config(['app.url' => 'https://api-words.example']);
        Notification::fake();
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->withHeader('Host', 'attacker.example')
            ->graphQL('mutation { forgotPassword(email: "anna@example.com") }');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use ($user) {
            $url = $mail->toMail($user)->actionUrl;

            return str_starts_with($url, "https://api-words.example/reset-password/{$mail->token}?email=");
        });
    }

    public function test_deleting_the_account_needs_the_password(): void
    {
        $user = $this->signIn();

        $this->graphQL('mutation { deleteAccount(password: "nope") }')
            ->assertGraphQLErrorMessage('Current password is incorrect.');

        $this->graphQL('mutation { deleteAccount(password: "password") }')->assertJsonPath('data.deleteAccount', true);
        $this->assertNull($user->fresh());
    }

    public function test_a_new_user_gets_the_default_reminders_until_they_change_them(): void
    {
        $this->signIn();

        $reminders = $this->graphQL('{ me { reminders { enabled days times { time enabled } } } }')->json('data.me.reminders');
        $this->assertTrue($reminders['enabled']);
        $this->assertSame([1, 2, 3, 4, 5], $reminders['days']);
        $this->assertSame(['time' => '20:30', 'enabled' => true], $reminders['times'][2]);

        $updated = $this->graphQL(
            'mutation {
                updateReminders(input: {
                    enabled: true, only_when_due: true, skip_if_studied: true, days: [6, 7, 7, 9],
                    times: [{ time: "21:00", enabled: true }, { time: "08:15", enabled: false }]
                }) { days skip_if_studied times { time enabled } }
            }',
        )->json('data.updateReminders');

        $this->assertSame([6, 7], $updated['days']);
        $this->assertTrue($updated['skip_if_studied']);
        $this->assertSame(['08:15', '21:00'], array_column($updated['times'], 'time'));
    }
}
