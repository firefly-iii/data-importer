<?php

declare(strict_types=1);

namespace Tests\Feature\Akahu;


use Illuminate\Support\Facades\Storage;
use App\Models\ImportJob;
use Tests\TestCase;

final class HttpFlowTest extends TestCase
{
    public function testValidate(): void
    {
        $this
            ->get('/api/import-flows/validate/akahu')
            ->assertStatus(200)
            ->assertJson([
                'result' => 'OK',
            ]);
    }

    public function testGetNewImport(): void
    {
        $this
            ->get('/new-import/akahu')
            ->assertStatus(200);
    }

    public function testPostNewImport(): void
    {
        Storage::fake('import-jobs');

        $response = $this->post('/new-import/akahu');

        $disk = Storage::disk('import-jobs');
        $files = $disk->files();

        $this->assertSame(count($files), 1);
        $importJobFilename = $files[0];

        $importJobJson = $disk->get($importJobFilename);
        $this->assertNotNull($importJobJson);
        $this->assertNotSame($importJobJson, '');
        $importJob = ImportJob::createFromJson($importJobJson);

        $this->assertSame($importJob->getFlow(), 'akahu');
        $this->assertSame($importJob->getState(), 'contains_content');

        $response->assertRedirectToRoute('configure-import.index', [
            $importJob->identifier
        ]);
    }

    // TODO: Mock Akahu api responses
}
