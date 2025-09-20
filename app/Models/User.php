<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'phone_verified_at',
        'referral_code',
        'referred_by',
        'parent_id',
        'position',
        'package_id',
        'role_hint',
        'status',
        'metadata',
        'last_login_ip',
        'last_login_at',
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
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'metadata' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = Str::uuid();
            }
            if (empty($user->referral_code)) {
                $user->referral_code = $user->generateReferralCode();
            }
        });
    }

    /**
     * Generate a unique referral code.
     */
    public function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get all auto pool entries for this user.
     */
    public function autoPoolEntries()
    {
        return $this->hasMany(AutoPoolEntry::class);
    }

    /**
     * Get auto pool entries by pool level.
     */
    public function getAutoPoolEntriesByLevel(int $level)
    {
        return $this->autoPoolEntries()->byPoolLevel($level);
    }

    /**
     * Get active auto pool entries.
     */
    public function getActiveAutoPoolEntries()
    {
        return $this->autoPoolEntries()->active();
    }

    /**
     * Get completed auto pool entries.
     */
    public function getCompletedAutoPoolEntries()
    {
        return $this->autoPoolEntries()->completed();
    }

    /**
     * Get paid out auto pool entries.
     */
    public function getPaidOutAutoPoolEntries()
    {
        return $this->autoPoolEntries()->paidOut();
    }

    /**
     * Get auto pool entries for specific pool level and sub level.
     */
    public function getAutoPoolEntriesForPool(int $level, int $subLevel)
    {
        return $this->autoPoolEntries()->forPoolLevel($level, $subLevel);
    }

    /**
     * Get auto pool statistics for this user.
     */
    public function getAutoPoolStats(): array
    {
        return AutoPoolEntry::getUserPoolStats($this->id);
    }

    /**
     * Create an auto pool entry for this user.
     */
    public function createAutoPoolEntry(
        int $packageId,
        int $poolLevel,
        int $poolSubLevel,
        ?int $allocatedBy = null
    ): AutoPoolEntry {
        return AutoPoolEntry::createActiveEntry(
            $this->id,
            $packageId,
            $poolLevel,
            $poolSubLevel,
            $allocatedBy
        );
    }

    /**
     * Get all club matrix entries where this user is a sponsor.
     */
    public function clubMatrixSponsorships()
    {
        return $this->hasMany(ClubMatrix::class, 'sponsor_id');
    }

    /**
     * Get all club matrix entries where this user is a member.
     */
    public function clubMatrixMemberships()
    {
        return $this->hasMany(ClubMatrix::class, 'member_id');
    }

    /**
     * Get direct referrals in club matrix.
     */
    public function getDirectClubReferrals()
    {
        return ClubMatrix::getDirectReferrals($this->id);
    }

    /**
     * Get indirect referrals in club matrix.
     */
    public function getIndirectClubReferrals()
    {
        return ClubMatrix::getIndirectReferrals($this->id);
    }

    /**
     * Get all referrals in club matrix.
     */
    public function getAllClubReferrals()
    {
        return ClubMatrix::getDownline($this->id);
    }

    /**
     * Get all sponsors in club matrix.
     */
    public function getAllClubSponsors()
    {
        return ClubMatrix::getUpline($this->id);
    }

    /**
     * Get direct sponsor in club matrix.
     */
    public function getDirectClubSponsor(): ?ClubMatrix
    {
        return ClubMatrix::getDirectSponsor($this->id);
    }

    /**
     * Get club matrix statistics for this user.
     */
    public function getClubMatrixStats(): array
    {
        return ClubMatrix::getSponsorStats($this->id);
    }

    /**
     * Add a member to this user's club matrix.
     */
    public function addClubMember(User $member): ClubMatrix
    {
        return ClubMatrix::addMember($this->id, $member->id);
    }

    /**
     * Check if this user sponsors another user in club matrix.
     */
    public function sponsorsInClubMatrix(User $member): bool
    {
        return ClubMatrix::hasRelationship($this->id, $member->id);
    }

    /**
     * Get the depth of relationship with another user in club matrix.
     */
    public function getClubMatrixDepth(User $member): ?int
    {
        return ClubMatrix::getRelationshipDepth($this->id, $member->id);
    }

    /**
     * Get club matrix visualization for this user.
     */
    public function getClubMatrixVisualization(int $maxDepth = 5): array
    {
        return ClubMatrix::getMatrixVisualization($this->id, $maxDepth);
    }

    /**
     * Get all payouts for this user.
     */
    public function payouts()
    {
        return $this->hasMany(Payout::class);
    }

    /**
     * Get requested payouts.
     */
    public function getRequestedPayouts()
    {
        return $this->payouts()->requested();
    }

    /**
     * Get processing payouts.
     */
    public function getProcessingPayouts()
    {
        return $this->payouts()->processing();
    }

    /**
     * Get completed payouts.
     */
    public function getCompletedPayouts()
    {
        return $this->payouts()->completed();
    }

    /**
     * Get failed payouts.
     */
    public function getFailedPayouts()
    {
        return $this->payouts()->failed();
    }

    /**
     * Get rejected payouts.
     */
    public function getRejectedPayouts()
    {
        return $this->payouts()->rejected();
    }

    /**
     * Get pending payouts (requested or processing).
     */
    public function getPendingPayouts()
    {
        return $this->payouts()->whereIn('status', [Payout::STATUS_REQUESTED, Payout::STATUS_PROCESSING]);
    }

    /**
     * Get payout statistics for this user.
     */
    public function getPayoutStats(): array
    {
        return Payout::getUserPayoutStats($this->id);
    }

    /**
     * Create a payout request for this user.
     */
    public function createPayoutRequest(
        int $walletId,
        float $amount,
        float $fee = 0,
        ?array $payoutMethod = null
    ): Payout {
        return Payout::createUserRequest(
            $this->id,
            $walletId,
            $amount,
            $fee,
            $payoutMethod
        );
    }

    /**
     * Get total amount requested in payouts.
     */
    public function getTotalPayoutAmount(): float
    {
        return $this->payouts()->sum('amount');
    }

    /**
     * Get total amount completed in payouts.
     */
    public function getTotalCompletedPayoutAmount(): float
    {
        return $this->payouts()->completed()->sum('amount');
    }

    /**
     * Get total fees paid in payouts.
     */
    public function getTotalPayoutFees(): float
    {
        return $this->payouts()->sum('fee');
    }

    /**
     * Get net payout amount (total - fees).
     */
    public function getNetPayoutAmount(): float
    {
        return $this->getTotalPayoutAmount() - $this->getTotalPayoutFees();
    }

    /**
     * Get all audit logs created by this admin user.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'admin_id');
    }

    /**
     * Get audit logs by action type.
     */
    public function getAuditLogsByAction(string $actionType)
    {
        return $this->auditLogs()->byActionType($actionType);
    }

    /**
     * Get audit logs for a specific table.
     */
    public function getAuditLogsForTable(string $tableName)
    {
        return $this->auditLogs()->forTable($tableName);
    }

    /**
     * Get critical action audit logs.
     */
    public function getCriticalAuditLogs()
    {
        return $this->auditLogs()->criticalActions();
    }

    /**
     * Get recent audit logs.
     */
    public function getRecentAuditLogs(int $limit = 50)
    {
        return $this->auditLogs()->orderBy('created_at', 'desc')->limit($limit)->get();
    }

    /**
     * Get audit statistics for this admin.
     */
    public function getAuditStats(): array
    {
        return AuditLog::getAdminStats($this->id);
    }

    /**
     * Log an action performed by this admin.
     */
    public function logAction(
        string $actionType,
        string $targetTable,
        ?int $targetId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::logAction(
            $this->id,
            $actionType,
            $targetTable,
            $targetId,
            $before,
            $after,
            $ipAddress
        );
    }

    /**
     * Log a create action.
     */
    public function logCreate(
        string $targetTable,
        int $targetId,
        array $after,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::logCreate(
            $this->id,
            $targetTable,
            $targetId,
            $after,
            $ipAddress
        );
    }

    /**
     * Log an update action.
     */
    public function logUpdate(
        string $targetTable,
        int $targetId,
        array $before,
        array $after,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::logUpdate(
            $this->id,
            $targetTable,
            $targetId,
            $before,
            $after,
            $ipAddress
        );
    }

    /**
     * Log a delete action.
     */
    public function logDelete(
        string $targetTable,
        int $targetId,
        array $before,
        ?string $ipAddress = null
    ): AuditLog {
        return AuditLog::logDelete(
            $this->id,
            $targetTable,
            $targetId,
            $before,
            $ipAddress
        );
    }
}
