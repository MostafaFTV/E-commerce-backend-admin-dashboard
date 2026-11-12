<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Write an activity log entry.
     */
    public function log(
        string $action,
        ?string $description = null,
        array $properties = [],
        ?Model $subject = null,
        ?Request $request = null,
        ?User $actor = null
    ): ActivityLog {
        $request ??= request();

        $properties = array_merge(
            $properties,
            $this->subjectProperties($subject)
        );

        $properties = $this->sanitizeValue($properties);

        return ActivityLog::create([
            'user_id' => $actor?->id
                ?? $request->user()?->id,

            'action' => $action,

            'description' => $description,

            'properties' => $properties !== []
                ? $properties
                : null,

            'ip_address' => $request->ip(),

            'user_agent' => $request->userAgent(),

            'created_at' => now(),
        ]);
    }

    /**
     * Log changed model attributes.
     */
    public function logModelChange(
        string $action,
        Model $model,
        array $properties = [],
        ?string $description = null,
        ?Request $request = null,
        ?User $actor = null
    ): ActivityLog {
        $changes = $model->getChanges();

        return $this->log(
            action: $action,
            description: $description,
            properties: array_merge(
                [
                    'changes' => $changes,
                ],
                $properties
            ),
            subject: $model,
            request: $request,
            actor: $actor
        );
    }

    /**
     * Log model creation.
     */
    public function logCreated(
        Model $model,
        string $action,
        ?string $description = null,
        array $properties = [],
        ?Request $request = null,
        ?User $actor = null
    ): ActivityLog {
        $attributes = $this->sanitizeValue(
            $model->getAttributes()
        );

        return $this->log(
            action: $action,
            description: $description,
            properties: array_merge(
                [
                    'attributes' => $attributes,
                ],
                $properties
            ),
            subject: $model,
            request: $request,
            actor: $actor
        );
    }

    /**
     * Log model deletion.
     */
    public function logDeleted(
        Model $model,
        string $action,
        ?string $description = null,
        array $properties = [],
        ?Request $request = null,
        ?User $actor = null
    ): ActivityLog {
        $attributes = $this->sanitizeValue(
            $model->getAttributes()
        );

        return $this->log(
            action: $action,
            description: $description,
            properties: array_merge(
                [
                    'attributes' => $attributes,
                ],
                $properties
            ),
            subject: $model,
            request: $request,
            actor: $actor
        );
    }

    /**
     * Build safe subject metadata.
     */
    protected function subjectProperties(
        ?Model $subject
    ): array {
        if (! $subject) {
            return [];
        }

        return [
            'subject' => [
                'type' => $subject::class,
                'id' => $subject->getKey(),
            ],
        ];
    }

    /**
     * Recursively remove sensitive fields from arbitrary log data.
     */
    protected function sanitizeValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];

        foreach ($value as $key => $item) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            $sanitized[$key] = $this->sanitizeValue($item);
        }

        return $sanitized;
    }

    /**
     * Determine whether a key represents sensitive data.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                trim($key)
            )
        );

        $exactSensitiveFields = [
            'password',
            'password_confirmation',
            'remember_token',
            'token',
            'access_token',
            'refresh_token',
            'secret',
            'client_secret',
            'authorization',
            'api_key',
            'apikey',
            'private_key',
            'privatekey',
            'webhook_secret',
            'signature',
            'card_token',
            'authorization_code',
        ];

        if (in_array($normalizedKey, $exactSensitiveFields, true)) {
            return true;
        }

        $sensitiveFragments = [
            'password',
            'token',
            'secret',
            'api_key',
            'apikey',
            'private_key',
            'privatekey',
            'authorization',
            'credential',
        ];

        foreach ($sensitiveFragments as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }
}