<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InspirationVideoController extends Controller
{
    private const DIRECTORY = 'homepage/story';

    private const FILENAME = 'Inspiration.mp4';

    public function show(): JsonResponse
    {
        $disk = Storage::disk('public');

        $path = self::DIRECTORY . '/' . self::FILENAME;

        if (! $disk->exists($path)) {
            return response()->json([
                'message' => 'Inspiration video not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'path' => '/storage/' . $path,
                'url' => url('/storage/' . $path)
                    . '?v=' . $disk->lastModified($path),
                'size' => $disk->size($path),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,application/mp4',
                'extensions:mp4',
                'max:51200',
            ],
        ]);

        $disk = Storage::disk('public');

        $directory = self::DIRECTORY;

        $target = $directory . '/' . self::FILENAME;

        $disk->makeDirectory($directory);

        $lockPath = $disk->path(
            $directory . '/.inspiration-upload.lock'
        );

        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new RuntimeException(
                'Unable to acquire video upload lock.'
            );
        }

        $temporary = null;

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException(
                    'Unable to lock video replacement.'
                );
            }

            if (! $disk->exists($target)) {
                throw new RuntimeException(
                    'The original Inspiration video is missing.'
                );
            }

            $temporaryName = '.inspiration-'
                . Str::uuid()->toString()
                . '.mp4';

            $temporary = $validated['video']->storeAs(
                $directory,
                $temporaryName,
                'public'
            );

            if (
                ! is_string($temporary)
                || ! $disk->exists($temporary)
                || $disk->size($temporary) <= 0
            ) {
                throw new RuntimeException(
                    'Video staging failed.'
                );
            }

            $backupDirectory = $directory . '/backups';

            $disk->makeDirectory($backupDirectory);

            $backup = $backupDirectory
                . '/Inspiration-'
                . now()->format('YmdHis')
                . '-'
                . Str::lower(Str::random(12))
                . '.mp4';

            if (! $disk->copy($target, $backup)) {
                throw new RuntimeException(
                    'Video backup failed.'
                );
            }

            /*
             * Both paths are on the same public disk.
             * On a local filesystem, rename is atomic.
             */
            if (! rename(
                $disk->path($temporary),
                $disk->path($target)
            )) {
                throw new RuntimeException(
                    'Video replacement failed.'
                );
            }

            $temporary = null;

            clearstatcache(true, $disk->path($target));

            return response()->json([
                'data' => [
                    'path' => '/storage/' . $target,
                    'url' => url('/storage/' . $target)
                        . '?v=' . $disk->lastModified($target),
                    'size' => $disk->size($target),
                ],
                'message' => 'Inspiration video updated.',
            ]);
        } finally {
            if (
                is_string($temporary)
                && $disk->exists($temporary)
            ) {
                $disk->delete($temporary);
            }

            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}