<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canBeDeletedBy(?self $actor): bool
    {
        return $actor !== null
            && ! $this->is($actor)
            && static::query()
                ->where($this->getKeyName(), '!=', $this->getKey())
                ->exists();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $user): void {
            if (auth()->id() === $user->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'user' => 'No podés eliminar tu propio usuario.',
                ]);
            }

            if (! static::query()->where($user->getKeyName(), '!=', $user->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'user' => 'No se puede eliminar el último usuario de Faro.',
                ]);
            }
        });
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
