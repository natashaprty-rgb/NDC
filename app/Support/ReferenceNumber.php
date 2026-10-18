<?php

namespace App\Support;

use App\Models\NumberSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Generates unique, sequential reference numbers such as NDC-PRJ-2026-0001.
 *
 * The sequence row is locked with SELECT ... FOR UPDATE inside a transaction,
 * so two concurrent requests can never receive the same number. Callers
 * should invoke this inside the same transaction that inserts the record,
 * so a rolled-back insert also rolls back the counter. The unique index on
 * each reference column remains the final safeguard.
 */
final class ReferenceNumber
{
    public static function next(string $type, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = (string) Settings::get('numbering.prefix.'.$type, strtoupper($type));
        $org = (string) Settings::get('numbering.org_prefix', 'NDC');
        $padding = max(3, (int) Settings::get('numbering.padding', 4));

        $number = DB::transaction(function () use ($type, $year) {
            $sequence = NumberSequence::query()
                ->where('key', $type)
                ->where('period', (string) $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                try {
                    // Savepoint so a duplicate-key race does not abort an outer transaction.
                    DB::transaction(fn () => NumberSequence::query()->create(['key' => $type, 'period' => (string) $year, 'last_number' => 0]));
                } catch (QueryException) {
                    // Another request created it first; fall through and lock it.
                }
                $sequence = NumberSequence::query()
                    ->where('key', $type)
                    ->where('period', (string) $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->last_number++;
            $sequence->save();

            return $sequence->last_number;
        });

        return implode('-', array_filter([$org, $prefix, (string) $year, str_pad((string) $number, $padding, '0', STR_PAD_LEFT)], fn ($p) => $p !== ''));
    }
}
