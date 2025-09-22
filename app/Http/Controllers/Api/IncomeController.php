<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomeRecord;
use App\Models\Wallet;
use App\Models\LedgerTransaction;
use App\Models\ClubEntry;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    use ApiResponse;

    /**
     * Get user's income summary
     * GET /api/user/income/summary
     */
    public function summary(Request $request)
    {
        $user = JWTAuth::user();

        // Get income statistics
        $incomeStats = IncomeRecord::getUserIncomeStats($user->id);

        // Get wallet balances
        $wallets = Wallet::where('user_id', $user->id)->get();
        $walletBalances = [];
        foreach ($wallets as $wallet) {
            $walletBalances[$wallet->wallet_type] = [
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'currency' => $wallet->currency
            ];
        }

        // Get total wallet balance
        $totalBalance = $wallets->sum('balance');
        $totalPending = $wallets->sum('pending_balance');

        $summary = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'income_summary' => $incomeStats,
            'wallet_summary' => [
                'total_balance' => $totalBalance,
                'total_pending' => $totalPending,
                'wallets' => $walletBalances
            ],
            'income_types' => [
                'level' => 'Level Income - Fixed amount per upline level',
                'fasttrack' => 'Fasttrack Income - Percentage from direct referrals',
                'club' => 'Club Income - Matrix expansion rewards',
                'company_allocation' => 'Company Allocation - Pool distribution'
            ]
        ];

        return $this->success($summary, 'Income summary retrieved successfully');
    }

    /**
     * Get user's income records by type
     * GET /api/user/income/records?type=level&status=paid&per_page=20
     */
    public function records(Request $request)
    {
        $user = JWTAuth::user();

        $query = IncomeRecord::where('user_id', $user->id)
            ->with(['originUser', 'userPackage.package', 'incomeConfig']);

        // Filter by income type
        if ($request->filled('type')) {
            $query->where('income_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by currency
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        // Date range filters
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Sort by created_at desc by default
        $query->orderBy('created_at', 'desc');

        $records = $query->paginate($request->get('per_page', 20));

        // Transform the data for better API response
        $transformedRecords = $records->getCollection()->map(function ($record) {
            return [
                'id' => $record->id,
                'income_type' => $record->income_type,
                'amount' => $record->amount,
                'currency' => $record->currency,
                'status' => $record->status,
                'description' => $this->getIncomeDescription($record),
                'origin_user' => $record->originUser ? [
                    'id' => $record->originUser->id,
                    'name' => $record->originUser->name
                ] : null,
                'package' => $record->userPackage && $record->userPackage->package ? [
                    'id' => $record->userPackage->package->id,
                    'name' => $record->userPackage->package->name,
                    'level' => $record->userPackage->package->level_number
                ] : null,
                'created_at' => $record->created_at,
                'updated_at' => $record->updated_at
            ];
        });

        $records->setCollection($transformedRecords);

        return $this->paginated($records, 'Income records retrieved successfully');
    }

    /**
     * Get user's income by type
     * GET /api/user/income/by-type
     */
    public function byType(Request $request)
    {
        $user = JWTAuth::user();

        $incomeTypes = ['level', 'fasttrack', 'club', 'company_allocation'];
        $results = [];

        foreach ($incomeTypes as $type) {
            $totalPaid = IncomeRecord::getTotalIncomeByType($user->id, $type);
            $totalPending = IncomeRecord::forUser($user->id)
                ->byType($type)
                ->pending()
                ->sum('amount');

            $results[$type] = [
                'type' => $type,
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'total_earned' => $totalPaid + $totalPending,
                'description' => $this->getIncomeTypeDescription($type)
            ];
        }

        return $this->success($results, 'Income by type retrieved successfully');
    }

    /**
     * Get user's wallet details
     * GET /api/user/wallets
     */
    public function wallets(Request $request)
    {
        $user = JWTAuth::user();

        $wallets = Wallet::where('user_id', $user->id)
            ->orderBy('wallet_type')
            ->get();

        $walletData = $wallets->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'wallet_type' => $wallet->wallet_type,
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'currency' => $wallet->currency,
                'description' => $this->getWalletDescription($wallet->wallet_type),
                'created_at' => $wallet->created_at,
                'updated_at' => $wallet->updated_at
            ];
        });

        return $this->success($walletData, 'Wallet details retrieved successfully');
    }

    /**
     * Get user's club matrix information
     * GET /api/user/club/matrix
     */
    public function clubMatrix(Request $request)
    {
        $user = JWTAuth::user();

        // Get club entries where user is the sponsor
        $clubEntries = ClubEntry::where('sponsor_id', $user->id)
            ->with(['user'])
            ->orderBy('level')
            ->orderBy('created_at')
            ->get();

        // Group by level
        $matrixByLevel = [];
        foreach ($clubEntries as $entry) {
            $level = $entry->level;
            if (!isset($matrixByLevel[$level])) {
                $matrixByLevel[$level] = [];
            }
            $matrixByLevel[$level][] = [
                'id' => $entry->id,
                'user' => [
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                    'email' => $entry->user->email
                ],
                'status' => $entry->status,
                'created_at' => $entry->created_at
            ];
        }

        // Get club income earned
        $clubIncome = IncomeRecord::where('user_id', $user->id)
            ->where('income_type', 'club')
            ->sum('amount');

        $matrixData = [
            'sponsor' => [
                'id' => $user->id,
                'name' => $user->name
            ],
            'total_club_income' => $clubIncome,
            'matrix_levels' => $matrixByLevel,
            'total_members' => $clubEntries->count(),
            'levels_filled' => count($matrixByLevel)
        ];

        return $this->success($matrixData, 'Club matrix retrieved successfully');
    }

    /**
     * Get user's ledger transactions
     * GET /api/user/ledger/transactions
     */
    public function ledgerTransactions(Request $request)
    {
        $user = JWTAuth::user();

        $query = LedgerTransaction::where(function ($q) use ($user) {
            $q->where('user_from', $user->id)
                ->orWhere('user_to', $user->id);
        })->with(['userFrom', 'userTo', 'walletFrom', 'walletTo']);

        // Filter by transaction type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // Transform the data
        $transformedTransactions = $transactions->getCollection()->map(function ($transaction) use ($user) {
            $isIncoming = $transaction->user_to == $user->id;

            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'description' => $transaction->description,
                'direction' => $isIncoming ? 'incoming' : 'outgoing',
                'from_user' => $transaction->userFrom ? [
                    'id' => $transaction->userFrom->id,
                    'name' => $transaction->userFrom->name
                ] : null,
                'to_user' => $transaction->userTo ? [
                    'id' => $transaction->userTo->id,
                    'name' => $transaction->userTo->name
                ] : null,
                'wallet_type' => $transaction->walletTo ? $transaction->walletTo->wallet_type : null,
                'created_at' => $transaction->created_at
            ];
        });

        $transactions->setCollection($transformedTransactions);

        return $this->paginated($transactions, 'Ledger transactions retrieved successfully');
    }

    /**
     * Get income type description
     */
    private function getIncomeTypeDescription(string $type): string
    {
        $descriptions = [
            'level' => 'Level Income - Fixed amount earned from each upline level when downline members purchase packages',
            'fasttrack' => 'Fasttrack Income - Percentage earned from direct referrals when they purchase packages',
            'club' => 'Club Income - Matrix expansion rewards earned when your club matrix fills with new members',
            'company_allocation' => 'Company Allocation - Pool distribution from company allocation fund'
        ];

        return $descriptions[$type] ?? 'Unknown income type';
    }

    /**
     * Get wallet type description
     */
    private function getWalletDescription(string $walletType): string
    {
        $descriptions = [
            'commission' => 'Commission Wallet - Stores Level Income earnings',
            'fasttrack' => 'Fasttrack Wallet - Stores Fasttrack Income earnings',
            'club' => 'Club Wallet - Stores Club Income earnings',
            'autopool' => 'Auto Pool Wallet - Stores Auto Pool Income earnings',
            'main' => 'Main Wallet - General purpose wallet'
        ];

        return $descriptions[$walletType] ?? 'Unknown wallet type';
    }

    /**
     * Get income record description
     */
    private function getIncomeDescription(IncomeRecord $record): string
    {
        $originUserName = $record->originUser ? $record->originUser->name : 'Unknown User';

        $descriptions = [
            'level' => "Level Income from {$originUserName}",
            'fasttrack' => "Fasttrack Income from {$originUserName}",
            'club' => "Club Income - Level {$record->reference_id}",
            'company_allocation' => "Company Allocation from {$originUserName}"
        ];

        return $descriptions[$record->income_type] ?? 'Income earned';
    }
}
