<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'native_language'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Languages this user learns, in the order they were picked.
     *
     * @return HasMany<UserLanguage, $this>
     */
    public function languages(): HasMany
    {
        return $this->hasMany(UserLanguage::class)->orderBy('position');
    }

    /**
     * @return HasMany<Word, $this>
     */
    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * @return HasOne<ReminderSetting, $this>
     */
    public function reminderSetting(): HasOne
    {
        return $this->hasOne(ReminderSetting::class);
    }

    /**
     * Codes of the languages this user learns.
     *
     * @return list<string>
     */
    public function studiedLanguages(): array
    {
        return $this->languages->pluck('language')->values()->all();
    }

    /**
     * The reminder settings, or the defaults when the user never changed them.
     */
    public function reminders(): ReminderSetting
    {
        return $this->reminderSetting ?? ReminderSetting::defaultsFor($this);
    }

    /**
     * Mails and notifications speak the user's language, whatever device
     * asked for them.
     */
    public function preferredLocale(): string
    {
        return $this->native_language;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
