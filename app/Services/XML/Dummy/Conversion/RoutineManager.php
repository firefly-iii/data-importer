<?php
/*
 * RoutineManager.php
 * Copyright (c) 2022 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
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

namespace App\Services\XML\Dummy\Conversion;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Conversion\Routine\ColumnValueConverter;
use App\Services\CSV\Conversion\Routine\CSVFileProcessor;
use App\Services\CSV\Conversion\Routine\LineProcessor;
use App\Services\CSV\Conversion\Routine\PseudoTransactionProcessor;
use App\Services\CSV\File\FileReader;
use App\Services\Shared\Authentication\IsRunningCli;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\GeneratesIdentifier;
use App\Services\Shared\Conversion\RoutineManagerInterface;
use App\Services\Shared\Conversion\RoutineStatusManager;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RoutineManager implements RoutineManagerInterface
{
    use IsRunningCli, GeneratesIdentifier;

    private array                      $allErrors;
    private array                      $allMessages;
    private array                      $allWarnings;
    private ColumnValueConverter       $columnValueConverter;
    private Configuration              $configuration;
    private string                     $content;
    private CSVFileProcessor           $csvFileProcessor;
    private bool                       $forceCli = false;
    private LineProcessor              $lineProcessor;
    private PseudoTransactionProcessor $pseudoTransactionProcessor;

    /**
     *
     */
    public function __construct(?string $identifier)
    {
        $this->forceCli    = false; // used in POST auto import
        $this->content     = '';    // used in CLI
        $this->allErrors   = [];
        $this->allWarnings = [];
        $this->allMessages = [];
        if (null === $identifier) {
            $this->generateIdentifier();
        }
        if (null !== $identifier) {
            $this->identifier = $identifier;
        }
    }

    /**
     * @return array
     */
    public function getAllErrors(): array
    {
        return $this->allErrors;
    }

    /**
     * @return array
     */
    public function getAllMessages(): array
    {
        return $this->allMessages;
    }

    /**
     * @return array
     */
    public function getAllWarnings(): array
    {
        return $this->allWarnings;
    }

    /**
     * @inheritDoc
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @param bool $forceCli
     */
    public function setForceCli(bool $forceCli): void
    {
        $this->forceCli = $forceCli;
    }

    /**
     * @inheritDoc
     * @throws ImporterErrorException
     */
    public function start(): array
    {
        app('log')->debug(sprintf('Now in %s', __METHOD__));

        // add error
        RoutineStatusManager::addError($this->identifier, 0, 'There is no XML conversion available yet.');

        return [];
    }

    /**
     * @param int $count
     */
    private function mergeMessages(int $count): void
    {
        $this->allMessages = [];
    }

    /**
     * @param int $count
     */
    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = [];
    }

    /**
     * @param int $count
     */
    private function mergeErrors(int $count): void
    {
        $this->allErrors = [];
    }
}
