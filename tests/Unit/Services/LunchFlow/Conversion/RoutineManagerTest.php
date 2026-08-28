<?php

/*
 * RoutineManagerTest.php
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

namespace Tests\Unit\Services\LunchFlow\Conversion;

use App\Models\ImportJob;
use App\Services\LunchFlow\Conversion\RoutineManager;
use App\Services\Shared\Configuration\Configuration;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Services\LunchFlow\Conversion\RoutineManager
 */
final class RoutineManagerTest extends TestCase
{
    private function makeManager(): RoutineManager
    {
        $job = ImportJob::createNew();
        $job->setConfiguration(Configuration::make());

        return new RoutineManager($job);
    }

    private function setDownloaded(RoutineManager $manager, array $downloaded): void
    {
        $property = new ReflectionProperty(RoutineManager::class, 'downloaded');
        $property->setValue($manager, $downloaded);
    }

    private function breakOnDownload(RoutineManager $manager): bool
    {
        $method = new ReflectionMethod(RoutineManager::class, 'breakOnDownload');

        return $method->invoke($manager);
    }

    /**
     * @covers ::breakOnDownload
     */
    public function testEmptyDownloadWithoutDownloadErrorDoesNotBreak(): void
    {
        Storage::fake('import-jobs');
        $manager = $this->makeManager();
        $this->setDownloaded($manager, ['test-account' => []]);

        self::assertFalse($this->breakOnDownload($manager));

        $errors = $manager->getImportJob()->conversionStatus->errors;
        self::assertSame([], $errors);
    }

    /**
     * @covers ::breakOnDownload
     */
    public function testEmptyDownloadWithDownloadErrorAddsBreakError(): void
    {
        Storage::fake('import-jobs');
        $manager = $this->makeManager();
        $manager->getImportJob()->conversionStatus->addError(0, '[a109]: Could not download from GoCardless: test');
        $this->setDownloaded($manager, ['test-account' => []]);

        self::assertTrue($this->breakOnDownload($manager));

        $errors = $manager->getImportJob()->conversionStatus->errors;
        self::assertNotEmpty($errors[0]);
        self::assertStringContainsString('[a111]', implode(' ', $errors[0]));
    }
}
