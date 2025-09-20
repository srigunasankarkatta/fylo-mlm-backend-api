<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClubMatrix extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sponsor_id',
        'member_id',
        'depth',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'depth' => 'integer',
    ];

    /**
     * Get the sponsor user.
     */
    public function sponsor()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * Get the member user.
     */
    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /**
     * Scope a query to only include entries for a specific sponsor.
     */
    public function scopeForSponsor($query, int $sponsorId)
    {
        return $query->where('sponsor_id', $sponsorId);
    }

    /**
     * Scope a query to only include entries for a specific member.
     */
    public function scopeForMember($query, int $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Scope a query to only include entries at a specific depth.
     */
    public function scopeAtDepth($query, int $depth)
    {
        return $query->where('depth', $depth);
    }

    /**
     * Scope a query to only include entries within a depth range.
     */
    public function scopeWithinDepth($query, int $minDepth, int $maxDepth)
    {
        return $query->whereBetween('depth', [$minDepth, $maxDepth]);
    }

    /**
     * Scope a query to only include direct referrals (depth = 1).
     */
    public function scopeDirectReferrals($query)
    {
        return $query->where('depth', 1);
    }

    /**
     * Scope a query to only include indirect referrals (depth > 1).
     */
    public function scopeIndirectReferrals($query)
    {
        return $query->where('depth', '>', 1);
    }

    /**
     * Create a new club matrix entry.
     */
    public static function createEntry(int $sponsorId, int $memberId, int $depth = 1): ClubMatrix
    {
        return static::create([
            'sponsor_id' => $sponsorId,
            'member_id' => $memberId,
            'depth' => $depth,
        ]);
    }

    /**
     * Add a member to the club matrix under a sponsor.
     */
    public static function addMember(int $sponsorId, int $memberId): ClubMatrix
    {
        // Check if relationship already exists
        $existing = static::where('sponsor_id', $sponsorId)
            ->where('member_id', $memberId)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create direct relationship
        $directEntry = static::createEntry($sponsorId, $memberId, 1);

        // Create indirect relationships for all sponsors of the sponsor
        $sponsorEntries = static::where('member_id', $sponsorId)->get();

        foreach ($sponsorEntries as $entry) {
            static::createEntry($entry->sponsor_id, $memberId, $entry->depth + 1);
        }

        return $directEntry;
    }

    /**
     * Get all direct referrals for a sponsor.
     */
    public static function getDirectReferrals(int $sponsorId)
    {
        return static::forSponsor($sponsorId)->directReferrals()->with('member')->get();
    }

    /**
     * Get all indirect referrals for a sponsor.
     */
    public static function getIndirectReferrals(int $sponsorId)
    {
        return static::forSponsor($sponsorId)->indirectReferrals()->with('member')->get();
    }

    /**
     * Get all referrals for a sponsor at a specific depth.
     */
    public static function getReferralsAtDepth(int $sponsorId, int $depth)
    {
        return static::forSponsor($sponsorId)->atDepth($depth)->with('member')->get();
    }

    /**
     * Get all referrals for a sponsor within a depth range.
     */
    public static function getReferralsWithinDepth(int $sponsorId, int $minDepth, int $maxDepth)
    {
        return static::forSponsor($sponsorId)
            ->withinDepth($minDepth, $maxDepth)
            ->with('member')
            ->get();
    }

    /**
     * Get all sponsors for a member.
     */
    public static function getSponsors(int $memberId)
    {
        return static::forMember($memberId)->with('sponsor')->orderBy('depth')->get();
    }

    /**
     * Get direct sponsor for a member.
     */
    public static function getDirectSponsor(int $memberId): ?ClubMatrix
    {
        return static::forMember($memberId)->directReferrals()->with('sponsor')->first();
    }

    /**
     * Get all sponsors for a member at a specific depth.
     */
    public static function getSponsorsAtDepth(int $memberId, int $depth)
    {
        return static::forMember($memberId)->atDepth($depth)->with('sponsor')->get();
    }

    /**
     * Get the complete downline for a sponsor.
     */
    public static function getDownline(int $sponsorId)
    {
        return static::forSponsor($sponsorId)
            ->with('member')
            ->orderBy('depth')
            ->orderBy('member_id')
            ->get();
    }

    /**
     * Get the complete upline for a member.
     */
    public static function getUpline(int $memberId)
    {
        return static::forMember($memberId)
            ->with('sponsor')
            ->orderBy('depth')
            ->get();
    }

    /**
     * Get matrix statistics for a sponsor.
     */
    public static function getSponsorStats(int $sponsorId): array
    {
        $totalReferrals = static::forSponsor($sponsorId)->count();
        $directReferrals = static::forSponsor($sponsorId)->directReferrals()->count();
        $indirectReferrals = static::forSponsor($sponsorId)->indirectReferrals()->count();

        $byDepth = [];
        $maxDepth = static::forSponsor($sponsorId)->max('depth') ?? 0;

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $byDepth[$depth] = static::forSponsor($sponsorId)->atDepth($depth)->count();
        }

        return [
            'total_referrals' => $totalReferrals,
            'direct_referrals' => $directReferrals,
            'indirect_referrals' => $indirectReferrals,
            'max_depth' => $maxDepth,
            'by_depth' => $byDepth,
        ];
    }

    /**
     * Get matrix statistics for a member.
     */
    public static function getMemberStats(int $memberId): array
    {
        $totalSponsors = static::forMember($memberId)->count();
        $directSponsor = static::getDirectSponsor($memberId);
        $indirectSponsors = static::forMember($memberId)->indirectReferrals()->count();

        $byDepth = [];
        $maxDepth = static::forMember($memberId)->max('depth') ?? 0;

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $byDepth[$depth] = static::forMember($memberId)->atDepth($depth)->count();
        }

        return [
            'total_sponsors' => $totalSponsors,
            'direct_sponsor' => $directSponsor ? $directSponsor->sponsor : null,
            'indirect_sponsors' => $indirectSponsors,
            'max_depth' => $maxDepth,
            'by_depth' => $byDepth,
        ];
    }

    /**
     * Get system-wide matrix statistics.
     */
    public static function getSystemStats(): array
    {
        $totalEntries = static::count();
        $uniqueSponsors = static::distinct('sponsor_id')->count('sponsor_id');
        $uniqueMembers = static::distinct('member_id')->count('member_id');
        $maxDepth = static::max('depth') ?? 0;

        $byDepth = [];
        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $byDepth[$depth] = static::atDepth($depth)->count();
        }

        return [
            'total_entries' => $totalEntries,
            'unique_sponsors' => $uniqueSponsors,
            'unique_members' => $uniqueMembers,
            'max_depth' => $maxDepth,
            'by_depth' => $byDepth,
        ];
    }

    /**
     * Check if a relationship exists between sponsor and member.
     */
    public static function hasRelationship(int $sponsorId, int $memberId): bool
    {
        return static::where('sponsor_id', $sponsorId)
            ->where('member_id', $memberId)
            ->exists();
    }

    /**
     * Get the depth of relationship between sponsor and member.
     */
    public static function getRelationshipDepth(int $sponsorId, int $memberId): ?int
    {
        $entry = static::where('sponsor_id', $sponsorId)
            ->where('member_id', $memberId)
            ->first();

        return $entry ? $entry->depth : null;
    }

    /**
     * Remove a member from the club matrix.
     */
    public static function removeMember(int $memberId): int
    {
        return static::where('member_id', $memberId)->delete();
    }

    /**
     * Rebuild the entire club matrix.
     */
    public static function rebuildMatrix(): int
    {
        // Clear existing matrix
        static::truncate();

        // Get all users with their parent relationships
        $users = User::whereNotNull('parent_id')->get();

        $added = 0;
        foreach ($users as $user) {
            if ($user->parent_id) {
                static::addMember($user->parent_id, $user->id);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Get matrix visualization data for a sponsor.
     */
    public static function getMatrixVisualization(int $sponsorId, int $maxDepth = 5): array
    {
        $matrix = [];

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $referrals = static::getReferralsAtDepth($sponsorId, $depth);
            $matrix[$depth] = $referrals->map(function ($entry) {
                return [
                    'id' => $entry->member_id,
                    'name' => $entry->member->name ?? "User #{$entry->member_id}",
                    'depth' => $entry->depth,
                ];
            })->toArray();
        }

        return $matrix;
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($entry) {
            if (empty($entry->depth)) {
                $entry->depth = 1;
            }
        });
    }
}
