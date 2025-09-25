<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\UserInvestment;
use App\Models\InvestmentPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserInvestmentController extends ApiController
{
    /**
     * Display a listing of user's investments.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = $user->investments();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by plan
        if ($request->filled('plan_id')) {
            $query->where('investment_plan_id', $request->plan_id);
        }

        // Date range filtering
        if ($request->filled('invested_from')) {
            $query->where('invested_at', '>=', $request->invested_from);
        }
        if ($request->filled('invested_to')) {
            $query->where('invested_at', '<=', $request->invested_to);
        }

        // Amount range filtering
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', $request->amount_from);
        }
        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', $request->amount_to);
        }

        // Include relationships
        $query->with(['investmentPlan', 'referrer']);

        // Order by
        $orderBy = $request->get('order_by', 'invested_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $investments = $query->paginate($request->get('per_page', 20));

        // Transform the data for customer consumption
        $transformedInvestments = $investments->getCollection()->map(function ($investment) {
            return [
                'id' => $investment->id,
                'investment_plan' => [
                    'id' => $investment->investmentPlan->id,
                    'name' => $investment->investmentPlan->name,
                    'code' => $investment->investmentPlan->code,
                ],
                'amount' => (float) $investment->amount,
                'amount_formatted' => $investment->formatted_amount,
                'daily_profit_percent' => (float) $investment->daily_profit_percent,
                'daily_profit_percent_formatted' => $investment->formatted_daily_profit_percent,
                'duration_days' => $investment->duration_days,
                'status' => $investment->status,
                'invested_at' => $investment->invested_at,
                'start_at' => $investment->start_at,
                'end_at' => $investment->end_at,
                'matured_at' => $investment->matured_at,
                'accrued_interest' => (float) $investment->accrued_interest,
                'accrued_interest_formatted' => $investment->formatted_accrued_interest,
                'total_payout' => (float) $investment->total_payout,
                'total_payout_formatted' => $investment->formatted_total_payout,
                'referral_commission' => (float) $investment->referral_commission,
                'referral_commission_formatted' => $investment->formatted_referral_commission,
                'referrer' => $investment->referrer ? [
                    'id' => $investment->referrer->id,
                    'name' => $investment->referrer->name,
                    'email' => $investment->referrer->email,
                ] : null,
                'daily_interest_amount' => $investment->getDailyInterestAmount(),
                'daily_interest_amount_formatted' => number_format($investment->getDailyInterestAmount(), 2),
                'total_expected_return' => $investment->getTotalExpectedReturn(),
                'total_expected_return_formatted' => number_format($investment->getTotalExpectedReturn(), 2),
                'remaining_days' => $investment->getRemainingDays(),
                'elapsed_days' => $investment->getElapsedDays(),
                'progress_percentage' => $investment->getProgressPercentage(),
                'created_at' => $investment->created_at,
                'updated_at' => $investment->updated_at,
            ];
        });

        // Replace the collection with transformed data
        $investments->setCollection($transformedInvestments);

        return $this->paginated($investments, 'User investments retrieved successfully');
    }

    /**
     * Display the specified investment.
     */
    public function show($id)
    {
        $user = auth()->user();
        $investment = $user->investments()->with(['investmentPlan', 'referrer'])->find($id);

        if (!$investment) {
            return $this->notFound('Investment not found');
        }

        // Transform the data for customer consumption
        $transformedInvestment = [
            'id' => $investment->id,
            'investment_plan' => [
                'id' => $investment->investmentPlan->id,
                'name' => $investment->investmentPlan->name,
                'code' => $investment->investmentPlan->code,
                'description' => $investment->investmentPlan->description,
            ],
            'amount' => (float) $investment->amount,
            'amount_formatted' => $investment->formatted_amount,
            'daily_profit_percent' => (float) $investment->daily_profit_percent,
            'daily_profit_percent_formatted' => $investment->formatted_daily_profit_percent,
            'duration_days' => $investment->duration_days,
            'status' => $investment->status,
            'invested_at' => $investment->invested_at,
            'start_at' => $investment->start_at,
            'end_at' => $investment->end_at,
            'matured_at' => $investment->matured_at,
            'accrued_interest' => (float) $investment->accrued_interest,
            'accrued_interest_formatted' => $investment->formatted_accrued_interest,
            'total_payout' => (float) $investment->total_payout,
            'total_payout_formatted' => $investment->formatted_total_payout,
            'referral_commission' => (float) $investment->referral_commission,
            'referral_commission_formatted' => $investment->formatted_referral_commission,
            'referrer' => $investment->referrer ? [
                'id' => $investment->referrer->id,
                'name' => $investment->referrer->name,
                'email' => $investment->referrer->email,
            ] : null,
            'daily_interest_amount' => $investment->getDailyInterestAmount(),
            'daily_interest_amount_formatted' => number_format($investment->getDailyInterestAmount(), 2),
            'total_expected_return' => $investment->getTotalExpectedReturn(),
            'total_expected_return_formatted' => number_format($investment->getTotalExpectedReturn(), 2),
            'remaining_days' => $investment->getRemainingDays(),
            'elapsed_days' => $investment->getElapsedDays(),
            'progress_percentage' => $investment->getProgressPercentage(),
            'created_at' => $investment->created_at,
            'updated_at' => $investment->updated_at,
        ];

        return $this->success($transformedInvestment, 'Investment retrieved successfully');
    }

    /**
     * Purchase an investment plan.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'investment_plan_id' => 'required|integer|exists:investment_plans,id',
            'amount' => 'required|numeric|min:0.01',
            'referrer_id' => 'nullable|integer|exists:users,id',
            'payment_method' => 'required|string|in:wallet,bank_transfer,crypto',
            'payment_reference' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        // Get the investment plan
        $plan = InvestmentPlan::active()->find($validated['investment_plan_id']);
        if (!$plan) {
            return $this->notFound('Investment plan not found or not active');
        }

        // Validate amount against plan limits
        if ($validated['amount'] < $plan->min_amount) {
            return $this->validationError([
                'amount' => ["Minimum investment amount is {$plan->min_amount}"]
            ]);
        }

        if ($plan->max_amount && $validated['amount'] > $plan->max_amount) {
            return $this->validationError([
                'amount' => ["Maximum investment amount is {$plan->max_amount}"]
            ]);
        }

        // Validate referrer if provided
        $referrer = null;
        if (isset($validated['referrer_id']) && $validated['referrer_id']) {
            $referrer = User::find($validated['referrer_id']);
            if (!$referrer) {
                return $this->notFound('Referrer not found');
            }
        }

        // Calculate referral commission
        $referralCommission = 0;
        if ($referrer && $plan->referral_percent > 0) {
            $referralCommission = $validated['amount'] * ($plan->referral_percent / 100);
        }

        try {
            DB::beginTransaction();

            // Create the investment
            $investment = UserInvestment::create([
                'user_id' => $user->id,
                'investment_plan_id' => $plan->id,
                'amount' => $validated['amount'],
                'daily_profit_percent' => $plan->daily_profit_percent, // Snapshot
                'duration_days' => $plan->duration_days, // Snapshot
                'status' => UserInvestment::STATUS_PENDING,
                'referrer_id' => $referrer?->id,
                'referral_commission' => $referralCommission,
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'payment_method' => $validated['payment_method'],
                    'payment_reference' => $validated['payment_reference'],
                ]),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // TODO: Process payment based on payment_method
            // For now, we'll just mark it as pending
            // In a real implementation, you would:
            // 1. Process wallet deduction if payment_method is 'wallet'
            // 2. Create payment record for bank_transfer or crypto
            // 3. Send confirmation email
            // 4. Notify referrer if applicable

            DB::commit();

            // Load relationships for response
            $investment->load(['investmentPlan', 'referrer']);

            // Transform the response
            $transformedInvestment = [
                'id' => $investment->id,
                'investment_plan' => [
                    'id' => $investment->investmentPlan->id,
                    'name' => $investment->investmentPlan->name,
                    'code' => $investment->investmentPlan->code,
                ],
                'amount' => (float) $investment->amount,
                'amount_formatted' => $investment->formatted_amount,
                'daily_profit_percent' => (float) $investment->daily_profit_percent,
                'daily_profit_percent_formatted' => $investment->formatted_daily_profit_percent,
                'duration_days' => $investment->duration_days,
                'status' => $investment->status,
                'invested_at' => $investment->invested_at,
                'referral_commission' => (float) $investment->referral_commission,
                'referral_commission_formatted' => $investment->formatted_referral_commission,
                'referrer' => $investment->referrer ? [
                    'id' => $investment->referrer->id,
                    'name' => $investment->referrer->name,
                ] : null,
                'daily_interest_amount' => $investment->getDailyInterestAmount(),
                'daily_interest_amount_formatted' => number_format($investment->getDailyInterestAmount(), 2),
                'total_expected_return' => $investment->getTotalExpectedReturn(),
                'total_expected_return_formatted' => number_format($investment->getTotalExpectedReturn(), 2),
                'created_at' => $investment->created_at,
            ];

            return $this->success($transformedInvestment, 'Investment created successfully. Please complete payment to activate.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create investment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get investment summary for user.
     */
    public function summary()
    {
        $user = auth()->user();

        $investments = $user->investments();

        $summary = [
            'total_investments' => $investments->count(),
            'total_invested' => $investments->sum('amount'),
            'total_accrued_interest' => $investments->sum('accrued_interest'),
            'total_payout' => $investments->sum('total_payout'),
            'total_referral_commission' => $investments->sum('referral_commission'),
            'active_investments' => $investments->where('status', UserInvestment::STATUS_ACTIVE)->count(),
            'completed_investments' => $investments->where('status', UserInvestment::STATUS_COMPLETED)->count(),
            'pending_investments' => $investments->where('status', UserInvestment::STATUS_PENDING)->count(),
            'by_status' => [
                'pending' => $investments->where('status', UserInvestment::STATUS_PENDING)->sum('amount'),
                'active' => $investments->where('status', UserInvestment::STATUS_ACTIVE)->sum('amount'),
                'completed' => $investments->where('status', UserInvestment::STATUS_COMPLETED)->sum('amount'),
                'cancelled' => $investments->where('status', UserInvestment::STATUS_CANCELLED)->sum('amount'),
                'withdrawn' => $investments->where('status', UserInvestment::STATUS_WITHDRAWN)->sum('amount'),
            ],
        ];

        // Add formatted values
        $summary['total_invested_formatted'] = number_format($summary['total_invested'], 2);
        $summary['total_accrued_interest_formatted'] = number_format($summary['total_accrued_interest'], 2);
        $summary['total_payout_formatted'] = number_format($summary['total_payout'], 2);
        $summary['total_referral_commission_formatted'] = number_format($summary['total_referral_commission'], 2);

        return $this->success($summary, 'Investment summary retrieved successfully');
    }

    /**
     * Get user investment transactions with earnings details.
     */
    public function transactions(Request $request)
    {
        $user = auth()->user();

        $query = $user->investments();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by plan
        if ($request->filled('plan_id')) {
            $query->where('investment_plan_id', $request->plan_id);
        }

        // Date range filtering
        if ($request->filled('from_date')) {
            $query->where('invested_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('invested_at', '<=', $request->to_date);
        }

        // Amount range filtering
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        // Include relationships
        $query->with(['investmentPlan', 'referrer']);

        // Order by
        $orderBy = $request->get('order_by', 'invested_at');
        $orderDirection = $request->get('order_direction', 'desc');
        $query->orderBy($orderBy, $orderDirection);

        $investments = $query->paginate($request->get('per_page', 20));

        // Transform the data for customer consumption
        $transformedInvestments = $investments->getCollection()->map(function ($investment) {
            return [
                'transaction_id' => $investment->id,
                'investment_plan' => [
                    'id' => $investment->investmentPlan->id,
                    'name' => $investment->investmentPlan->name,
                    'code' => $investment->investmentPlan->code,
                    'description' => $investment->investmentPlan->description,
                ],
                'transaction_details' => [
                    'amount_invested' => (float) $investment->amount,
                    'amount_invested_formatted' => $investment->formatted_amount,
                    'daily_profit_percent' => (float) $investment->daily_profit_percent,
                    'daily_profit_percent_formatted' => $investment->formatted_daily_profit_percent,
                    'duration_days' => $investment->duration_days,
                    'status' => $investment->status,
                    'invested_at' => $investment->invested_at,
                    'start_at' => $investment->start_at,
                    'end_at' => $investment->end_at,
                    'matured_at' => $investment->matured_at,
                ],
                'earnings_details' => [
                    'accrued_interest' => (float) $investment->accrued_interest,
                    'accrued_interest_formatted' => $investment->formatted_accrued_interest,
                    'total_payout' => (float) $investment->total_payout,
                    'total_payout_formatted' => $investment->formatted_total_payout,
                    'daily_interest_amount' => $investment->getDailyInterestAmount(),
                    'daily_interest_amount_formatted' => number_format($investment->getDailyInterestAmount(), 2),
                    'total_expected_return' => $investment->getTotalExpectedReturn(),
                    'total_expected_return_formatted' => number_format($investment->getTotalExpectedReturn(), 2),
                    'remaining_days' => $investment->getRemainingDays(),
                    'elapsed_days' => $investment->getElapsedDays(),
                    'progress_percentage' => $investment->getProgressPercentage(),
                ],
                'referral_details' => [
                    'referral_commission' => (float) $investment->referral_commission,
                    'referral_commission_formatted' => $investment->formatted_referral_commission,
                    'referrer' => $investment->referrer ? [
                        'id' => $investment->referrer->id,
                        'name' => $investment->referrer->name,
                        'email' => $investment->referrer->email,
                    ] : null,
                ],
                'metadata' => $investment->metadata,
                'created_at' => $investment->created_at,
                'updated_at' => $investment->updated_at,
            ];
        });

        // Replace the collection with transformed data
        $investments->setCollection($transformedInvestments);

        return $this->paginated($investments, 'Investment transactions retrieved successfully');
    }

    /**
     * Get user earnings summary by period.
     */
    public function earnings(Request $request)
    {
        $user = auth()->user();

        $period = $request->get('period', 'all'); // all, today, week, month, year
        $query = $user->investments();

        // Apply period filter
        switch ($period) {
            case 'today':
                $query->whereDate('invested_at', today());
                break;
            case 'week':
                $query->whereBetween('invested_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('invested_at', now()->month)
                    ->whereYear('invested_at', now()->year);
                break;
            case 'year':
                $query->whereYear('invested_at', now()->year);
                break;
        }

        $investments = $query->get();

        $earnings = [
            'period' => $period,
            'total_investments' => $investments->count(),
            'total_invested' => $investments->sum('amount'),
            'total_accrued_interest' => $investments->sum('accrued_interest'),
            'total_payout' => $investments->sum('total_payout'),
            'total_referral_commission' => $investments->sum('referral_commission'),
            'net_earnings' => $investments->sum('accrued_interest') + $investments->sum('referral_commission'),
            'by_status' => [
                'pending' => [
                    'count' => $investments->where('status', UserInvestment::STATUS_PENDING)->count(),
                    'amount' => $investments->where('status', UserInvestment::STATUS_PENDING)->sum('amount'),
                ],
                'active' => [
                    'count' => $investments->where('status', UserInvestment::STATUS_ACTIVE)->count(),
                    'amount' => $investments->where('status', UserInvestment::STATUS_ACTIVE)->sum('amount'),
                    'accrued_interest' => $investments->where('status', UserInvestment::STATUS_ACTIVE)->sum('accrued_interest'),
                ],
                'completed' => [
                    'count' => $investments->where('status', UserInvestment::STATUS_COMPLETED)->count(),
                    'amount' => $investments->where('status', UserInvestment::STATUS_COMPLETED)->sum('amount'),
                    'total_payout' => $investments->where('status', UserInvestment::STATUS_COMPLETED)->sum('total_payout'),
                ],
                'cancelled' => [
                    'count' => $investments->where('status', UserInvestment::STATUS_CANCELLED)->count(),
                    'amount' => $investments->where('status', UserInvestment::STATUS_CANCELLED)->sum('amount'),
                ],
                'withdrawn' => [
                    'count' => $investments->where('status', UserInvestment::STATUS_WITHDRAWN)->count(),
                    'amount' => $investments->where('status', UserInvestment::STATUS_WITHDRAWN)->sum('amount'),
                ],
            ],
        ];

        // Add formatted values
        $earnings['total_invested_formatted'] = number_format($earnings['total_invested'], 2);
        $earnings['total_accrued_interest_formatted'] = number_format($earnings['total_accrued_interest'], 2);
        $earnings['total_payout_formatted'] = number_format($earnings['total_payout'], 2);
        $earnings['total_referral_commission_formatted'] = number_format($earnings['total_referral_commission'], 2);
        $earnings['net_earnings_formatted'] = number_format($earnings['net_earnings'], 2);

        return $this->success($earnings, 'Earnings summary retrieved successfully');
    }
}
