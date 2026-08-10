<?php

/*
 * Configuration.php
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

namespace Tests\Unit\Services;

use App\Services\Shared\Configuration\Configuration;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Services\Shared\Configuration\Configuration
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @covers ::setFlow
     */
    public function testFlowChangeAppliesOnlyDefaultDuplicateDetectionMethod(): void
    {
        $defaultConfiguration = Configuration::make();
        $defaultConfiguration->setFlow('eb');

        $this->assertSame('cell', $defaultConfiguration->getDuplicateDetectionMethod());

        $explicitConfiguration = Configuration::fromArray(['duplicate_detection_method' => 'classic']);
        $explicitConfiguration->setFlow('eb');

        $this->assertSame('classic', $explicitConfiguration->getDuplicateDetectionMethod());
    }
}
