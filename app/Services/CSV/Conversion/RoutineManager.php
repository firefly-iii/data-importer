<?php
/*
 * RoutineManager.php
 * Copyright (c) 2021 james@firefly-iii.org
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

declare(strict_types=1);

namespace App\Services\CSV\Conversion;

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
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class RoutineManager
 */
class RoutineManager implements RoutineManagerInterface
{
    use GeneratesIdentifier;
    use IsRunningCli;

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
     * @throws ImporterErrorException
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // save config
        $this->configuration = $configuration;

        // share config
        $this->csvFileProcessor           = new CSVFileProcessor($this->configuration);
        $this->lineProcessor              = new LineProcessor($this->configuration);
        $this->columnValueConverter       = new ColumnValueConverter($this->configuration);
        $this->pseudoTransactionProcessor = new PseudoTransactionProcessor($this->configuration->getDefaultAccount());

        // set identifier:
        $this->csvFileProcessor->setIdentifier($this->identifier);
        $this->lineProcessor->setIdentifier($this->identifier);
        $this->columnValueConverter->setIdentifier($this->identifier);
        $this->pseudoTransactionProcessor->setIdentifier($this->identifier);
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

        // convert CSV file into raw lines (arrays)
        $this->csvFileProcessor->setHasHeaders($this->configuration->isHeaders());
        $this->csvFileProcessor->setDelimiter($this->configuration->getDelimiter());

        // check if CLI or not and read as appropriate:
        if ('' !== $this->content) {
            $this->csvFileProcessor->setReader(FileReader::getReaderFromContent($this->content, $this->configuration->isConversion()));
        }
        if ('' === $this->content) {
            try {
                $this->csvFileProcessor->setReader(FileReader::getReaderFromSession($this->configuration->isConversion()));
            } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
                throw new ImporterErrorException($e->getMessage(), 0, $e);
            }
        }

        $CSVLines = $this->csvFileProcessor->processCSVFile();

        // convert raw lines into arrays with individual ColumnValues
        $valueArrays = $this->lineProcessor->processCSVLines($CSVLines);

        // convert value arrays into (pseudo) transactions.
        $pseudo = $this->columnValueConverter->processValueArrays($valueArrays);

        // convert pseudo transactions into actual transactions.
        $transactions = $this->pseudoTransactionProcessor->processPseudo($pseudo);


        $count = count($CSVLines);
        $this->mergeMessages($count);
        $this->mergeWarnings($count);
        $this->mergeErrors($count);

        return $transactions;
    }

    /**
     * @param int $count
     */
    private function mergeErrors(int $count): void
    {
        $this->allErrors = $this->mergeArrays([
                                                  $this->csvFileProcessor->getErrors(),
                                                  $this->lineProcessor->getErrors(),
                                                  $this->columnValueConverter->getErrors(),
                                                  $this->pseudoTransactionProcessor->getErrors(),
                                              ], $count);

    }

    /**
     * @param int $count
     */
    private function mergeMessages(int $count): void
    {
        $this->allMessages = $this->mergeArrays([
                                                    $this->csvFileProcessor->getMessages(),
                                                    $this->lineProcessor->getMessages(),
                                                    $this->columnValueConverter->getMessages(),
                                                    $this->pseudoTransactionProcessor->getMessages(),
                                                ], $count);
    }

    /**
     * @param int $count
     */
    private function mergeWarnings(int $count): void
    {
        $this->allWarnings = $this->mergeArrays([
                                                    $this->csvFileProcessor->getWarnings(),
                                                    $this->lineProcessor->getWarnings(),
                                                    $this->columnValueConverter->getWarnings(),
                                                    $this->pseudoTransactionProcessor->getWarnings(),
                                                ], $count);
    }

    /**
     * @param array $collection
     * @param int   $count
     *
     * @return array
     */
    private function mergeArrays(array $collection, int $count): array
    {
        $return = [];
        foreach ($collection as $set) {
            if (0 === count($set)) {
                continue;
            }
            for ($i = 0; $i < $count; $i++) {
                if (array_key_exists($i, $set)) {
                    $return[$i] = array_key_exists($i, $return) ? $return[$i] : [];
                    $return[$i] = array_merge($return[$i], $set[$i]);
                }
            }
        }

        // sanity check (should not be necessary)
        foreach ($return as $index => $set) {
            if (0 === count($set)) {
                unset($return[$index]);
            }
        }
        if (0 === count($return)) {
            $return = [];
        }

        return $return;
    }
}
