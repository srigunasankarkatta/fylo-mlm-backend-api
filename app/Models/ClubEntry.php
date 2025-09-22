<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sponsor_id',
        'level',
        'status',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    /**
     * Get the user who is placed in the club
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sponsor whose club tree this user joins
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * Scope to get entries by sponsor
     */
    public function scopeBySponsor($query, int $sponsorId)
    {
        return $query->where('sponsor_id', $sponsorId);
    }

    /**
     * Scope to get entries by level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope to get active entries
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get completed entries
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the count of children for a sponsor at a specific level
     */
    public static function getChildrenCount(int $sponsorId, int $level): int
    {
        return static::where('sponsor_id', $sponsorId)
            ->where('level', $level)
            ->count();
    }

    /**
     * Get all children of a sponsor at a specific level
     */
    public static function getChildren(int $sponsorId, int $level)
    {
        return static::where('sponsor_id', $sponsorId)
            ->where('level', $level)
            ->with('user')
            ->get();
    }

    /**
     * Check if a sponsor has available slots at a specific level
     */
    public static function hasAvailableSlots(int $sponsorId, int $level): bool
    {
        $maxSlots = pow(4, $level - 1); // 4^(level-1) slots at each level
        $currentSlots = static::getChildrenCount($sponsorId, $level);

        return $currentSlots < $maxSlots;
    }

    /**
     * Get the next available slot for a sponsor at a specific level
     */
    public static function getNextAvailableSlot(int $sponsorId, int $level): ?int
    {
        $maxSlots = pow(4, $level - 1);
        $currentSlots = static::getChildrenCount($sponsorId, $level);

        return $currentSlots < $maxSlots ? $currentSlots + 1 : null;
    }
}
