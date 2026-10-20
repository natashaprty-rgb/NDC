<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Version metadata only; tests that download the file must also put a file
 * at the generated path on the "local" disk.
 *
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version' => 1,
            'original_name' => fake()->slug(3).'.pdf',
            'disk' => 'local',
            'path' => 'documents/'.now()->format('Y/m').'/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'checksum' => hash('sha256', Str::random(16)),
        ];
    }
}
