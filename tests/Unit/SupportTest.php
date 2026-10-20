<?php

namespace Tests\Unit;

use App\Support\CsvExport;
use App\Support\FileStore;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_csv_cells_neutralise_formulas_but_keep_numbers(): void
    {
        $this->assertSame("'=SUM(A1:A9)", CsvExport::cell('=SUM(A1:A9)'));
        $this->assertSame("'@cmd", CsvExport::cell('@cmd'));
        $this->assertSame("'+44 1 2", CsvExport::cell('+44 1 2'));
        $this->assertSame('-12.50', CsvExport::cell('-12.50'));
        $this->assertSame('Yes', CsvExport::cell(true));
        $this->assertSame('', CsvExport::cell(null));
    }

    public function test_uploaded_file_names_are_cleaned(): void
    {
        $this->assertSame('report_2026.pdf', FileStore::cleanName('report<>2026.pdf'));
        $this->assertSame('Warbixin sannadeed.docx', FileStore::cleanName('Warbixin sannadeed.docx'));
        $this->assertSame('file', FileStore::cleanName('   '));
        $this->assertSame(200, mb_strlen(FileStore::cleanName(str_repeat('a', 300))));
    }
}
