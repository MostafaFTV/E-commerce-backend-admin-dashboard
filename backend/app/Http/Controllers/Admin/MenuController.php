<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Menu::query()
            ->withCount('items')
            ->latest('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($menuQuery) use ($search) {
                $menuQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        return JsonResource::collection(
            $query->paginate(20)
        );
    }

    public function show(Menu $menu): JsonResource
    {
        $menu->load([
            'items' => function ($query) {
                $query
                    ->with([
                        'category',
                        'product',
                        'collection',
                        'page',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('id');
            },
        ]);

        return JsonResource::make(
            $this->transformMenu($menu)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],
            'slug' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:menus,slug',
            ],
            'location' => [
                'required',
                'string',
                'max:100',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $menu = DB::transaction(
            fn () => Menu::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'location' => $validated['location'],
                'is_active' => $validated['is_active'] ?? true,
            ])
        );

        $menu->loadCount('items');

        return response()->json([
            'message' => 'Menu created successfully.',
            'data' => $this->transformMenu($menu),
        ], 201);
    }

    public function update(
        Request $request,
        Menu $menu
    ): JsonResource {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('menus', 'slug')
                    ->ignore($menu->id),
            ],
            'location' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        DB::transaction(
            fn () => $menu->update($validated)
        );

        $menu->loadCount('items');

        return JsonResource::make([
            'message' => 'Menu updated successfully.',
            'data' => $this->transformMenu(
                $menu->fresh()->loadCount('items')
            ),
        ]);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        if ($menu->items()->exists()) {
            return response()->json([
                'message' => 'This menu cannot be deleted while it contains menu items.',
            ], 422);
        }

        DB::transaction(
            fn () => $menu->delete()
        );

        return response()->json([
            'message' => 'Menu deleted successfully.',
        ]);
    }

    protected function transformMenu(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'location' => $menu->location,
            'is_active' => (bool) $menu->is_active,
            'items_count' => $menu->items_count ?? 0,
            'items' => $menu->relationLoaded('items')
                ? $menu->items->map(
                    fn ($item) => [
                        'id' => $item->id,
                        'parent_id' => $item->parent_id,
                        'title' => $item->title,
                        'type' => $item->type?->value,
                        'url' => $item->url,
                        'category_id' => $item->category_id,
                        'product_id' => $item->product_id,
                        'collection_id' => $item->collection_id,
                        'page_id' => $item->page_id,
                        'sort_order' => $item->sort_order,
                        'is_active' => (bool) $item->is_active,
                    ]
                )->values()->all()
                : [],
            'created_at' => $menu->created_at,
            'updated_at' => $menu->updated_at,
        ];
    }
}
