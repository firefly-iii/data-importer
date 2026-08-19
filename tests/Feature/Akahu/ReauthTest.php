<?php


/*
 * ReauthTest.php
 * Copyright (c) 2026 james@firefly-iii.org
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

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
