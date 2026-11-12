<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDiscountCode;
use Illuminate\Http\Request;

class CustomerDiscountController extends Controller
{
    public function index(Request $request)
    {
        $codes = CustomerDiscountCode::query()
            ->where('customer_id', $request->user()->id)
            ->with('category:id,name')
            ->orderByDesc('id')
            ->get();

        $activeCount = CustomerDiscountCode::query()
            ->where('customer_id', $request->user()->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->count();

        return response()->json([
            'data' => $codes->map(function (CustomerDiscountCode $code): array {
                $status = $code->status();

                return [
                    'id' => $code->id,
                    'code' => $code->code,
                    'percentage' => $code->percentage,
                    'scope' => $code->scope,
                    'category' => $code->category ? [
                        'id' => $code->category->id,
                        'name' => $code->category->name,
                    ] : null,
                    'expires_at' => $code->expires_at->toIso8601String(),
                    'is_active' => $code->is_active,
                    'status' => $status,
                    'status_label' => match (app()->getLocale()) {
                        'fa' => match ($status) {
                            'active' => 'فعال',
                            'expired' => 'منقضی‌شده',
                            default => 'غیرفعال',
                        },
                        default => ucfirst($status),
                    },
                ];
            })->values(),
            'meta' => [
                'active_count' => $activeCount,
            ],
        ]);
    }
}
