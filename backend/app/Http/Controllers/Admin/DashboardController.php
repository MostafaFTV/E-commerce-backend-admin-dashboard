<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\JalaliCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $user = $request->user();

        $can = function (
            string $permission
        ) use ($user): bool {
            return $user->isSuperAdmin()
                || $user->hasPermission(
                    $permission
                );
        };

        $data = [
            'generated_at' => now()
                ->toISOString(),

            'access' => [
                'orders' => $can(
                    'orders.view'
                ),
                'order_financials' => $can(
                    'orders.manage'
                ),
                'customers' => $can(
                    'customers.view'
                ),
                'products' => $can(
                    'products.view'
                ),
                'inventory' => $can(
                    'inventory.view'
                ),
                'categories' => $can(
                    'categories.view'
                ),
                'collections' => $can(
                    'collections.view'
                ),
                'activity_logs' => $can(
                    'activity_logs.view'
                ),
            ],
        ];

        if ($can('orders.view')) {
            $data['orders'] =
                $this->orderSummary(
                    $can(
                        'orders.manage'
                    )
                );
        }

        if ($can('customers.view')) {
            $customerQuery =
                User::query()
                    ->whereHas(
                        'roles',
                        function ($query) {
                            $query->where(
                                'slug',
                                'customer'
                            );
                        }
                    );

            $data['customers'] = [
                'total' => (
                    clone $customerQuery
                )->count(),

                'new_last_30_days' => (
                    clone $customerQuery
                )
                    ->where(
                        'created_at',
                        '>=',
                        now()->subDays(30)
                    )
                    ->count(),
            ];
        }

        if ($can('products.view')) {
            $productStatuses =
                Product::query()
                    ->selectRaw(
                        'status, COUNT(*) as total'
                    )
                    ->groupBy('status')
                    ->pluck(
                        'total',
                        'status'
                    )
                    ->map(
                        fn ($value) =>
                            (int) $value
                    )
                    ->all();

            $data['products'] = [
                'total' =>
                    Product::query()
                        ->count(),

                'statuses' =>
                    $productStatuses,
            ];
        }

        if ($can('inventory.view')) {
            $summary =
                Inventory::query()
                    ->selectRaw('
                        COUNT(*) as records,
                        COALESCE(
                            SUM(quantity),
                            0
                        ) as physical_units,
                        COALESCE(
                            SUM(reserved_quantity),
                            0
                        ) as reserved_units,
                        COALESCE(
                            SUM(
                                GREATEST(
                                    quantity - reserved_quantity,
                                    0
                                )
                            ),
                            0
                        ) as available_units,
                        SUM(
                            CASE
                                WHEN (
                                    quantity - reserved_quantity
                                ) <= 0
                                THEN 1
                                ELSE 0
                            END
                        ) as out_of_stock,
                        SUM(
                            CASE
                                WHEN (
                                    quantity - reserved_quantity
                                ) > 0
                                AND (
                                    quantity - reserved_quantity
                                ) <= low_stock_threshold
                                THEN 1
                                ELSE 0
                            END
                        ) as low_stock,
                        SUM(
                            CASE
                                WHEN (
                                    quantity - reserved_quantity
                                ) > low_stock_threshold
                                THEN 1
                                ELSE 0
                            END
                        ) as healthy
                    ')
                    ->first();

            $data['inventory'] = [
                'records' =>
                    (int) (
                        $summary->records ?? 0
                    ),

                'physical_units' =>
                    (int) (
                        $summary->physical_units ?? 0
                    ),

                'reserved_units' =>
                    (int) (
                        $summary->reserved_units ?? 0
                    ),

                'available_units' =>
                    (int) (
                        $summary->available_units ?? 0
                    ),

                'healthy' =>
                    (int) (
                        $summary->healthy ?? 0
                    ),

                'low_stock' =>
                    (int) (
                        $summary->low_stock ?? 0
                    ),

                'out_of_stock' =>
                    (int) (
                        $summary->out_of_stock ?? 0
                    ),
            ];
        }

        if ($can('categories.view')) {
            $data['categories'] = [
                'total' =>
                    Category::query()
                        ->count(),
            ];
        }

        if ($can('collections.view')) {
            $data['collections'] = [
                'total' =>
                    Collection::query()
                        ->count(),
            ];
        }

        if (
            $can(
                'activity_logs.view'
            )
        ) {
            $data['activity_logs'] =
                ActivityLog::query()
                    ->with([
                        'user:id,first_name,last_name',
                    ])
                    ->latest('id')
                    ->limit(6)
                    ->get()
                    ->map(
                        function (
                            ActivityLog $log
                        ): array {
                            return [
                                'id' =>
                                    $log->id,

                                'action' =>
                                    $log->action,

                                'description' =>
                                    $log->description,

                                'created_at' =>
                                    $log->created_at
                                        ?->toISOString(),

                                'user' =>
                                    $log->user
                                        ? [
                                            'id' =>
                                                $log
                                                    ->user
                                                    ->id,

                                            'name' =>
                                                trim(
                                                    implode(
                                                        ' ',
                                                        array_filter([
                                                            $log
                                                                ->user
                                                                ->first_name,
                                                            $log
                                                                ->user
                                                                ->last_name,
                                                        ])
                                                    )
                                                ),
                                        ]
                                        : null,
                            ];
                        }
                    )
                    ->values()
                    ->all();
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    private function orderSummary(
        bool $includeFinancials
    ): array {
        $statuses =
            Order::query()
                ->selectRaw(
                    'status, COUNT(*) as total'
                )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                )
                ->map(
                    fn ($value) =>
                        (int) $value
                )
                ->all();

        $months = [];

        [$jalaliYear, $jalaliMonth] =
            JalaliCalendar::yearAndMonth(
                CarbonImmutable::now('Asia/Tehran')
            );

        for ($offset = 5; $offset >= 0; $offset--) {
            $index = $jalaliYear * 12
                + $jalaliMonth - 1 - $offset;

            $year = intdiv($index, 12);
            $month = ($index % 12) + 1;

            $from = JalaliCalendar::monthStart(
                $year,
                $month
            );

            $to = $month === 12
                ? JalaliCalendar::monthStart($year + 1, 1)
                : JalaliCalendar::monthStart($year, $month + 1);

            $months[] = [
                'key' => sprintf('%04d-%02d', $year, $month),
                'label' => JalaliCalendar::monthName($month),
                'orders' => Order::query()
                    ->where(
                        'created_at',
                        '>=',
                        $from->setTimezone('UTC')
                    )
                    ->where(
                        'created_at',
                        '<',
                        $to->setTimezone('UTC')
                    )
                    ->count(),
            ];
        }

        $result = [
            'total' =>
                Order::query()
                    ->count(),

            'statuses' =>
                $statuses,

            'monthly' =>
                $months,
        ];

        if (!$includeFinancials) {
            return $result;
        }

        $revenue =
            Payment::query()
                ->where(
                    'status',
                    'paid'
                )
                ->selectRaw('
                    currency,
                    COALESCE(
                        SUM(amount),
                        0
                    ) as total
                ')
                ->groupBy('currency')
                ->orderBy('currency')
                ->get()
                ->map(
                    fn (Payment $payment) => [
                        'currency' =>
                            $payment->currency,

                        'total' =>
                            (float) $payment->total,
                    ]
                )
                ->values()
                ->all();

        $result['paid_revenue'] =
            $revenue;

        $result['paid_orders'] =
            Payment::query()
                ->where(
                    'status',
                    'paid'
                )
                ->distinct(
                    'order_id'
                )
                ->count(
                    'order_id'
                );

        return $result;
    }
}