<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Generates sequential official reference numbers such as NDC/IN/2026/0007.
 * Counters are stored per prefix and year and incremented under a row lock so
 * concurrent requests never receive the same number.
 */
class ReferenceNumber
{
    public static function next(string $prefix, ?int $year = null, int $pad = 4): string
    {
        $year ??= (int) now()->format('Y');

        $value = DB::transaction(function () use ($prefix, $year) {
            $row = DB::table('sequences')
                ->where('name', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('sequences')->insert([
                    'name' => $prefix,
                    'year' => $year,
                    'last_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = $row->last_value + 1;
            DB::table('sequences')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => now()]);

            return $next;
        });

        return sprintf('NDC/%s/%d/%s', $prefix, $year, str_pad((string) $value, $pad, '0', STR_PAD_LEFT));
    }
}
