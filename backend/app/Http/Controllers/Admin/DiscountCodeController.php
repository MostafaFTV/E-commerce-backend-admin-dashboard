<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerDiscountCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class DiscountCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerDiscountCode::query()
            ->with([
                'customer:id,first_name,last_name,phone',
                'category:id,name,slug',
            ])
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request): void {
                    $search = trim($request->string('search')->toString());

                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('code', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                                $customerQuery
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
                }
            )
            ->when(
                in_array($request->query('status'), ['active', 'expired', 'inactive'], true),
                function (Builder $query) use ($request): void {
                    match ($request->query('status')) {
                        'active' => $query
                            ->where('is_active', true)
                            ->where('expires_at', '>', now()),
                        'expired' => $query
                            ->where('is_active', true)
                            ->where('expires_at', '<=', now()),
                        'inactive' => $query->where('is_active', false),
                    };
                }
            )
            ->when(
                in_array($request->query('scope'), ['all', 'category'], true),
                fn (Builder $query) => $query->where('scope', $request->query('scope'))
            )
            ->latest('id');

        $codes = $query->paginate(
            min(max($request->integer('per_page', 20), 1), 100)
        );

        return response()->json([
            'data' => $codes->getCollection()
                ->map(fn (CustomerDiscountCode $code) => $this->adminData($code))
                ->values(),
            'meta' => [
                'current_page' => $codes->currentPage(),
                'last_page' => $codes->lastPage(),
                'per_page' => $codes->perPage(),
                'total' => $codes->total(),
            ],
        ]);
    }

    public function options(Request $request)
    {
        $customerQuery = User::query()
            ->where(function (Builder $query) use ($request): void {
                $query->whereHas(
                    'roles',
                    fn (Builder $roles) => $roles->where('slug', 'customer')
                );

                if ($request->filled('search')) {
                    $search = trim($request->string('search')->toString());
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                }

                $customerId = $request->integer('customer_id');

                if ($customerId > 0) {
                    $query->orWhere(function (Builder $query) use ($customerId): void {
                        $query
                            ->whereKey($customerId)
                            ->whereHas(
                                'roles',
                                fn (Builder $roles) => $roles->where('slug', 'customer')
                            );
                    });
                }
            })
            ->when(
                $request->integer('customer_id') > 0,
                fn (Builder $query) => $query->orderByRaw(
                    'CASE WHEN id = ? THEN 0 ELSE 1 END',
                    [$request->integer('customer_id')]
                )
            )
            ->orderByDesc('id')
            ->limit(50);

        $customers = $customerQuery->get(['id', 'first_name', 'last_name', 'phone'])
            ->map(fn (User $customer) => [
                'id' => $customer->id,
                'name' => trim($customer->first_name . ' ' . $customer->last_name),
                'phone' => $customer->phone,
            ])
            ->values();

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'name', 'slug']);

        $categoryOptions = $this->flattenCategories($categories);

        return response()->json([
            'data' => [
                'customers' => $customers,
                'categories' => $categoryOptions,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);
        $code = CustomerDiscountCode::create($payload);
        $code->load(['customer:id,first_name,last_name,phone', 'category:id,name,slug']);

        return response()->json([
            'data' => $this->adminData($code),
        ], 201);
    }

    public function update(Request $request, CustomerDiscountCode $discountCode)
    {
        $discountCode->update(
            $this->validatedPayload($request, $discountCode)
        );
        $discountCode->load(['customer:id,first_name,last_name,phone', 'category:id,name,slug']);

        return response()->json([
            'data' => $this->adminData($discountCode),
        ]);
    }

    public function deactivate(CustomerDiscountCode $discountCode)
    {
        $discountCode->update(['is_active' => false]);

        return response()->json([
            'data' => $this->adminData(
                $discountCode->load([
                    'customer:id,first_name,last_name,phone',
                    'category:id,name,slug',
                ])
            ),
        ]);
    }

    private function validatedPayload(
        Request $request,
        ?CustomerDiscountCode $existing = null
    ): array {
        $current = $existing ? [
            'code' => $existing->code,
            'percentage' => $existing->percentage,
            'customer_id' => $existing->customer_id,
            'scope' => $existing->scope,
            'category_id' => $existing->category_id,
            'expires_at' => $existing->expires_at->toDateTimeString(),
            'is_active' => $existing->is_active,
            'description' => $existing->description,
        ] : [];

        $input = array_merge(
            ['category_id' => null, 'description' => null],
            $current,
            $request->all()
        );
        $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));

        $payload = validator($input, [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('customer_discount_codes', 'code')
                    ->ignore($existing?->id),
            ],
            'percentage' => ['required', 'integer', 'between:1,100'],
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'scope' => ['required', Rule::in(['all', 'category'])],
            'category_id' => [
                Rule::requiredIf(($input['scope'] ?? null) === 'category'),
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'expires_at' => ['required', 'date'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $customerIsValid = User::query()
            ->whereKey($payload['customer_id'])
            ->whereHas('roles', fn (Builder $query) => $query->where('slug', 'customer'))
            ->exists();

        if (! $customerIsValid) {
            throw ValidationException::withMessages([
                'customer_id' => ['کد تخفیف باید به یک مشتری اختصاص داده شود.'],
            ]);
        }

        if ($payload['scope'] === 'all') {
            if ($payload['category_id'] !== null) {
                throw ValidationException::withMessages([
                    'category_id' => ['برای محدوده همه محصولات دسته‌بندی انتخاب نکنید.'],
                ]);
            }
        } elseif (! Category::query()
            ->whereKey($payload['category_id'])
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'category_id' => ['دسته‌بندی انتخاب‌شده فعال نیست.'],
            ]);
        }

        $expiresAt = Carbon::parse($payload['expires_at']);
        $earliestExpiry = $existing?->created_at ?? now();

        if ($expiresAt->lessThanOrEqualTo($earliestExpiry)) {
            throw ValidationException::withMessages([
                'expires_at' => ['تاریخ انقضا باید پس از ایجاد کد باشد.'],
            ]);
        }

        $payload['expires_at'] = $expiresAt;

        return $payload;
    }

    private function adminData(CustomerDiscountCode $code): array
    {
        $status = $code->status();

        return [
            'id' => $code->id,
            'code' => $code->code,
            'percentage' => $code->percentage,
            'customer_id' => $code->customer_id,
            'customer' => $code->customer ? [
                'id' => $code->customer->id,
                'name' => trim($code->customer->first_name . ' ' . $code->customer->last_name),
                'phone' => $code->customer->phone,
            ] : null,
            'scope' => $code->scope,
            'category_id' => $code->category_id,
            'category' => $code->category ? [
                'id' => $code->category->id,
                'name' => $code->category->name,
                'slug' => $code->category->slug,
            ] : null,
            'expires_at' => $code->expires_at->toIso8601String(),
            'is_active' => $code->is_active,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'description' => $code->description,
            'created_at' => $code->created_at?->toIso8601String(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match (app()->getLocale()) {
            'fa' => match ($status) {
                'active' => 'فعال',
                'expired' => 'منقضی‌شده',
                default => 'غیرفعال',
            },
            default => ucfirst($status),
        };
    }

    private function flattenCategories($categories): array
    {
        $children = $categories->groupBy('parent_id');
        $result = [];
        $visited = [];

        $visit = function ($parentId, int $depth) use (
            &$visit,
            &$result,
            &$visited,
            $children
        ): void {
            foreach ($children->get($parentId, collect()) as $category) {
                if (isset($visited[$category->id])) {
                    continue;
                }

                $visited[$category->id] = true;
                $result[] = [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'depth' => $depth,
                ];
                $visit($category->id, $depth + 1);
            }
        };

        $visit(null, 0);

        return $result;
    }
}
