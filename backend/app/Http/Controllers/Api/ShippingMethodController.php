<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    /**
     * Get active shipping methods available to customers.
     */
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $shippingMethods = ShippingMethod::query()
            ->where(
                'is_active',
                true
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ShippingMethodResource::collection(
            $shippingMethods
        );
    }
}
