<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletCredit;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $walletService): JsonResponse
    {
        $user = $request->user();
        $balance = $walletService->balances($user);
        $wallet = Wallet::query()->where('user_id', $user->id)->first();

        return response()->json([
            'data' => [
                'currency' => $balance['currency'],
                'available' => $balance['available'],
                'pending' => $balance['pending'],
                'wallet_exists' => $wallet !== null,
            ],
        ]);
    }

    public function credits(Request $request): JsonResponse
    {
        $walletId = Wallet::query()
            ->where('user_id', $request->user()->id)
            ->value('id');

        $credits = WalletCredit::query()
            ->where('wallet_id', $walletId ?? 0)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $credits->getCollection()->map(fn (WalletCredit $credit) => [
                'id' => $credit->id,
                'source_order_id' => $credit->source_order_id,
                'status' => $credit->status,
                'original_amount' => (string) $credit->original_amount,
                'remaining_amount' => (string) $credit->remaining_amount,
                'available_at' => $credit->available_at?->toISOString(),
                'expires_at' => $credit->expires_at?->toISOString(),
            ])->values(),
            'meta' => [
                'current_page' => $credits->currentPage(),
                'last_page' => $credits->lastPage(),
                'per_page' => $credits->perPage(),
                'total' => $credits->total(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $walletId = Wallet::query()
            ->where('user_id', $request->user()->id)
            ->value('id');

        $transactions = WalletTransaction::query()
            ->where('wallet_id', $walletId ?? 0)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (WalletTransaction $transaction) => [
                'id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'type' => $transaction->type,
                'amount' => (string) $transaction->amount,
                'created_at' => $transaction->created_at?->toISOString(),
            ])->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}