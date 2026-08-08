<?php

/*
 * AutoImportResultTest.php
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

namespace Tests\Unit\Console;

use App\Console\AutoImportResult;
use App\Enums\ExitCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Console\AutoImportResult
 */
final class AutoImportResultTest extends TestCase
{
    public static function exitCodeProvider(): iterable
    {
        yield 'mixed successful and quiet imports' => [['one.json' => 73, 'two.json' => 0], ExitCode::SUCCESS];

        yield 'only quiet imports' => [['one.json' => 73, 'two.json' => 73], ExitCode::NOTHING_WAS_IMPORTED];

        yield 'one distinct failure' => [['one.json' => 74, 'two.json' => 74], ExitCode::AGREEMENT_EXPIRED];

        yield 'one failure and one quiet import' => [['one.json' => 74, 'two.json' => 73], ExitCode::AGREEMENT_EXPIRED];

        yield 'distinct failures' => [['one.json' => 74, 'two.json' => 75], ExitCode::GENERAL_ERROR];
    }

    /**
     * @param array<string, int> $result
     */
    #[DataProvider('exitCodeProvider')]
    public function testExitCode(array $result, ExitCode $expected): void
    {
        $this->assertSame($expected, new AutoImportResult($result)->getExitCode());
    }

    public function testFailuresExcludeSuccessfulAndQuietImports(): void
    {
        $result = new AutoImportResult([
            'imported.json' => ExitCode::SUCCESS->value,
            'quiet.json'    => ExitCode::NOTHING_WAS_IMPORTED->value,
            'expired.json'  => ExitCode::AGREEMENT_EXPIRED->value
        ]);

        $this->assertSame(['expired.json' => ExitCode::AGREEMENT_EXPIRED->value], $result->getFailures());
    }
}
