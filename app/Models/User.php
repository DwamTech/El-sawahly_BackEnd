<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';

    const ROLE_EDITOR = 'editor';

    const ROLE_AUTHOR = 'author';

    const ROLE_REVIEWER = 'reviewer';

    const ROLE_USER = 'user';

    public const MANAGEABLE_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_EDITOR,
        self::ROLE_AUTHOR,
        self::ROLE_REVIEWER,
        self::ROLE_USER,
    ];

    public const INTERNAL_DASHBOARD_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_EDITOR,
        self::ROLE_AUTHOR,
        self::ROLE_REVIEWER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAuthor()
    {
        return $this->role === self::ROLE_AUTHOR;
    }

    public function isEditor()
    {
        return $this->role === self::ROLE_EDITOR;
    }

    public function isReviewer()
    {
        return $this->role === self::ROLE_REVIEWER;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canManageContent(): bool
    {
        return $this->hasAnyRole([
            self::ROLE_ADMIN,
            self::ROLE_EDITOR,
            self::ROLE_AUTHOR,
            self::ROLE_REVIEWER,
        ]);
    }

    public static function manageableRoles(): array
    {
        return self::MANAGEABLE_ROLES;
    }

    public static function internalDashboardRoles(): array
    {
        return self::INTERNAL_DASHBOARD_ROLES;
    }

    // public function isAuthor()
    // {
    //     return $this->role === self::ROLE_AUTHOR;
    // }
}
