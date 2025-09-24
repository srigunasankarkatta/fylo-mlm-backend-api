<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\InvestmentPlan;
use Illuminate\Http\Request;

class InvestmentPlanController extends ApiController
{
    /**
     * Display a listing of active investment plans for customers.
     */
    public function index(Request $request)
    {
        $query = InvestmentPlan::active();

        // Filter by amount range if provided
        if ($request->filled('amount')) {
            $query->forAmount($request->amount);
        }

        // Filter by minimum amount
        if ($request->filled('min_amount')) {
            $query->where('min_amount', '<=', $request->min_amount);
        }

        // Filter by maximum amount
        if ($request->filled('max_amount')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('max_amount')
                    ->orWhere('max_amount', '>=', $request->max_amount);
            });
        }

        // Filter by duration range
        if ($request->filled('min_duration')) {
            $query->where('duration_days', '>=', $request->min_duration);
        }
        if ($request->filled('max_duration')) {
            $query->where('duration_days', '<=', $request->max_duration);
        }

        // Filter by profit range
        if ($request->filled('min_profit_percent')) {
            $query->where('daily_profit_percent', '>=', $request->min_profit_percent);
        }
        if ($request->filled('max_profit_percent')) {
            $query->where('daily_profit_percent', '<=', $request->max_profit_percent);
        }

        // Search functionality
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        // Order by
        $orderBy = $request->get('order_by', 'min_amount');
        $orderDirection = $request->get('order_direction', 'asc');

        // Validate order_by field to prevent SQL injection
        $allowedOrderFields = ['min_amount', 'max_amount', 'daily_profit_percent', 'duration_days', 'referral_percent', 'created_at'];
        if (!in_array($orderBy, $allowedOrderFields)) {
            $orderBy = 'min_amount';
        }

        $query->orderBy($orderBy, $orderDirection);

        // Pagination
        $perPage = min($request->get('per_page', 20), 100); // Max 100 items per page
        $plans = $query->paginate($perPage);

        // Transform the data for customer consumption
        $transformedPlans = $plans->getCollection()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_amount' => (float) $plan->min_amount,
                'max_amount' => $plan->max_amount ? (float) $plan->max_amount : null,
                'daily_profit_percent' => (float) $plan->daily_profit_percent,
                'duration_days' => $plan->duration_days,
                'referral_percent' => (float) $plan->referral_percent,
                'total_return_percent' => (float) $plan->total_return_percent,
                'is_active' => $plan->is_active,
                'version' => $plan->version,
                'created_at' => $plan->created_at,
                'updated_at' => $plan->updated_at,
                // Add calculated fields for customer convenience
                'min_investment_formatted' => number_format($plan->min_amount, 2),
                'max_investment_formatted' => $plan->max_amount ? number_format($plan->max_amount, 2) : 'No Limit',
                'daily_profit_formatted' => number_format($plan->daily_profit_percent, 2) . '%',
                'total_return_formatted' => number_format($plan->total_return_percent, 2) . '%',
                'referral_commission_formatted' => number_format($plan->referral_percent, 2) . '%',
                'duration_formatted' => $plan->duration_days . ' day' . ($plan->duration_days > 1 ? 's' : ''),
            ];
        });

        // Replace the collection with transformed data
        $plans->setCollection($transformedPlans);

        return $this->paginated($plans, 'Investment plans retrieved successfully');
    }

    /**
     * Display the specified investment plan for customers.
     */
    public function show($id)
    {
        $plan = InvestmentPlan::active()->find($id);

        if (!$plan) {
            return $this->notFound('Investment plan not found or not available');
        }

        // Transform the data for customer consumption
        $transformedPlan = [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'min_amount' => (float) $plan->min_amount,
            'max_amount' => $plan->max_amount ? (float) $plan->max_amount : null,
            'daily_profit_percent' => (float) $plan->daily_profit_percent,
            'duration_days' => $plan->duration_days,
            'referral_percent' => (float) $plan->referral_percent,
            'total_return_percent' => (float) $plan->total_return_percent,
            'is_active' => $plan->is_active,
            'version' => $plan->version,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
            // Add calculated fields for customer convenience
            'min_investment_formatted' => number_format($plan->min_amount, 2),
            'max_investment_formatted' => $plan->max_amount ? number_format($plan->max_amount, 2) : 'No Limit',
            'daily_profit_formatted' => number_format($plan->daily_profit_percent, 2) . '%',
            'total_return_formatted' => number_format($plan->total_return_percent, 2) . '%',
            'referral_commission_formatted' => number_format($plan->referral_percent, 2) . '%',
            'duration_formatted' => $plan->duration_days . ' day' . ($plan->duration_days > 1 ? 's' : ''),
            // Add investment examples
            'investment_examples' => $this->getInvestmentExamples($plan),
        ];

        return $this->success($transformedPlan, 'Investment plan retrieved successfully');
    }

    /**
     * Get investment examples for a plan.
     */
    private function getInvestmentExamples(InvestmentPlan $plan): array
    {
        $examples = [];

        // Example 1: Minimum investment
        $minInvestment = (float) $plan->min_amount;
        $examples[] = [
            'investment_amount' => $minInvestment,
            'investment_amount_formatted' => number_format($minInvestment, 2),
            'daily_return' => $plan->getDailyReturnAmount($minInvestment),
            'daily_return_formatted' => number_format($plan->getDailyReturnAmount($minInvestment), 2),
            'total_return' => $plan->getTotalReturnAmount($minInvestment),
            'total_return_formatted' => number_format($plan->getTotalReturnAmount($minInvestment), 2),
            'referral_commission' => $plan->getReferralCommissionAmount($minInvestment),
            'referral_commission_formatted' => number_format($plan->getReferralCommissionAmount($minInvestment), 2),
        ];

        // Example 2: Mid-range investment (if max amount exists)
        if ($plan->max_amount) {
            $midInvestment = (float) (($plan->min_amount + $plan->max_amount) / 2);
            $examples[] = [
                'investment_amount' => $midInvestment,
                'investment_amount_formatted' => number_format($midInvestment, 2),
                'daily_return' => $plan->getDailyReturnAmount($midInvestment),
                'daily_return_formatted' => number_format($plan->getDailyReturnAmount($midInvestment), 2),
                'total_return' => $plan->getTotalReturnAmount($midInvestment),
                'total_return_formatted' => number_format($plan->getTotalReturnAmount($midInvestment), 2),
                'referral_commission' => $plan->getReferralCommissionAmount($midInvestment),
                'referral_commission_formatted' => number_format($plan->getReferralCommissionAmount($midInvestment), 2),
            ];
        }

        // Example 3: Maximum investment (if max amount exists)
        if ($plan->max_amount) {
            $maxInvestment = (float) $plan->max_amount;
            $examples[] = [
                'investment_amount' => $maxInvestment,
                'investment_amount_formatted' => number_format($maxInvestment, 2),
                'daily_return' => $plan->getDailyReturnAmount($maxInvestment),
                'daily_return_formatted' => number_format($plan->getDailyReturnAmount($maxInvestment), 2),
                'total_return' => $plan->getTotalReturnAmount($maxInvestment),
                'total_return_formatted' => number_format($plan->getTotalReturnAmount($maxInvestment), 2),
                'referral_commission' => $plan->getReferralCommissionAmount($maxInvestment),
                'referral_commission_formatted' => number_format($plan->getReferralCommissionAmount($maxInvestment), 2),
            ];
        }

        return $examples;
    }
}
