<?php

namespace Tests\Feature\Core;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every interface string passed to __() or trans_choice() must have a
 * Somali translation, with the same :placeholders and plural segments.
 */
class TranslationCoverageTest extends TestCase
{
    protected bool $seed = false;

    public function test_every_interface_string_has_a_somali_translation(): void
    {
        $somali = [];
        foreach (glob(lang_path('modules/*/so.json')) as $file) {
            $decoded = json_decode(File::get($file), true);
            $this->assertIsArray($decoded, "$file is not valid JSON");
            $somali += $decoded;
        }

        $missing = [];
        $placeholderErrors = [];
        foreach ($this->sourceFiles() as $file) {
            preg_match_all("/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/s", File::get($file), $matches);
            foreach ($matches[1] as $key) {
                $key = str_replace("\\'", "'", $key);
                // PHP language-file keys (validation.required, ...) live in lang/so/*.php.
                if (preg_match('/^[a-z_]+\.[a-z_.]+$/', $key)) {
                    continue;
                }
                if (! isset($somali[$key]) || $somali[$key] === '') {
                    $missing[$key] = str_replace(base_path().'/', '', $file);

                    continue;
                }
                if ($this->placeholders($key) !== $this->placeholders($somali[$key])
                    || substr_count($key, '|') !== substr_count($somali[$key], '|')) {
                    $placeholderErrors[] = $key;
                }
            }
        }

        $this->assertSame([], $missing, 'Strings without a Somali translation');
        $this->assertSame([], $placeholderErrors, 'Somali translations with different placeholders');
    }

    public function test_somali_language_files_mirror_english(): void
    {
        foreach (['auth', 'pagination', 'passwords', 'validation'] as $group) {
            $en = require lang_path("en/{$group}.php");
            $so = require lang_path("so/{$group}.php");
            $required = array_filter(Arr::dot($en), fn ($v) => $v !== []);
            $this->assertSame([], array_diff_key($required, Arr::dot($so)), "lang/so/{$group}.php is missing keys");
        }
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        return collect(File::allFiles(app_path()))
            ->merge(File::allFiles(resource_path('views')))
            ->map(fn ($f) => $f->getPathname())
            ->filter(fn ($p) => str_ends_with($p, '.php'))
            ->values()->all();
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/', $text, $m);
        $found = array_values(array_unique($m[0]));
        sort($found);

        return $found;
    }
}
