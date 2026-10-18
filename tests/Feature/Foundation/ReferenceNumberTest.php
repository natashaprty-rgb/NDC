<?php

namespace Tests\Feature\Foundation;

use App\Support\ReferenceNumber;
use App\Support\Settings;
use Tests\TestCase;

class ReferenceNumberTest extends TestCase
{
    public function test_generates_sequential_unique_numbers_per_type_and_year(): void
    {
        $this->assertSame('NDC-PRJ-2026-0001', ReferenceNumber::next('project', 2026));
        $this->assertSame('NDC-PRJ-2026-0002', ReferenceNumber::next('project', 2026));
        $this->assertSame('NDC-TSK-2026-0001', ReferenceNumber::next('task', 2026));
        $this->assertSame('NDC-PRJ-2027-0001', ReferenceNumber::next('project', 2027));
    }

    public function test_numbering_follows_settings(): void
    {
        Settings::set('numbering.org_prefix', 'XYZ');
        Settings::set('numbering.prefix.project', 'PJ');
        Settings::set('numbering.padding', 6);

        $this->assertSame('XYZ-PJ-2026-000001', ReferenceNumber::next('project', 2026));
    }

    public function test_many_rapid_requests_never_produce_duplicates(): void
    {
        $numbers = [];
        for ($i = 0; $i < 50; $i++) {
            $numbers[] = ReferenceNumber::next('document', 2026);
        }
        $this->assertCount(50, array_unique($numbers));
        $this->assertSame('NDC-DOC-2026-0050', end($numbers));
    }
}
