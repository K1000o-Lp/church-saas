<?php

namespace App\Support\Audit;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

final class AuthAudit
{
    public const LOGIN = 'auth.login';

    public const LOGOUT = 'auth.logout';

    public const PASSWORD_RESET = 'auth.password_reset';

    /**
     * Authentication auditing must never interrupt the flow it observes.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(string $event, ?User $user, array $metadata = []): void
    {
        $runningInConsole = app()->runningInConsole();
        $metadata = self::sanitize($metadata);

        try {
            AuthEvent::create([
                'user_id' => $user?->getKey(),
                'event' => $event,
                'ip_address' => $runningInConsole ? null : request()->ip(),
                'user_agent' => $runningInConsole
                    ? null
                    : Str::limit((string) request()->userAgent(), 500),
                'metadata' => $metadata === [] ? (object) [] : $metadata,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function sanitize(array $metadata): array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'remember_token', 'secret', 'notes', 'email'];

        return collect($metadata)
            ->reject(fn (mixed $value, string $key): bool => in_array(Str::lower($key), $blocked, true))
            ->map(function (mixed $value): mixed {
                if (is_string($value)) {
                    return Str::limit($value, 500);
                }

                if (is_array($value)) {
                    return collect($value)
                        ->take(50)
                        ->map(fn (mixed $item): mixed => is_string($item) ? Str::limit($item, 200) : $item)
                        ->all();
                }

                return $value;
            })
            ->all();
    }
}
