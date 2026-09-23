<?php

/*
 * TestCase.php
 * Copyright (c) 2025 james@firefly-iii.org
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

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Runner\ErrorHandler;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function tearDown(): void
    {
        parent::tearDown();

        // Workaround für Laravel/PHPUnit Handler-Tracking-Issue auf PHP 8.5:
        // HandleExceptions::flushHandlersState() nutzt get_error_handler() und
        // get_exception_handler() (PHP 8.5), die in manchen Builds fehlerhaft null
        // zurückgeben. Ohne Cleanup wachsen die Handler-Stacks mit jedem Test,
        // PHPUnit markiert alle Tests als risky → Failure bei failOnRisky=true.
        // Siehe: https://github.com/laravel/framework/issues/49502
        $this->ensureHandlerStackClean();
    }

    private function ensureHandlerStackClean(): void
    {
        // Alle Exception-Handler entfernen (nach tearDown sollten 0 übrig sein)
        while (true) {
            $previous = set_exception_handler(static fn (Throwable $e) => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }

        // Alle Error-Handler entfernen, PHPUnits ErrorHandler merken
        $phpunitHandler = null;
        while (true) {
            $handler = set_error_handler(static fn () => false);
            restore_error_handler();
            if (null === $handler) {
                break;
            }
            restore_error_handler();
            if ($handler instanceof ErrorHandler) {
                $phpunitHandler = $handler;
            }
        }

        // PHPUnits Error-Handler wieder installieren
        if (null !== $phpunitHandler) {
            set_error_handler($phpunitHandler);
        }
    }
}
