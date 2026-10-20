<?php

namespace Tests\Unit;

use App\Models\Church;
use App\Support\Tenancy\CurrentChurch;
use Tests\TestCase;

class CurrentChurchTest extends TestCase
{
    public function test_it_holds_and_clears_the_current_church(): void
    {
        $context = new CurrentChurch;
        $church = new Church;
        $church->setAttribute('id', 42);

        $context->set($church);

        $this->assertSame($church, $context->church());
        $this->assertSame(42, $context->id());

        $context->clear();

        $this->assertNull($context->church());
        $this->assertNull($context->id());
    }
}
