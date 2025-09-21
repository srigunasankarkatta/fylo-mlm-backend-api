<?php

namespace App\Services;

use App\Models\User;
use App\Models\Package;
use App\Models\UserPackage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessPurchaseJob;

class PurchaseService
{
    /**
     * Create purchase order (idempotent by idempotency_key)
     */
    public function createPurchaseOrder(User $user, Package $package, array $payload): UserPackage
    {
        // Idempotent: if an order exists for this user + idempotency_key, return it
        $existing = UserPackage::where('user_id', $user->id)
            ->where('idempotency_key', $payload['idempotency_key'])
            ->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($user, $package, $payload) {
            $order = UserPackage::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'amount_paid' => $payload['amount'],
                'payment_reference' => null,
                'payment_status' => 'pending',
                'purchase_at' => null,
                'assigned_level' => $package->level_number,
                'payment_meta' => $payload['meta'] ?? null,
                'idempotency_key' => $payload['idempotency_key'],
            ]);

            // Optionally create a payment intent via gateway and return redirect / client token
            // e.g. $paymentIntent = $this->gateway->createIntent(...); attach to $order->payment_meta

            return $order;
        });
    }

    /**
     * Confirm a purchase by idempotency_key + payment_reference + status.
     * This method is idempotent: repeated confirm calls won't double process.
     */
    public function confirmPurchase(array $payload)
    {
        // Find order by idempotency_key
        $order = UserPackage::where('idempotency_key', $payload['idempotency_key'])->first();
        if (!$order) return 'Order not found';

        if ($order->payment_status === 'completed') {
            // Already processed; idempotent success
            return true;
        }

        if ($payload['payment_status'] !== 'completed') {
            // mark failed and return
            $order->update([
                'payment_status' => 'failed',
                'payment_reference' => $payload['payment_reference'],
                'payment_meta' => $payload['gateway_meta'] ?? null,
            ]);
            return 'Payment failed';
        }

        // Payment completed — process
        DB::transaction(function () use ($order, $payload) {
            $order->update([
                'payment_status' => 'completed',
                'payment_reference' => $payload['payment_reference'],
                'payment_meta' => array_merge((array)$order->payment_meta, $payload['gateway_meta'] ?? []),
                'purchase_at' => now()
            ]);

            // Dispatch processing job to compute incomes and ledger entries.
            // Option: process small distributions synchronously here if you prefer immediate credit.
            dispatch(new ProcessPurchaseJob($order->id));
        });

        return true;
    }
}
