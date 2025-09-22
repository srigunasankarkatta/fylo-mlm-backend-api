<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserTree extends Model
{
    use HasFactory;

    protected $table = 'user_tree';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'parent_id',
        'position',
        'path',
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
     * Get the user who owns this tree node.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent node.
     */
    public function parent()
    {
        return $this->belongsTo(UserTree::class, 'parent_id');
    }

    /**
     * Get the child nodes.
     */
    public function children()
    {
        return $this->hasMany(UserTree::class, 'parent_id');
    }

    /**
     * Get the child node at a specific position.
     */
    public function childAtPosition(int $position)
    {
        return $this->children()->where('position', $position)->first();
    }

    /**
     * Get all ancestors (up the tree).
     */
    public function ancestors()
    {
        if (empty($this->path)) {
            return collect();
        }

        $userIds = array_filter(explode('/', trim($this->path, '/')));
        return static::whereIn('user_id', $userIds)->get();
    }

    /**
     * Get all descendants (down the tree).
     */
    public function descendants()
    {
        return static::where('path', 'like', $this->path . $this->user_id . '/%')->get();
    }

    /**
     * Get immediate descendants (direct children only).
     */
    public function immediateDescendants()
    {
        return $this->children()->orderBy('position')->get();
    }

    /**
     * Get siblings (same parent).
     */
    public function siblings()
    {
        if (!$this->parent_id) {
            return collect();
        }

        return static::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->orderBy('position')
            ->get();
    }

    /**
     * Get the root node (depth 0).
     */
    public function root()
    {
        if ($this->depth === 0) {
            return $this;
        }

        $userIds = array_filter(explode('/', trim($this->path, '/')));
        $rootUserId = $userIds[0] ?? null;

        return $rootUserId ? static::where('user_id', $rootUserId)->first() : null;
    }

    /**
     * Get the tree level (depth + 1).
     */
    public function getLevelAttribute(): int
    {
        return $this->depth + 1;
    }

    /**
     * Check if this node is a leaf (no children).
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Check if this node is the root.
     */
    public function isRoot(): bool
    {
        return $this->depth === 0;
    }

    /**
     * Check if this node has available positions for new children.
     */
    public function hasAvailablePosition(): bool
    {
        return $this->children()->count() < 4;
    }

    /**
     * Get the next available position for a new child.
     */
    public function getNextAvailablePosition(): ?int
    {
        $usedPositions = $this->children()->pluck('position')->toArray();

        for ($i = 1; $i <= 4; $i++) {
            if (!in_array($i, $usedPositions)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Add a new child to this node.
     */
    public function addChild(int $userId, ?int $position = null): ?UserTree
    {
        if (!$this->hasAvailablePosition()) {
            return null;
        }

        if ($position === null) {
            $position = $this->getNextAvailablePosition();
        }

        if ($position === null) {
            return null;
        }

        $newPath = $this->path . $this->user_id . '/';
        $newDepth = $this->depth + 1;

        return static::create([
            'user_id' => $userId,
            'parent_id' => $this->id,
            'position' => $position,
            'path' => $newPath,
            'depth' => $newDepth,
        ]);
    }

    /**
     * Move this node to a new parent.
     */
    public function moveToParent(?UserTree $newParent, ?int $position = null): bool
    {
        if ($newParent && !$newParent->hasAvailablePosition()) {
            return false;
        }

        if ($position === null && $newParent) {
            $position = $newParent->getNextAvailablePosition();
        }

        if ($position === null && $newParent) {
            return false;
        }

        $oldPath = $this->path;
        $newPath = $newParent ? $newParent->path . $newParent->user_id . '/' : null;
        $newDepth = $newParent ? $newParent->depth + 1 : 0;

        // Update this node
        $this->update([
            'parent_id' => $newParent?->id,
            'position' => $position,
            'path' => $newPath,
            'depth' => $newDepth,
        ]);

        // Update all descendants' paths
        $this->updateDescendantsPaths($oldPath, $newPath);

        return true;
    }

    /**
     * Update paths for all descendants after a move.
     */
    protected function updateDescendantsPaths(string $oldPath, ?string $newPath): void
    {
        $descendants = static::where('path', 'like', $oldPath . $this->user_id . '/%')->get();

        foreach ($descendants as $descendant) {
            $newDescendantPath = str_replace($oldPath, $newPath ?? '', $descendant->path);
            $newDescendantDepth = substr_count($newDescendantPath, '/') - 1;

            $descendant->update([
                'path' => $newDescendantPath,
                'depth' => $newDescendantDepth,
            ]);
        }
    }

    /**
     * Get the tree structure as a nested array.
     */
    public function getTreeStructure(): array
    {
        $children = $this->immediateDescendants();
        $structure = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'position' => $this->position,
            'depth' => $this->depth,
            'path' => $this->path,
            'children' => []
        ];

        foreach ($children as $child) {
            $structure['children'][] = $child->getTreeStructure();
        }

        return $structure;
    }

    /**
     * Get all nodes at a specific depth.
     */
    public static function getNodesAtDepth(int $depth)
    {
        return static::where('depth', $depth)->get();
    }

    /**
     * Get the tree statistics.
     */
    public function getTreeStats(): array
    {
        $totalNodes = static::where('path', 'like', $this->path . '%')->count();
        $maxDepth = static::where('path', 'like', $this->path . '%')->max('depth') ?? $this->depth;
        $leafNodes = static::where('path', 'like', $this->path . '%')
            ->whereNotIn('id', function ($query) {
                $query->select('parent_id')
                    ->from('user_tree')
                    ->whereNotNull('parent_id');
            })->count();

        return [
            'total_nodes' => $totalNodes,
            'max_depth' => $maxDepth - $this->depth,
            'leaf_nodes' => $leafNodes,
            'current_depth' => $this->depth,
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($userTree) {
            if (empty($userTree->path) && $userTree->parent_id) {
                $parent = static::find($userTree->parent_id);
                if ($parent) {
                    $userTree->path = $parent->path . $parent->user_id . '/';
                    $userTree->depth = $parent->depth + 1;
                }
            }
        });
    }
}
