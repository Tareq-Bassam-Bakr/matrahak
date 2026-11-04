<?php

/*
 * Matrahak (مطرحك) — Smart citizen-reports platform | منصة ذكية لإدارة بلاغات المواطنين
 * Developed by Eng. Tareq Bassam Bakr — طُوّر بواسطة م. طارق بسام بكر
 * Graduation Project · Informatics Engineering · Al-Jazeera Private University · 2026
 * © 2026 Tareq Bassam Bakr. All rights reserved. See LICENSE — do not remove this notice.
 */

namespace App\Models;

use App\Enums\ReportStatus;
use App\Traits\Auditable;
use App\Traits\HasUuid;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    /** حسابات بيانات العرض (DemoReportsSeeder) — بلاغاتها لا تُستخدم أبداً في تدريب النموذج */
    public const DEMO_EMAIL_PATTERN = 'demo.%@matrahak.sy';

    /**
     * ⚠️ كان citizen_fcm_token و employee_fcm_token خارج هذه القائمة سابقاً،
     * فكان Laravel يتجاهلهما بصمت ولا يُحفظ أي FCM Token.
     */
    protected $fillable = [
        'uuid', 'name', 'email', 'phone', 'national_id', 'user_type', 'avatar',
        'department_id', 'district_id', 'is_active', 'is_verified',
        'citizen_fcm_token', 'employee_fcm_token', 'preferred_lang', 'last_login_at', 'email_verified_at', 'password',
    ];

    protected $hidden = ['password', 'remember_token', 'citizen_fcm_token', 'employee_fcm_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // نوع المستخدم هو مصدر الحقيقة للدور (Role) — نزامنهما تلقائياً
        static::saved(function (User $user) {
            if (($user->wasRecentlyCreated || $user->wasChanged('user_type'))
                && Role::query()->where('name', $user->user_type)->where('guard_name', 'web')->exists()) {
                $user->syncRoles([$user->user_type]);
            }
        });
    }

    // ================= Filament =================

    /** فقط مدير النظام ومدير المدينة يدخلون لوحة الإدارة */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->user_type, ['admin', 'manager'], true);
    }

    // ================= Relations =================

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'citizen_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class, 'employee_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class, 'employee_id')->where('is_active', true);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(EmployeeStat::class, 'employee_id');
    }

    // ================= Scopes =================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEmployeesOf($query, int $departmentId)
    {
        return $query->where('user_type', 'employee')->where('department_id', $departmentId)->where('is_active', true);
    }

    public function scopeStaff($query)
    {
        return $query->whereIn('user_type', ['admin', 'manager']);
    }

    // ================= Helpers =================

    public function isCitizen(): bool
    {
        return $this->user_type === 'citizen';
    }

    public function isEmployee(): bool
    {
        return $this->user_type === 'employee';
    }

    public function isManager(): bool
    {
        return $this->user_type === 'manager';
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function currentWorkload(): int
    {
        return $this->activeAssignments()
            ->whereHas('report', fn ($q) => $q->whereIn('status', ReportStatus::openValues()))
            ->count();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/'.$this->avatar) : null;
    }

    /** يحدد Token الجهاز حسب التطبيق الذي يستخدمه المستخدم */
    public function fcmToken(): ?string
    {
        return $this->isCitizen() ? $this->citizen_fcm_token : $this->employee_fcm_token;
    }

    public function fcmTokenField(): string
    {
        return $this->isCitizen() ? 'citizen_fcm_token' : 'employee_fcm_token';
    }
}
