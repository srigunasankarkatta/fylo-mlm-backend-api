<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPackage;
use App\Models\Package;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\PurchaseService;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserPackageController extends Controller
{
    use ApiResponse;

    protected PurchaseService $purchaseService;

    public function __construct(PurchaseService $purchaseService)
    {
        $this->purchaseService = $purchaseService;
    }

    // GET /api/user/packages
    public function index(Request $request)
    {
        $user = JWTAuth::user();
        $q = UserPackage::with('package')
            ->where('user_id', $user->id)
            ->orderByDesc('purchase_at');

        $items = $q->paginate($request->get('per_page', 20));
        return $this->paginated($items, 'User packages retrieved');
    }

    // GET /api/user/packages/{id}
    public function show($id)
    {
        $user = JWTAuth::user();
        $pkg = UserPackage::with('package')->where('user_id', $user->id)->where('id', $id)->first();
        if (!$pkg) return $this->notFound('User package not found');
        return $this->success($pkg, 'User package retrieved');
    }

    /**
     * Initiate purchase
     * POST /api/user/packages
     */
    public function store(Request $request)
    {
        $user = JWTAuth::user();

        $payload = $request->validate([
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'amount' => 'required|numeric|min:0',
            'payment_gateway' => 'required|string|max:50',
            'idempotency_key' => 'required|string|max:100', // client-generated unique key
            'meta' => 'nullable|array'
        ]);

        // Check package active and price matching (basic)
        $package = Package::find($payload['package_id']);
        if (!$package || !$package->is_active) {
            return $this->error('Invalid package', 422);
        }
        if (bccomp((string)$package->price, (string)$payload['amount'], 8) !== 0) {
            return $this->error('Amount mismatch with package price', 422);
        }

        // Create or return existing order (idempotent)
        try {
            $order = $this->purchaseService->createPurchaseOrder($user, $package, $payload);
            return $this->success($order, 'Purchase initiated', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to initiate purchase: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Confirm purchase (payment gateway webhook/callback)
     * POST /api/user/packages/confirm
     */
    public function confirm(Request $request)
    {
        $payload = $request->validate([
            'idempotency_key' => 'required|string|max:100',
            'payment_reference' => 'required|string|max:255',
            'payment_status' => ['required', Rule::in(['completed', 'failed'])],
            'gateway' => 'required|string',
            'gateway_meta' => 'nullable|array'
        ]);

        try {
            $result = $this->purchaseService->confirmPurchase($payload);
            if ($result === true) {
                return $this->success(null, 'Purchase confirmed');
            }
            return $this->error($result ?? 'Confirm failed', 400);
        } catch (\Exception $e) {
            return $this->error('Confirm error: ' . $e->getMessage(), 500);
        }
    }
}
