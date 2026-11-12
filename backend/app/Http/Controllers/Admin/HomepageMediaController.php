<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HomepageMediaController extends Controller
{
    public function store(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'folder' => [
                'sometimes',
                'nullable',
                'string',
                'in:services,story,kitchen-family,general',
            ],
        ]);

        $folder =
            $validated['folder']
            ?? 'general';

        $file =
            $validated['image'];

        $extension =
            strtolower(
                $file->getClientOriginalExtension()
            );

        $filename =
            now()->format('YmdHis')
            . '-'
            . Str::lower(
                Str::random(12)
            )
            . '.'
            . $extension;

        $storedPath =
            $file->storeAs(
                "homepage/{$folder}",
                $filename,
                'public'
            );

        $publicPath =
            '/storage/' . $storedPath;

        return response()->json([
            'data' => [
                'path' =>
                    $publicPath,

                'url' =>
                    url($publicPath),
            ],
        ], 201);
    }
}