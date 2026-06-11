<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enum\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'display_name', 'name', 'first_name', 'last_name', 'middle_name', 'suffix_name',
    'email', 'personal_email', 'password', 'photo', 'sex', 'status', 'active',
    'bio_metric_id', 'birthday', 'date_hired', 'regular_date', 'phone', 'civil_status',
    'employment_status', 'department_id', 'job_title', 'sss', 'phic', 'hdmf_tin',
    'job_description', 'permanent_address', 'emergency_contact',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * Roles with HR/admin-level access across the app.
     *
     * @var list<string>
     */
    public const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    /**
     * Whether the user holds an HR/admin-level role.
     */
    public function isManager(): bool
    {
        return $this->hasAnyRole(self::MANAGER_ROLES);
    }

    /**
     * Whether this user may view the daily time record of $target. Managers
     * see everyone, team leaders see their departments' members, and everyone
     * may view their own.
     */
    public function canViewDtrOf(User $target): bool
    {
        if ($this->isManager() || $this->is($target)) {
            return true;
        }

        if ($this->isTeamLeader()) {
            return $target->department_id !== null
                && $this->ledDepartments()->whereKey($target->department_id)->exists();
        }

        return false;
    }

    /**
     * Whether this user may access the Filament panel. The panel is the
     * employee self-service portal, so every active employee gets in;
     * per-resource role checks govern what they can actually do.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === UserStatus::ACTIVE->value;
    }

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => UserStatus::ACTIVE->value,
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
            'two_factor_confirmed_at' => 'datetime',
            'emergency_contact' => 'array',
        ];
    }

    /**
     * Scope to employees whose status is active.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::ACTIVE->value);
    }

    /**
     * The avatar URL used by Filament (user menu, account widget).
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (blank($this->photo)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo);
    }

    /**
     * Praises this user has sent.
     *
     * @return HasMany<Praise, $this>
     */
    public function praisesSent(): HasMany
    {
        return $this->hasMany(Praise::class, 'user_id');
    }

    /**
     * Praises this user has received.
     *
     * @return HasMany<Praise, $this>
     */
    public function praisesReceived(): HasMany
    {
        return $this->hasMany(Praise::class, 'recipient_id');
    }

    /**
     * @return HasMany<PraiseReaction, $this>
     */
    public function praiseReactions(): HasMany
    {
        return $this->hasMany(PraiseReaction::class);
    }

    /**
     * @return HasMany<PraiseComment, $this>
     */
    public function praiseComments(): HasMany
    {
        return $this->hasMany(PraiseComment::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<OverTimeRequest, $this>
     */
    public function overTimeRequests(): HasMany
    {
        return $this->hasMany(OverTimeRequest::class);
    }

    /**
     * The department this employee belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Departments this user leads.
     *
     * @return BelongsToMany<Department, $this>
     */
    public function ledDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'team_leaders')->withTimestamps();
    }

    /**
     * Whether the user leads any department.
     */
    public function isTeamLeader(): bool
    {
        return $this->ledDepartments()->exists();
    }

    /**
     * Whether the user holds a super-admin role (full, unrestricted access).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasAnyRole(['superadmin', 'super_admin']);
    }

    /**
     * The user's leave balances and work schedule.
     *
     * @return HasOne<UserData, $this>
     */
    public function userData(): HasOne
    {
        return $this->hasOne(UserData::class);
    }

    /**
     * @return HasMany<AttendanceLog, $this>
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
