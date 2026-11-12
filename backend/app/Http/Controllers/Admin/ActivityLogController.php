<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $logs = ActivityLog::query()
            ->with([
                'user.roles',
            ])

            /*
            |--------------------------------------------------------------------------
            | Action Filter
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled('action'),
                function ($query) use ($request) {
                    $query->where(
                        'action',
                        $request
                            ->string('action')
                            ->toString()
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | User Filter
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled('user_id'),
                function ($query) use ($request) {
                    $query->where(
                        'user_id',
                        $request->integer(
                            'user_id'
                        )
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = $request
                        ->string('search')
                        ->toString();

                    $query->where(function ($searchQuery) use ($search) {
                        $searchQuery
                            ->where(
                                'action',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'description',
                                'like',
                                "%{$search}%"
                            );
                    });
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Date Range
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled('from'),
                function ($query) use ($request) {
                    $query->where(
                        'created_at',
                        '>=',
                        $request->date('from')
                            ->startOfDay()
                    );
                }
            )

            ->when(
                $request->filled('to'),
                function ($query) use ($request) {
                    $query->where(
                        'created_at',
                        '<=',
                        $request->date('to')
                            ->endOfDay()
                    );
                }
            )

            ->latest('id')

            ->paginate(
                min(
                    max(
                        $request->integer(
                            'per_page',
                            50
                        ),
                        1
                    ),
                    100
                )
            );

        return ActivityLogResource::collection(
            $logs
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Available Actions
    |--------------------------------------------------------------------------
    */

    public function actions(): JsonResponse
    {
        $actions = ActivityLog::query()
            ->whereNotNull('action')
            ->where('action', '!=', '')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        return response()->json([
            'data' => $actions,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Show
    |--------------------------------------------------------------------------
    */

    public function show(
        ActivityLog $activityLog
    ): ActivityLogResource {
        $activityLog->load([
            'user.roles',
        ]);

        return new ActivityLogResource(
            $activityLog
        );
    }
}
