<?php

declare(strict_types=1);

namespace Tests\Feature\Akahu;

use App\Models\ImportJob;
use App\Services\Akahu\Authentication\SecretManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ReauthTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Config::set(sprintf('akahu.%s', SecretManager::APP_ID_TOKEN), 'my personal app');
        Config::set(sprintf('akahu.%s', SecretManager::USER_ACCESS_TOKEN), 'wrong :(');
    }

    public function testReauthenticate(): void
    {
        Storage::fake('import-jobs');
        $response  = $this->post('/new-import/akahu');
        $disk      = Storage::disk('import-jobs');
        $importJob = ImportJob::createFromJson($disk->get($disk->files()[0]));

        $response  = $this->get(sprintf('/configure-import/%s?parse=true', $importJob->identifier));
        $this->assertSame(session('akahu_reauthenticate'), 'true');
        $this->assertStringContainsString('Akahu credentials could not be authenticated', session('error'));

        $response->assertRedirectToRoute('authenticate-flow.index', ['flow' => 'akahu']);
    }
}
