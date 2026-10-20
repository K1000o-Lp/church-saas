<?php

namespace App\Support\Tenancy;

use App\Models\Church;

class CurrentChurch
{
    private ?Church $church = null;

    public function set(Church $church): void
    {
        $this->church = $church;
    }

    public function church(): ?Church
    {
        return $this->church;
    }

    public function id(): int|string|null
    {
        return $this->church?->getKey();
    }

    public function clear(): void
    {
        $this->church = null;
    }
}
