<?php

declare(strict_types=1);
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
use App\Services\LunchFlow\Model\Account as LunchFlowAccount;
use App\Services\Nordigen\Model\Account as NordigenAccount;
use App\Services\Session\Constants;
use App\Services\Shared\Configuration\Configuration;
use App\Services\Shared\Conversion\ConversionStatus;
use App\Services\Shared\Import\Status\SubmissionStatus;
use App\Services\SimpleFIN\Model\Account as SimpleFINAccount;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/*
 * ImportJob states:
 * - new: totally new, no data or anything.
 * - contains_content: has config content and importable file content loaded, but nothing else has been done.
 * - needs_connection_details: parsed and processed, but still needs more meta-data (like a session) to continue to configuration
 * - is_parsed: configuration is parsed and processed. More meta-data exists in the import job.
 * - is_configured: configuration is set and validated. Also check on new to be created accounts. But not yet created!
 * - configured_and_roles_defined: file imports with this state are configured and have roles for their data. are ready to be mapped. may be AFTER conversion.
 * - configured_roles_map_in_place: file import has roles defined, configuration and data is mapped. Is ready to be converted.
 * - ready_for_conversion: any import with this state is ready to be converted to Firefly III compatible transactions.
 * - ready_for_submission: any import with converted data that can be submitted to Firefly III.
 */

class ImportJob implements Arrayable
{
    // job meta-data:
    public string           $identifier;
    private string          $instanceIdentifier    = '';
    private int             $instanceCounter       = 0;
    private Carbon          $createdAt;
    private string          $state;
    private string          $flow                  = '';
    private string          $configurationString   = '';
    private string          $importableFileString  = '';
    private ?Configuration  $configuration         = null;
    public ConversionStatus $conversionStatus;
    public SubmissionStatus $submissionStatus;
    private array           $convertedTransactions = [];
    private bool            $initialized           = false;

    private array $sophtronInstitutions = [];

    // collected Firefly III data.
    private array $applicationAccounts   = [];
    private array $currencies            = [];
    private array $serviceAccounts       = [];
    private array $authenticationDetails = [];

    public static function createNew(): self
    {
        $job = new self();
        $job->refreshInstanceIdentifier();
        $job->conversionStatus = new ConversionStatus();
        $job->submissionStatus = new SubmissionStatus();

        return $job;
    }

    public static function createFromJson(string $json): self
    {
        // Log::debug('ImportJob::createFromJson()');
        $array = json_decode($json, true);

        return self::fromArray($array);
    }

    public static function fromArray(array $array): self
    {
        // Log::debug('ImportJob::toArray()');
        $importJob                        = new self();
        $importJob->instanceIdentifier    = $array['instance_identifier'];
        $importJob->instanceCounter       = $array['instance_counter'];
        $importJob->identifier            = $array['identifier'];
        $importJob->initialized           = $array['initialized'];
        $importJob->createdAt             = Carbon::parse($array['created_at']);
        $importJob->state                 = $array['state'];
        $importJob->flow                  = $array['flow'];
        $importJob->configurationString   = $array['configuration_string'];
        $importJob->importableFileString  = $array['importable_file_string'];
        $importJob->authenticationDetails = $array['authentication_details'];
        $importJob->sophtronInstitutions  = $array['sophtron_institutions'];

        // only create configuration object when there is configuration to be parsed.
        $importJob->configuration = null;
        if (0 !== count($array['configuration'])) {
            $importJob->configuration = Configuration::fromArray($array['configuration']);
        }
        $importJob->conversionStatus      = ConversionStatus::fromArray($array['conversion_status']);
        $importJob->submissionStatus      = SubmissionStatus::fromArray($array['submission_status']);
        $importJob->convertedTransactions = $array['converted_transactions'];

        $importJob->applicationAccounts = [];
        $importJob->serviceAccounts     = [];

        // Log::debug('Restoring service accounts');
        /** @var array $item */
        foreach ($array['service_accounts'] as $item) {
            $class                        = $item['class'];
            $importJob->serviceAccounts[] = $class::fromArray($item);
        }
        $keys = [Constants::ASSET_ACCOUNTS, Constants::LIABILITIES];
        foreach ($keys as $key) {
            $importJob->applicationAccounts[$key] = [];
            if (array_key_exists($key, $array['application_accounts'])) {
                /** @var array $item */
                foreach ($array['application_accounts'][$key] as $item) {
                    $importJob->applicationAccounts[$key][] = Account::fromArray($item);
                }
            }
        }
        // Log::debug('Restored application accounts');
        $importJob->currencies = $array['currencies'];

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
        // Log::debug('ImportJob::toArray()');
        $serviceAccounts     = [];
        $applicationAccounts = [];

        /** @var LunchFlowAccount|NordigenAccount|SimpleFINAccount $serviceAccount */
        foreach ($this->serviceAccounts as $serviceAccount) {
            $serviceAccounts[] = $serviceAccount->toArray();
        }
        $keys = [Constants::ASSET_ACCOUNTS, Constants::LIABILITIES];
        foreach ($keys as $key) {
            $applicationAccounts[$key] ??= [];
            foreach ($this->applicationAccounts[$key] ?? [] as $current) {
                $applicationAccounts[$key][] = $current->toArray();
            }
        }

        return
            [
                'identifier'             => $this->identifier,
                'instance_identifier'    => $this->instanceIdentifier,
                'instance_counter'       => $this->instanceCounter,
                'initialized'            => $this->initialized,
                'created_at'             => $this->createdAt->toW3cString(),
                'state'                  => $this->state,
                'flow'                   => $this->flow,
                'configuration_string'   => $this->configurationString,
                'sophtron_institutions'  => $this->sophtronInstitutions,
                'authentication_details' => $this->authenticationDetails,
                'importable_file_string' => $this->importableFileString,
                'configuration'          => null === $this->configuration ? [] : $this->configuration->toArray(),
                'conversion_status'      => $this->conversionStatus->toArray(),
                'submission_status'      => $this->submissionStatus->toArray(),
                'converted_transactions' => $this->convertedTransactions,
                'application_accounts'   => $applicationAccounts,
                'service_accounts'       => $serviceAccounts,
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

    public function setConfiguration(Configuration $configuration): void
    {
        $configuration->updateDateRange();
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
        // Log::debug('setApplicationAccounts()', $applicationAccounts);
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

    public function getConvertedTransactions(): array
    {
        return $this->convertedTransactions;
    }

    public function setConvertedTransactions(array $convertedTransactions): void
    {
        $this->convertedTransactions = $convertedTransactions;
    }

    public function getServiceAccounts(): array
    {
        // Log::debug(__METHOD__);
        return $this->serviceAccounts;
    }

    public function setServiceAccounts(array $serviceAccounts): void
    {
        // Log::debug(__METHOD__);
        $this->serviceAccounts = $serviceAccounts;
    }

    public function refreshInstanceIdentifier(): void
    {
        $this->instanceIdentifier = Uuid::uuid4()->toString();
        ++$this->instanceCounter;
        Log::debug(sprintf('Refresh unique instance identifier: %s', $this->instanceIdentifier));
    }

    public function getInstanceIdentifier(): string
    {
        return $this->instanceIdentifier;
    }

    public function getInstanceCounter(): int
    {
        return $this->instanceCounter;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function setInitialized(bool $initialized): void
    {
        $this->initialized = $initialized;
    }

    public function getAuthenticationDetails(): array
    {
        return $this->authenticationDetails;
    }

    public function getSophtronInstitutions(): array
    {
        return $this->sophtronInstitutions;
    }

    public function setSophtronInstitutions(array $sophtronInstitutions): void
    {
        $this->sophtronInstitutions = $sophtronInstitutions;
    }


}
