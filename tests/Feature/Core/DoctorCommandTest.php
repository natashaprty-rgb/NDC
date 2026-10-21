<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_missing_administrator(): void
    {
        $this->artisan('ndc:doctor')
            ->expectsOutputToContain('Super Administrator account')
            ->assertFailed();
    }

    public function test_passes_on_a_complete_installation(): void
    {
        $this->superAdmin();

        $this->artisan('ndc:doctor')
            ->expectsOutputToContain('Everything needed to run the application is in place.')
            ->assertSuccessful();
    }
}
