<?php

namespace App\Models\Concerns;

use App\Models\Church;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToChurch
{
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    protected static function bootBelongsToChurch(): void
    {
        static::addGlobalScope('church', function (Builder $builder): void {
            $churchId = app(CurrentChurch::class)->id();

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->qualifyColumn('church_id'), $churchId);
        });

        static::creating(function (Model $model): void {
            $churchId = app(CurrentChurch::class)->id();

            if ($churchId === null) {
                throw new AuthorizationException('A church context is required to create tenant-owned records.');
            }

            if ($model->getAttribute('church_id') !== null && (string) $model->getAttribute('church_id') !== (string) $churchId) {
                throw new AuthorizationException('The church_id cannot differ from the current church.');
            }

            $model->setAttribute('church_id', $churchId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('church_id')) {
                throw new AuthorizationException('The church_id of a tenant-owned record cannot be changed.');
            }

            $churchId = app(CurrentChurch::class)->id();

            if ($churchId === null || (string) $model->getOriginal('church_id') !== (string) $churchId) {
                throw new AuthorizationException('The current church must match the record church to update it.');
            }
        });

        static::deleting(function (Model $model): void {
            if ($model->isDirty('church_id')) {
                throw new AuthorizationException('The church_id of a tenant-owned record cannot be changed.');
            }

            $churchId = app(CurrentChurch::class)->id();

            if ($churchId === null || (string) $model->getOriginal('church_id') !== (string) $churchId) {
                throw new AuthorizationException('The current church must match the record church to delete it.');
            }
        });
    }
}
