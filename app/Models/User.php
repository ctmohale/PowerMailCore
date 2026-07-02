<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['client_id', 'name', 'email', 'password', 'role', 'status', 'permissions', 'default_email_template_id', 'last_access_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CLIENT_USER = 'client_user';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const PERMISSION_SEND_EMAILS = 'send_emails';

    public const PERMISSION_VIEW_INBOX = 'view_inbox';

    public const PERMISSION_VIEW_LOGS = 'view_logs';

    public const PERMISSION_MANAGE_TEMPLATES = 'manage_templates';

    public const PERMISSION_MANAGE_ACCOUNTS = 'manage_accounts';

    public const PERMISSION_MANAGE_MARKETING = 'manage_marketing';

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
            'permissions' => 'array',
            'last_access_at' => 'datetime',
        ];
    }

    public static function defaultClientPermissions(): array
    {
        return [
            self::PERMISSION_SEND_EMAILS => true,
            self::PERMISSION_VIEW_INBOX => true,
            self::PERMISSION_VIEW_LOGS => true,
            self::PERMISSION_MANAGE_TEMPLATES => true,
            self::PERMISSION_MANAGE_ACCOUNTS => false,
            self::PERMISSION_MANAGE_MARKETING => false,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function canAccess(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (bool) ($this->permissions[$permission] ?? false);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function defaultEmailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'default_email_template_id');
    }

    public function emailAccounts(): BelongsToMany
    {
        return $this->belongsToMany(EmailAccount::class)->withTimestamps();
    }
}
