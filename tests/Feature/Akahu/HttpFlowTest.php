<?php

/*
 * HttpFlowTest.php
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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class HttpFlowTest extends TestCase
{
    public function testValidate(): void
    {
        $this->get('/api/import-flows/validate/akahu')->assertOk()->assertJson(['result' => 'OK']);
    }

    public function testGetNewImport(): void
    {
        $this->get('/new-import/akahu')->assertOk();
    }

    public function testPostNewImport(): void
    {
        Storage::fake('import-jobs');

        $response          = $this->post('/new-import/akahu');

        $disk              = Storage::disk('import-jobs');
        $files             = $disk->files();

        $this->assertSame(count($files), 1);
        $importJobFilename = $files[0];

        $importJobJson     = $disk->get($importJobFilename);
        $this->assertNotNull($importJobJson);
        $this->assertNotSame($importJobJson, '');
        $importJob         = ImportJob::createFromJson($importJobJson);

        $this->assertSame($importJob->getFlow(), 'akahu');
        $this->assertSame($importJob->getState(), 'contains_content');

        $response->assertRedirectToRoute('configure-import.index', [$importJob->identifier]);
    }

    // TODO: Mock Akahu api responses
}
