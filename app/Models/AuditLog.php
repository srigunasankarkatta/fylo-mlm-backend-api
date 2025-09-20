<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_id',
        'action_type',
        'target_table',
        'target_id',
        'before',
        'after',
        'ip_address',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Disable updated_at timestamp.
     */
    public $timestamps = false;

    /**
     * Action type constants.
     */
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_ACTIVATE = 'activate';
    const ACTION_DEACTIVATE = 'deactivate';
    const ACTION_APPROVE = 'approve';
    const ACTION_REJECT = 'reject';
    const ACTION_PROCESS = 'process';
    const ACTION_CANCEL = 'cancel';
    const ACTION_RESTORE = 'restore';
    const ACTION_FORCE_DELETE = 'force_delete';

    /**
     * Get the admin user who performed the action.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope a query to only include logs for a specific admin.
     */
    public function scopeForAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * Scope a query to only include logs by action type.
     */
    public function scopeByActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope a query to only include logs for a specific table.
     */
    public function scopeForTable($query, string $tableName)
    {
        return $query->where('target_table', $tableName);
    }

    /**
     * Scope a query to only include logs for a specific record.
     */
    public function scopeForRecord($query, string $tableName, int $recordId)
    {
        return $query->where('target_table', $tableName)
            ->where('target_id', $recordId);
    }

    /**
     * Scope a query to only include logs by IP address.
     */
    public function scopeByIpAddress($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Scope a query to only include logs within date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include logs for income configs.
     */
    public function scopeForIncomeConfigs($query)
    {
        return $query->where('target_table', 'income_configs');
    }

    /**
     * Scope a query to only include logs for critical actions.
     */
    public function scopeCriticalActions($query)
    {
        return $query->whereIn('action_type', [
            self::ACTION_DELETE,
            self::ACTION_DEACTIVATE,
            self::ACTION_REJECT,
            self::ACTION_CANCEL,
            self::ACTION_FORCE_DELETE,
        ]);
    }

    /**
     * Create a new audit log entry.
     */
    public static function createLog(array $data): AuditLog
    {
        return static::create($data);
    }

    /**
     * Log a create action.
     */
    public static function logCreate(
        int $adminId,
        string $targetTable,
        int $targetId,
        array $after,
        ?string $ipAddress = null
    ): AuditLog {
        return static::createLog([
            'admin_id' => $adminId,
            'action_type' => self::ACTION_CREATE,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'before' => null,
            'after' => $after,
            'ip_address' => $ipAddress ?? Request::ip(),
        ]);
    }

    /**
     * Log an update action.
     */
    public static function logUpdate(
        int $adminId,
        string $targetTable,
        int $targetId,
        array $before,
        array $after,
        ?string $ipAddress = null
    ): AuditLog {
        return static::createLog([
            'admin_id' => $adminId,
            'action_type' => self::ACTION_UPDATE,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress ?? Request::ip(),
        ]);
    }

    /**
     * Log a delete action.
     */
    public static function logDelete(
        int $adminId,
        string $targetTable,
        int $targetId,
        array $before,
        ?string $ipAddress = null
    ): AuditLog {
        return static::createLog([
            'admin_id' => $adminId,
            'action_type' => self::ACTION_DELETE,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'before' => $before,
            'after' => null,
            'ip_address' => $ipAddress ?? Request::ip(),
        ]);
    }

    /**
     * Log a custom action.
     */
    public static function logAction(
        int $adminId,
        string $actionType,
        string $targetTable,
        ?int $targetId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipAddress = null
    ): AuditLog {
        return static::createLog([
            'admin_id' => $adminId,
            'action_type' => $actionType,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress ?? Request::ip(),
        ]);
    }

    /**
     * Get all action types.
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_ACTIVATE,
            self::ACTION_DEACTIVATE,
            self::ACTION_APPROVE,
            self::ACTION_REJECT,
            self::ACTION_PROCESS,
            self::ACTION_CANCEL,
            self::ACTION_RESTORE,
            self::ACTION_FORCE_DELETE,
        ];
    }

    /**
     * Get audit statistics for an admin.
     */
    public static function getAdminStats(int $adminId): array
    {
        $totalActions = static::forAdmin($adminId)->count();
        $byActionType = static::forAdmin($adminId)
            ->select('action_type', DB::raw('count(*) as count'))
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();

        $byTable = static::forAdmin($adminId)
            ->select('target_table', DB::raw('count(*) as count'))
            ->groupBy('target_table')
            ->pluck('count', 'target_table')
            ->toArray();

        $criticalActions = static::forAdmin($adminId)->criticalActions()->count();
        $recentActions = static::forAdmin($adminId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total_actions' => $totalActions,
            'by_action_type' => $byActionType,
            'by_table' => $byTable,
            'critical_actions' => $criticalActions,
            'recent_actions' => $recentActions,
        ];
    }

    /**
     * Get audit statistics for a specific table.
     */
    public static function getTableStats(string $tableName): array
    {
        $totalActions = static::forTable($tableName)->count();
        $byActionType = static::forTable($tableName)
            ->select('action_type', DB::raw('count(*) as count'))
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();

        $byAdmin = static::forTable($tableName)
            ->select('admin_id', DB::raw('count(*) as count'))
            ->groupBy('admin_id')
            ->with('admin')
            ->get()
            ->pluck('count', 'admin.name')
            ->toArray();

        $recentActions = static::forTable($tableName)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total_actions' => $totalActions,
            'by_action_type' => $byActionType,
            'by_admin' => $byAdmin,
            'recent_actions' => $recentActions,
        ];
    }

    /**
     * Get system-wide audit statistics.
     */
    public static function getSystemStats(): array
    {
        $totalActions = static::count();
        $byActionType = static::select('action_type', DB::raw('count(*) as count'))
            ->groupBy('action_type')
            ->pluck('count', 'action_type')
            ->toArray();

        $byTable = static::select('target_table', DB::raw('count(*) as count'))
            ->groupBy('target_table')
            ->pluck('count', 'target_table')
            ->toArray();

        $byAdmin = static::select('admin_id', DB::raw('count(*) as count'))
            ->groupBy('admin_id')
            ->with('admin')
            ->get()
            ->pluck('count', 'admin.name')
            ->toArray();

        $criticalActions = static::criticalActions()->count();
        $recentActions = static::where('created_at', '>=', now()->subDays(7))->count();

        return [
            'total_actions' => $totalActions,
            'by_action_type' => $byActionType,
            'by_table' => $byTable,
            'by_admin' => $byAdmin,
            'critical_actions' => $criticalActions,
            'recent_actions' => $recentActions,
        ];
    }

    /**
     * Get audit trail for a specific record.
     */
    public static function getRecordAuditTrail(string $tableName, int $recordId)
    {
        return static::forRecord($tableName, $recordId)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get recent audit logs.
     */
    public static function getRecentLogs(int $limit = 50)
    {
        return static::with('admin')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit logs for income configs.
     */
    public static function getIncomeConfigLogs()
    {
        return static::forIncomeConfigs()
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get critical action logs.
     */
    public static function getCriticalActionLogs()
    {
        return static::criticalActions()
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get audit summary.
     */
    public function getSummaryAttribute(): string
    {
        $adminName = $this->admin ? $this->admin->name : "Admin #{$this->admin_id}";
        $target = $this->target_id ? "{$this->target_table}#{$this->target_id}" : $this->target_table;

        return "{$adminName} {$this->action_type}d {$target}";
    }

    /**
     * Get formatted changes.
     */
    public function getFormattedChangesAttribute(): array
    {
        if (!$this->before || !$this->after) {
            return [];
        }

        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($this->before), array_keys($this->after)));

        foreach ($allKeys as $key) {
            $beforeValue = $this->before[$key] ?? null;
            $afterValue = $this->after[$key] ?? null;

            if ($beforeValue !== $afterValue) {
                $changes[$key] = [
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (empty($log->ip_address)) {
                $log->ip_address = Request::ip();
            }
        });
    }
}
