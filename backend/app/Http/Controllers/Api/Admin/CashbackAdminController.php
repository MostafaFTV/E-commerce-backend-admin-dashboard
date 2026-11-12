<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashbackRule;
use App\Models\WalletCredit;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashbackAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rules = CashbackRule::query()
            ->with(['products:id', 'categories:id'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return response()->json($rules);
    }

    public function show(CashbackRule $rule): JsonResponse
    {
        return response()->json(['data' => $this->present($rule)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $rule = DB::transaction(function () use ($data): CashbackRule {
            $rule = CashbackRule::create($this->attributes($data));
            $this->syncScope($rule, $data);
            return $rule;
        });

        return response()->json(['data' => $this->present($rule)], 201);
    }

    public function update(Request $request, CashbackRule $rule): JsonResponse
    {
        $data = $this->validated($request, $rule);
        DB::transaction(function () use ($rule, $data): void {
            $rule->update($this->attributes($data));
            $this->syncScope($rule, $data);
        });

        return response()->json(['data' => $this->present($rule)]);
    }

    public function destroy(CashbackRule $rule): JsonResponse
    {
        if ($rule->orderItems()->exists()) {
            throw ValidationException::withMessages([
                'rule' => 'This rule has order history. Disable it instead of deleting it.',
            ]);
        }
        DB::transaction(fn () => $rule->delete());
        return response()->json(['message' => 'Cashback rule deleted.']);
    }

    public function report(Request $request): JsonResponse
    {
        $credits = WalletCredit::query()
            ->selectRaw('status, COUNT(*) AS credit_count, COALESCE(SUM(original_amount),0) AS issued_amount, COALESCE(SUM(remaining_amount),0) AS remaining_amount')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        $transactions = WalletTransaction::query()
            ->select(['id', 'wallet_id', 'order_id', 'type', 'amount', 'created_at'])
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'data' => [
                'currency' => 'IRT',
                'credits_by_status' => $credits,
                'transactions' => $transactions,
            ],
        ]);
    }

    private function validated(Request $request, ?CashbackRule $rule): array
    {
        $data = $request->validate([
            'name' => [$rule ? 'sometimes' : 'required', 'string', 'max:255'],
            'scope' => [$rule ? 'sometimes' : 'required', Rule::in(['all', 'products', 'categories'])],
            'percentage' => [$rule ? 'sometimes' : 'required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'expires_after_days' => [$rule ? 'sometimes' : 'required', 'integer', 'between:1,3650'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ]);

        $scope = $data['scope'] ?? $rule?->scope;
        $start = array_key_exists('starts_at', $data) ? $data['starts_at'] : $rule?->starts_at;
        $end = array_key_exists('ends_at', $data) ? $data['ends_at'] : $rule?->ends_at;
        if ($start !== null && $end !== null && \Illuminate\Support\Carbon::parse($end)->lt(\Illuminate\Support\Carbon::parse($start))) {
            throw ValidationException::withMessages(['ends_at' => 'End date must not precede start date.']);
        }

        if ($scope === 'products') {
            $ids = $data['product_ids'] ?? ($rule?->scope === 'products' ? $rule->products()->pluck('products.id')->all() : []);
            if ($ids === []) {
                throw ValidationException::withMessages(['product_ids' => 'Select at least one product.']);
            }
        }
        if ($scope === 'categories') {
            $ids = $data['category_ids'] ?? ($rule?->scope === 'categories' ? $rule->categories()->pluck('categories.id')->all() : []);
            if ($ids === []) {
                throw ValidationException::withMessages(['category_ids' => 'Select at least one category.']);
            }
        }
        if ($scope !== 'products' && !empty($data['product_ids'])) {
            throw ValidationException::withMessages(['product_ids' => 'Product selection requires products scope.']);
        }
        if ($scope !== 'categories' && !empty($data['category_ids'])) {
            throw ValidationException::withMessages(['category_ids' => 'Category selection requires categories scope.']);
        }

        return $data;
    }

    private function attributes(array $data): array
    {
        unset($data['product_ids'], $data['category_ids']);
        return $data;
    }

    private function syncScope(CashbackRule $rule, array $data): void
    {
        if ($rule->scope === 'products') {
            if (array_key_exists('product_ids', $data)) {
                $rule->products()->sync($data['product_ids']);
            }
            $rule->categories()->detach();
        } elseif ($rule->scope === 'categories') {
            if (array_key_exists('category_ids', $data)) {
                $rule->categories()->sync($data['category_ids']);
            }
            $rule->products()->detach();
        } else {
            $rule->products()->detach();
            $rule->categories()->detach();
        }
    }

    private function present(CashbackRule $rule): array
    {
        $rule->load(['products:id', 'categories:id']);
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'scope' => $rule->scope,
            'percentage' => $rule->percentage,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'starts_at' => $rule->starts_at,
            'ends_at' => $rule->ends_at,
            'expires_after_days' => $rule->expires_after_days,
            'product_ids' => $rule->products->pluck('id')->values(),
            'category_ids' => $rule->categories->pluck('id')->values(),
            'created_at' => $rule->created_at,
            'updated_at' => $rule->updated_at,
        ];
    }
}