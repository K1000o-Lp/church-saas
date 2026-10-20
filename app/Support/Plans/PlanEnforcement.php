<?php

namespace App\Support\Plans;

final class PlanEnforcement
{
    private static int $bypassDepth = 0;

    /**
     * Run an operation without plan limit enforcement, e.g. platform administration or seeds.
     */
    public static function without(callable $callback): mixed
    {
        self::$bypassDepth++;

        try {
            return $callback();
        } finally {
            self::$bypassDepth--;
        }
    }

    public static function isBypassed(): bool
    {
        return self::$bypassDepth > 0;
    }
}
