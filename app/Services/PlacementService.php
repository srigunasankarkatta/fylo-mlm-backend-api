<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserTree;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlacementService
{
    /**
     * Place a new user in the tree under their referrer
     * 
     * @param User $user The new user to place
     * @param User $referrer The user who referred them
     * @return UserTree The created tree node
     * @throws \Exception If no available slot is found
     */
    public function placeUserInTree(User $user, User $referrer): UserTree
    {
        // Check if user is already placed
        $existingPlacement = UserTree::where('user_id', $user->id)->first();
        if ($existingPlacement) {
            Log::info("User {$user->id} is already placed in tree");
            return $existingPlacement;
        }

        // Ensure user has wallets initialized
        $this->ensureUserWalletsExist($user);

        // Get referrer's tree node
        $referrerNode = UserTree::where('user_id', $referrer->id)->first();
        if (!$referrerNode) {
            // If referrer is not in tree, create root node for them first
            $referrerNode = $this->createRootNode($referrer);
        }

        // Try direct placement first
        $childrenCount = UserTree::where('parent_id', $referrerNode->id)->count();

        if ($childrenCount < 4) {
            return $this->createUserTreeNode($user, $referrerNode, $childrenCount + 1);
        }

        // Spillover: BFS search for next available parent slot
        return $this->findAvailableSlot($user, $referrerNode);
    }

    /**
     * Create a root node for a user (when referrer is not in tree)
     */
    protected function createRootNode(User $user): UserTree
    {
        return UserTree::create([
            'user_id' => $user->id,
            'parent_id' => null,
            'position' => 1,
            'path' => '/',
            'depth' => 0
        ]);
    }

    /**
     * Create a tree node for the user
     */
    protected function createUserTreeNode(User $user, UserTree $parent, int $position): UserTree
    {
        $newPath = $parent->path . $parent->user_id . '/';
        $newDepth = $parent->depth + 1;

        return UserTree::create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'position' => $position,
            'path' => $newPath,
            'depth' => $newDepth
        ]);
    }

    /**
     * Find an available slot using BFS (Breadth-First Search)
     * This implements spillover logic - if direct parent is full, 
     * find the next available slot in the tree
     */
    protected function findAvailableSlot(User $user, UserTree $startNode): UserTree
    {
        $queue = [$startNode->id];
        $visited = [];

        while (!empty($queue)) {
            $currentParentId = array_shift($queue);

            if (in_array($currentParentId, $visited)) {
                continue;
            }
            $visited[] = $currentParentId;

            $currentParent = UserTree::find($currentParentId);
            if (!$currentParent) {
                continue;
            }

            // Check if this parent has available slots
            $childrenCount = UserTree::where('parent_id', $currentParentId)->count();

            if ($childrenCount < 4) {
                Log::info("Found available slot under parent {$currentParentId} for user {$user->id}");
                return $this->createUserTreeNode($user, $currentParent, $childrenCount + 1);
            }

            // Add children to queue for next level search
            $childIds = UserTree::where('parent_id', $currentParentId)
                ->pluck('user_id')
                ->toArray();

            // Convert user_ids to tree node ids
            $childNodeIds = UserTree::whereIn('user_id', $childIds)
                ->pluck('id')
                ->toArray();

            $queue = array_merge($queue, $childNodeIds);
        }

        // If no slot found, place as root (fallback)
        Log::warning("No available slot found in tree for user {$user->id}, placing as root");
        return $this->createRootNode($user);
    }

    /**
     * Get the placement statistics for a user
     */
    public function getPlacementStats(User $user): array
    {
        $userNode = UserTree::where('user_id', $user->id)->first();
        if (!$userNode) {
            return ['error' => 'User not found in tree'];
        }

        $children = UserTree::where('parent_id', $userNode->id)->get();
        $descendants = UserTree::where('path', 'like', $userNode->path . $userNode->user_id . '/%')->get();

        return [
            'user_id' => $user->id,
            'parent_id' => $userNode->parent_id,
            'position' => $userNode->position,
            'depth' => $userNode->depth,
            'path' => $userNode->path,
            'direct_children' => $children->count(),
            'total_descendants' => $descendants->count(),
            'available_slots' => 4 - $children->count(),
            'children' => $children->map(function ($child) {
                return [
                    'user_id' => $child->user_id,
                    'position' => $child->position,
                    'depth' => $child->depth
                ];
            })
        ];
    }

    /**
     * Get the full tree structure starting from a user
     */
    public function getTreeStructure(User $user, int $maxDepth = 5): array
    {
        $userNode = UserTree::where('user_id', $user->id)->first();
        if (!$userNode) {
            return ['error' => 'User not found in tree'];
        }

        return $this->buildTreeStructure($userNode, $maxDepth);
    }

    /**
     * Recursively build tree structure
     */
    protected function buildTreeStructure(UserTree $node, int $maxDepth, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return [
                'user_id' => $node->user_id,
                'position' => $node->position,
                'depth' => $node->depth,
                'children' => []
            ];
        }

        $children = UserTree::where('parent_id', $node->id)
            ->orderBy('position')
            ->get();

        $childrenData = [];
        foreach ($children as $child) {
            $childrenData[] = $this->buildTreeStructure($child, $maxDepth, $currentDepth + 1);
        }

        return [
            'user_id' => $node->user_id,
            'position' => $node->position,
            'depth' => $node->depth,
            'path' => $node->path,
            'children' => $childrenData
        ];
    }

    /**
     * Move a user to a new parent (admin function)
     */
    public function moveUser(User $user, User $newParent): bool
    {
        $userNode = UserTree::where('user_id', $user->id)->first();
        $newParentNode = UserTree::where('user_id', $newParent->id)->first();

        if (!$userNode || !$newParentNode) {
            return false;
        }

        // Check if new parent has available slots
        $childrenCount = UserTree::where('parent_id', $newParentNode->id)->count();
        if ($childrenCount >= 4) {
            return false;
        }

        return DB::transaction(function () use ($userNode, $newParentNode, $childrenCount) {
            // Update the user's placement
            $userNode->update([
                'parent_id' => $newParentNode->id,
                'position' => $childrenCount + 1,
                'path' => $newParentNode->path . $newParentNode->user_id . '/',
                'depth' => $newParentNode->depth + 1
            ]);

            // Update all descendants' paths
            $this->updateDescendantsPaths($userNode);

            return true;
        });
    }

    /**
     * Update paths for all descendants after a move
     */
    protected function updateDescendantsPaths(UserTree $node): void
    {
        $descendants = UserTree::where('path', 'like', $node->path . $node->user_id . '/%')->get();

        foreach ($descendants as $descendant) {
            $newPath = $node->path . $node->user_id . '/';
            $newDepth = substr_count($newPath, '/') - 1;

            $descendant->update([
                'path' => $newPath,
                'depth' => $newDepth
            ]);
        }
    }

    /**
     * Ensure user has all necessary wallets initialized
     * Creates wallets if they don't exist
     */
    protected function ensureUserWalletsExist(User $user): void
    {
        $walletTypes = ['commission', 'fasttrack', 'autopool', 'club', 'main'];

        foreach ($walletTypes as $walletType) {
            $existingWallet = Wallet::where('user_id', $user->id)
                ->where('wallet_type', $walletType)
                ->where('currency', 'USD')
                ->first();

            if (!$existingWallet) {
                Wallet::create([
                    'user_id' => $user->id,
                    'wallet_type' => $walletType,
                    'currency' => 'USD',
                    'balance' => 0,
                    'pending_balance' => 0,
                ]);
                Log::info("Created {$walletType} wallet for user {$user->id}");
            }
        }
    }
}
