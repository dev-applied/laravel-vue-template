<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\ForgotPasswordMail;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Rickycezar\Impersonate\Models\Impersonate;
use Rickycezar\Impersonate\Services\ImpersonateManager;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract, JWTSubject
{
    use Authenticatable,
        Authorizable,
        CanResetPassword,
        HasFactory,
        Impersonate,
        MustVerifyEmail,
        Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
    ];

    public static function booted(): void
    {
        parent::booted();
        self::saving(function (User $user) {
            if ($user->password && Hash::needsRehash($user->password)) {
                $user->password = Hash::make($user->password);
            }
        });
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * sendPasswordResetNotification
     *
     * @param  mixed  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this)
            ->send(new ForgotPasswordMail($token, $this));
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->canImpersonate();
    }

    public function canImpersonate(): bool
    {
        // need to add logic here
        return false;
    }

    public function isImpersonated(): Attribute
    {
        return Attribute::get(function (): bool {
            return app(ImpersonateManager::class)->isImpersonating();
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => mb_trim($this->first_name.' '.$this->last_name));
    }
}
