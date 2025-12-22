<?php
/*
 * ImportJob.php
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

namespace App\Models;

use App\Exceptions\ImporterErrorException;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ConversionStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Ramsey\Uuid\Uuid;

/*
 * ImportJob states:
 * - new: totally new, no data or anything.
 * - loaded: has config content and importable file content loaded, but nothing else has been done.
 * - parsed: configuration + importable file are parsed and processed. More meta-data exists in the import job.
 * - configured: configuration is set and validated. Also check on new to be created accounts. But not yet created!
 * - roles_defined: file imports with this state are ready to be mapped. may be AFTER conversion.
 * - ready_for_conversion: any import with this state is ready to be converted to Firefly III compatible transactions.
 */

class ImportJob implements Arrayable
{
    // job meta-data:
    public string            $identifier;
    private Carbon           $createdAt;
    private string           $state;
    private string           $flow                 = '';
    private string           $configurationString  = '';
    private string           $importableFileString = '';
    private ?Configuration   $configuration        = null;
    private ConversionStatus $conversionStatus;
    private array $convertedTransactions = [];

    // collected Firefly III data.
    private array $applicationAccounts = [];
    private array $currencies          = [];

    public static function createNew(): self
    {
        $job                   = new ImportJob();
        $job->conversionStatus = new ConversionStatus();
        return $job;
    }

    public static function createFromJson(string $json): self
    {
        $array = json_decode($json, true);
        return ImportJob::fromArray($array);
    }

    public static function fromArray(array $array): self
    {
        $importJob                       = new ImportJob();
        $importJob->identifier           = $array['identifier'];
        $importJob->createdAt            = Carbon::parse($array['created_at']);
        $importJob->state                = $array['state'];
        $importJob->flow                 = $array['flow'];
        $importJob->configurationString  = $array['configuration_string'];
        $importJob->importableFileString = $array['importable_file_string'];

        // only create configuration object when there is configuration to be parsed.
        $importJob->configuration = null;
        if (0 !== count($array['configuration'])) {
            $importJob->configuration = Configuration::fromArray($array['configuration']);
        }
        $importJob->conversionStatus    = ConversionStatus::fromArray($array['conversion_status']);
        $importJob->applicationAccounts = $array['application_accounts'];
        $importJob->currencies          = $array['currencies'];
        return $importJob;
    }

    private function __construct()
    {
        $this->identifier = $this->generateIdentifier();
        $this->createdAt  = Carbon::now();
        $this->state      = 'new';
    }

    private function generateIdentifier(): string
    {
        $uuid = Uuid::uuid4();
        return $uuid->toString();
    }

    public function toArray(): array
    {
        return
            [
                'identifier'             => $this->identifier,
                'created_at'             => $this->createdAt->toW3cString(),
                'state'                  => $this->state,
                'flow'                   => $this->flow,
                'configuration_string'   => $this->configurationString,
                'importable_file_string' => $this->importableFileString,
                'configuration'          => null === $this->configuration ? [] : $this->configuration->toArray(),
                'conversion_status'      => $this->conversionStatus->toArray(),
                'application_accounts'   => $this->applicationAccounts,
                'currencies'             => $this->currencies,
            ];
    }

    public function toString(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function getConfiguration(): ?Configuration
    {
        return $this->configuration;
    }

    public function setConfigurationString(string $configurationString): void
    {
        if ('' === $configurationString) {
            return;
        }
        if (!json_validate($configurationString)) {
            throw new ImporterErrorException('The configuration string is not valid JSON.');
        }
        $this->configurationString = $configurationString;
    }

    public function setFlow(string $flow): void
    {
        $this->flow = $flow;
    }

    public function setConfiguration(Configuration $configuration)
    {
        $this->configuration = $configuration;

    }

    public function setImportableFileString(string $importableFileString): void
    {
        $this->importableFileString = $importableFileString;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getConfigurationString(): string
    {
        return $this->configurationString;
    }

    public function getFlow(): string
    {
        return $this->flow;
    }

    public function getApplicationAccounts(): array
    {
        return $this->applicationAccounts;
    }

    public function setApplicationAccounts(array $applicationAccounts): void
    {
        $this->applicationAccounts = $applicationAccounts;
    }

    public function getImportableFileString(): string
    {
        return $this->importableFileString;
    }

    public function getCurrencies(): array
    {
        return $this->currencies;
    }

    public function setCurrencies(array $currencies): void
    {
        $this->currencies = $currencies;
    }

    public function getConversionStatus(): ConversionStatus
    {
        return $this->conversionStatus;
    }

    public function setConversionStatus(ConversionStatus $conversionStatus): void
    {
        $this->conversionStatus = $conversionStatus;
    }

    public function getConvertedTransactions(): array
    {
        return $this->convertedTransactions;
    }

    public function setConvertedTransactions(array $convertedTransactions): void
    {
        $this->convertedTransactions = $convertedTransactions;
    }




}
