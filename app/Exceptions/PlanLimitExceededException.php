<?php

namespace App\Exceptions;

use App\Support\Plans\PlanResources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlanLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $resource,
        public readonly int $limit,
        public readonly int $used,
    ) {
        parent::__construct(sprintf(
            'El plan actual permite %d %s y ya se usan %d. Mejora el plan o solicita una excepción.',
            $limit,
            mb_strtolower(PlanResources::label($resource)),
            $used,
        ));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'resource' => $this->resource,
                'limit' => $this->limit,
                'used' => $this->used,
            ], 422);
        }

        return back()->withErrors(['plan' => $this->getMessage()]);
    }
}
