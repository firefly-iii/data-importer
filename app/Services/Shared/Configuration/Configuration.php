<?php

/*
 * Configuration.php
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

namespace App\Services\Shared\Configuration;

use App\Exceptions\ImporterErrorException;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

/**
 * Class Configuration
 */
class Configuration
{
    public const int VERSION        = 3;
    private array  $accounts        = [];
    private array  $newAccounts     = [];
    private bool   $addImportTag    = true;
    private string $connection      = '0';
    private string $contentType     = 'csv';
    private string $camtType        = '';
    private bool   $conversion;
    private string $customTag       = '';
    private string $date            = 'Y-m-d';
    private string $dateNotAfter;
    private string $dateNotBefore;
    private string $dateRange;
    private int    $dateRangeNumber;
    private string $dateRangeUnit;

    // same date range settings but for earlier transactions.
    private int    $dateRangeNotAfterNumber;
    private string $dateRangeNotAfterUnit;

    private int $defaultAccount     = 1;

    // nordigen configuration
    private string $delimiter       = 'comma';
    private array  $doMapping       = [];

    // flow and file type
    private string $duplicateDetectionMethod;
    private string $flow            = 'file';

    // csv config
    private string $groupedTransactionHandling;

    // spectre + nordigen configuration
    private bool $headers           = false;

    // spectre configuration
    private string $identifier;
    private bool   $ignoreDuplicateLines;
    private bool   $ignoreDuplicateTransactions;

    // camt configuration
    private bool $ignoreSpectreCategories;
    private bool $mapAllData;

    // simplefin configuration
    private bool   $pendingTransactions;
    private string $accessToken;

    // date range settings
    private array  $mapping         = [];
    private string $nordigenBank;
    private string $nordigenCountry;
    private string $nordigenMaxDays;
    private array  $nordigenRequisitions;

    private string $lunchFlowApiKey = '';

    // what type of import?
    private array $roles            = [];

    private bool $rules             = true;

    // configuration for "classic" method:
    private bool  $skipForm         = false;
    private array $specifics        = [];

    // configuration for "cell" method:
    private int    $uniqueColumnIndex;
    private string $uniqueColumnType;

    // configuration for pseudo identifier (composite identifiers):
    private array $pseudoIdentifier = [];

    private bool $useEntireOpposingAddress;

    // configuration for utf-8
    private int $version;

    /**
     * Configuration constructor.
     */
    private function __construct()
    {
        $this->customTag                   = '';

        // date range settings
        $this->dateRange                   = 'all';
        $this->dateRangeNumber             = 30;
        $this->dateRangeUnit               = 'd';
        // by default, no "not after" settings.
        $this->dateRangeNotAfterNumber     = 0;
        $this->dateRangeNotAfterUnit       = '';

        $this->dateNotBefore               = '';
        $this->dateNotAfter                = '';

        // camt settings
        $this->groupedTransactionHandling  = 'single';
        $this->useEntireOpposingAddress    = false;

        // nordigen configuration
        $this->nordigenCountry             = '';
        $this->nordigenBank                = '';
        $this->nordigenRequisitions        = [];
        $this->nordigenMaxDays             = '90';

        // lunch flow configuration
        $this->lunchFlowApiKey             = '';

        // spectre
        $this->identifier                  = '0';
        $this->ignoreSpectreCategories     = false;

        // mapping for spectre + nordigen
        $this->mapAllData                  = false;

        // simplefin configuration
        $this->pendingTransactions         = true;
        $this->accessToken                 = '';

        // double transaction detection:
        $this->duplicateDetectionMethod    = 'classic';

        // config for "classic":
        // Log::debug('Configuration __construct. ignoreDuplicateTransactions = true');
        $this->ignoreDuplicateTransactions = true;
        $this->ignoreDuplicateLines        = true;

        // config for "cell":
        $this->uniqueColumnIndex           = 0;
        $this->uniqueColumnType            = 'internal_reference';

        // utf8
        $this->conversion                  = false;

        $this->version                     = self::VERSION;
    }

    /**
     * @return $this
     */
    public static function fromFile(array $data): self
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));
        $version = $data['version'] ?? 1;
        if (1 === $version) {
            Log::debug('v1, going for classic.');

            return self::fromClassicFile($data);
        }
        if (2 === $version) {
            Log::debug('v2 config file!');

            return self::fromVersionTwo($data);
        }
        if (3 === $version) {
            Log::debug('v3 config file!');

            return self::fromVersionThree($data);
        }

        throw new UnexpectedValueException(sprintf('Configuration file version "%s" cannot be parsed.', $version));
    }

    /**
     * @return static
     */
    private static function fromClassicFile(array $data): self
    {
        $delimiters                          = config('csv.delimiters_reversed');
        $classicRoleNames                    = config('csv.classic_roles');
        $object                              = new self();
        $object->headers                     = $data['has-headers'] ?? false;
        $object->date                        = $data['date-format'] ?? $object->date;
        $object->delimiter                   = $delimiters[$data['delimiter']] ?? 'comma';
        $object->defaultAccount              = $data['import-account'] ?? $object->defaultAccount;
        $object->rules                       = $data['apply-rules'] ?? true;
        $object->flow                        = $data['flow'] ?? 'file';
        $object->contentType                 = $data['content_type'] ?? 'csv';
        $object->camtType                    = $data['camt_type'] ?? '';
        $object->customTag                   = $data['custom_tag'] ?? '';

        // camt settings
        $object->groupedTransactionHandling  = $data['grouped_transaction_handling'] ?? 'single';
        $object->useEntireOpposingAddress    = $data['use_entire_opposing_address'] ?? false;

        // other settings (are not in v1 anyway)
        $object->dateRange                   = $data['date_range'] ?? 'all';
        $object->dateRangeNumber             = $data['date_range_number'] ?? 30;
        $object->dateRangeUnit               = $data['date_range_unit'] ?? 'd';

        // by default, no "not after" settings (are not in v1 anyway)
        $object->dateRangeNotAfterNumber     = $data['date_range_not_after_number'] ?? 0;
        $object->dateRangeNotAfterUnit       = $data['date_range_not_after_unit'] ?? '';

        $object->dateNotBefore               = $data['date_not_before'] ?? '';
        $object->dateNotAfter                = $data['date_not_after'] ?? '';

        // spectre settings (are not in v1 anyway)
        $object->identifier                  = $data['identifier'] ?? '0';
        $object->connection                  = $data['connection'] ?? '0';
        $object->ignoreSpectreCategories     = $data['ignore_spectre_categories'] ?? false;

        // nordigen settings (are not in v1 anyway)
        $object->nordigenCountry             = $data['nordigen_country'] ?? '';
        $object->nordigenBank                = $data['nordigen_bank'] ?? '';
        $object->nordigenRequisitions        = $data['nordigen_requisitions'] ?? [];
        $object->nordigenMaxDays             = $data['nordigen_max_days'] ?? '90';

        // lunch flow configuration
        $object->lunchFlowApiKey             = $data['lunch_flow_api_key'] ?? '';

        // settings for spectre + nordigen (are not in v1 anyway)
        $object->mapAllData                  = $data['map_all_data'] ?? false;
        $object->accounts                    = $data['accounts'] ?? [];

        // simplefin
        $object->pendingTransactions         = $data['pending_transactions'] ?? true;


        $object->ignoreDuplicateTransactions = $data['ignore_duplicate_transactions'] ?? true;
        Log::debug(sprintf('Configuration fromClassicFile: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));

        if (isset($data['ignore_duplicates']) && true === $data['ignore_duplicates']) {
            Log::debug('Will ignore duplicates.');
            $object->ignoreDuplicateTransactions = true;
            Log::debug(sprintf('Configuration fromClassicFile: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));
            $object->duplicateDetectionMethod    = 'classic';
        }

        if (isset($data['ignore_duplicates']) && false === $data['ignore_duplicates']) {
            Log::debug('Will NOT ignore duplicates.');
            $object->ignoreDuplicateTransactions = false;
            $object->duplicateDetectionMethod    = 'none';
            Log::debug(sprintf('Configuration fromClassicFile: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));
        }

        if (isset($data['ignore_lines']) && true === $data['ignore_lines']) {
            Log::debug('Will ignore duplicate lines.');
            $object->ignoreDuplicateLines = true;
        }

        // array values
        $object->specifics                   = [];
        $object->roles                       = [];
        $object->doMapping                   = [];
        $object->mapping                     = [];
        $object->accounts                    = [];

        // utf8
        $object->conversion                  = $data['conversion'] ?? false;

        // loop roles from classic file:
        $roles                               = $data['column-roles'] ?? [];
        foreach ($roles as $index => $role) {
            // some roles have been given a new name some time in the past.
            $role   = $classicRoleNames[$role] ?? $role;
            $config = config(sprintf('csv.import_roles.%s', $role));
            if (null !== $config) {
                $object->roles[$index] = $role;
            }
            if (null === $config) {
                Log::warn(sprintf('There is no config for "%s"!', $role));
            }
        }
        ksort($object->roles);

        // loop do mapping from classic file.
        $doMapping                           = $data['column-do-mapping'] ?? [];
        foreach ($doMapping as $index => $map) {
            $index                     = (int)$index;
            $object->doMapping[$index] = $map;
        }
        ksort($object->doMapping);

        // loop mapping from classic file.
        $mapping                             = $data['column-mapping-config'] ?? [];
        foreach ($mapping as $index => $map) {
            $index                   = (int)$index;
            $object->mapping[$index] = $map;
        }
        ksort($object->mapping);

        // set version to latest version and return.
        $object->version                     = self::VERSION;

        if ('csv' === $object->flow) {
            $object->flow = 'file';
        }

        return $object;
    }

    /**
     * @return static
     */
    private static function fromVersionTwo(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $delimiters                          = config('csv.delimiters_reversed');
        $object                              = new self();
        $object->headers                     = $array['headers'] ?? false;
        $object->date                        = $array['date'] ?? 'Y-m-d';
        $object->defaultAccount              = $array['default_account'] ?? 0;
        $object->delimiter                   = $delimiters[$array['delimiter'] ?? ','] ?? 'comma';
        $object->rules                       = $array['rules'] ?? true;
        $object->skipForm                    = $array['skip_form'] ?? false;
        $object->addImportTag                = $array['add_import_tag'] ?? true;
        $object->roles                       = $array['roles'] ?? [];
        $object->mapping                     = $array['mapping'] ?? [];
        $object->doMapping                   = $array['do_mapping'] ?? [];
        $object->version                     = self::VERSION;
        $object->flow                        = $array['flow'] ?? 'file';
        $object->contentType                 = $array['content_type'] ?? 'csv';
        $object->camtType                    = $array['camt_type'] ?? '';
        $object->customTag                   = $array['custom_tag'] ?? '';

        // Log::debug(sprintf('Configuration fromArray, default_account=%s', var_export($object->defaultAccount, true)));

        // sort
        ksort($object->doMapping);
        ksort($object->mapping);
        ksort($object->roles);

        // settings for spectre + nordigen
        $object->mapAllData                  = $array['map_all_data'] ?? false;
        $object->accounts                    = $array['accounts'] ?? [];
        $object->newAccounts                 = $array['new_accounts'] ?? [];

        // spectre
        $object->identifier                  = $array['identifier'] ?? '0';
        $object->connection                  = $array['connection'] ?? '0';
        $object->ignoreSpectreCategories     = $array['ignore_spectre_categories'] ?? false;

        // date range settings
        $object->dateRange                   = $array['date_range'] ?? 'all';
        $object->dateRangeNumber             = $array['date_range_number'] ?? 30;
        $object->dateRangeUnit               = $array['date_range_unit'] ?? 'd';

        // add date range not after settings.
        $object->dateRangeNotAfterNumber     = $array['date_range_not_after_number'] ?? 0;
        $object->dateRangeNotAfterUnit       = $array['date_range_not_after_unit'] ?? '';

        $object->dateNotBefore               = $array['date_not_before'] ?? '';
        $object->dateNotAfter                = $array['date_not_after'] ?? '';

        // camt
        $object->groupedTransactionHandling  = $array['grouped_transaction_handling'] ?? 'single';
        $object->useEntireOpposingAddress    = $array['use_entire_opposing_address'] ?? false;

        // nordigen information:
        $object->nordigenCountry             = $array['nordigen_country'] ?? '';
        $object->nordigenBank                = $array['nordigen_bank'] ?? '';
        $object->nordigenRequisitions        = $array['nordigen_requisitions'] ?? [];
        $object->nordigenMaxDays             = $array['nordigen_max_days'] ?? '90';

        // lunch flow configuration
        $object->lunchFlowApiKey             = $array['lunch_flow_api_key'] ?? '';

        // simplefin
        $object->pendingTransactions         = $array['pending_transactions'] ?? true;

        // duplicate transaction detection
        $object->duplicateDetectionMethod    = $array['duplicate_detection_method'] ?? 'classic';

        // config for "classic":
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'] ?? false;
        $object->ignoreDuplicateTransactions = $array['ignore_duplicate_transactions'] ?? true;
        // Log::debug(sprintf('Configuration fromArray: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));

        if (!array_key_exists('duplicate_detection_method', $array)) {
            if (false === $object->ignoreDuplicateTransactions) {
                Log::debug('Set the duplicate method to "none".');
                $object->duplicateDetectionMethod = 'none';
            }
        }

        // overrule a setting:
        if ('none' === $object->duplicateDetectionMethod) {
            $object->ignoreDuplicateTransactions = false;
            Log::debug(sprintf('Configuration fromClassicFile overruled: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));
        }

        // config for "cell":
        $object->uniqueColumnIndex           = $array['unique_column_index'] ?? 0;
        $object->uniqueColumnType            = $array['unique_column_type'] ?? '';

        // config for pseudo identifier (composite identifiers):
        $object->pseudoIdentifier            = $array['pseudo_identifier'] ?? [];

        // Migrate old single-column identifier to pseudo identifier format
        $object->migrateSingleIdentifierToPseudoIdentifier();

        // utf8
        $object->conversion                  = $array['conversion'] ?? false;

        // simplefin configuration
        $object->pendingTransactions         = $array['pending_transactions'] ?? true;
        $object->accessToken                 = $array['access_token'] ?? '';

        if ('csv' === $object->flow) {
            $object->flow        = 'file';
            $object->contentType = 'csv';
        }

        return $object;
    }

    /**
     * @return static
     */
    private static function fromVersionThree(array $data): self
    {
        $object            = self::fromArray($data);
        $object->specifics = [];

        return $object;
    }

    /**
     * @return $this
     */
    public static function fromRequest(array $array): self
    {
        $delimiters                          = config('csv.delimiters_reversed');
        $object                              = new self();
        $object->version                     = self::VERSION;
        $object->headers                     = $array['headers'] ?? false;
        $object->date                        = $array['date'];
        $object->defaultAccount              = $array['default_account'];
        $object->delimiter                   = $delimiters[$array['delimiter']] ?? 'comma';
        $object->rules                       = $array['rules'];
        $object->skipForm                    = $array['skip_form'];
        $object->addImportTag                = $array['add_import_tag'] ?? true;
        $object->roles                       = $array['roles'] ?? [];
        $object->mapping                     = $array['mapping'] ?? [];
        $object->doMapping                   = $array['do_mapping'] ?? [];
        $object->contentType                 = $array['content_type'] ?? 'csv';
        $object->camtType                    = $array['camt_type'] ?? '';
        $object->customTag                   = $array['custom_tag'] ?? '';

        Log::debug(sprintf('Configuration fromRequest, default_account=%s', var_export($object->defaultAccount, true)));

        // mapping for spectre + nordigen
        $object->mapAllData                  = $array['map_all_data'] ?? false;

        // spectre
        $object->identifier                  = $array['identifier'] ?? '0';
        $object->connection                  = $array['connection'] ?? '0';
        $object->ignoreSpectreCategories     = $array['ignore_spectre_categories'] ?? false;

        // nordigen:
        $object->nordigenCountry             = $array['nordigen_country'] ?? '';
        $object->nordigenBank                = $array['nordigen_bank'] ?? '';
        $object->nordigenRequisitions        = $array['nordigen_requisitions'] ?? [];
        $object->nordigenMaxDays             = $array['nordigen_max_days'] ?? '90';

        // lunch flow configuration
        $object->lunchFlowApiKey             = $array['lunch_flow_api_key'] ?? '';

        $object->groupedTransactionHandling  = $array['grouped_transaction_handling'] ?? 'single';
        $object->useEntireOpposingAddress    = $array['use_entire_opposing_address'] ?? false;

        // spectre + nordigen
        $object->accounts                    = $array['accounts'] ?? [];
        $object->newAccounts                 = $array['new_accounts'] ?? [];

        // date range settings
        $object->dateRange                   = $array['date_range'] ?? 'all';
        $object->dateRangeNumber             = $array['date_range_number'] ?? 30;
        $object->dateRangeUnit               = $array['date_range_unit'] ?? 'd';

        // date range settings for "not after"
        $object->dateRangeNotAfterNumber     = $array['date_range_not_after_number'] ?? 0;
        $object->dateRangeNotAfterUnit       = $array['date_range_not_after_unit'] ?? '';

        // null or Carbon because fromRequest will give Carbon object.
        $object->dateNotBefore               = null === $array['date_not_before'] ? '' : $array['date_not_before']->format('Y-m-d');
        $object->dateNotAfter                = null === $array['date_not_after'] ? '' : $array['date_not_after']->format('Y-m-d');

        // duplicate transaction detection
        $object->duplicateDetectionMethod    = $array['duplicate_detection_method'] ?? 'classic';

        // config for "classic":
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'];
        $object->ignoreDuplicateTransactions = true;
        Log::debug(sprintf('Configuration fromRequest: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));

        // config for "cell":
        $object->uniqueColumnIndex           = $array['unique_column_index'] ?? 0;
        $object->uniqueColumnType            = $array['unique_column_type'] ?? '';

        // config for pseudo identifier (composite identifiers):
        $object->pseudoIdentifier            = $array['pseudo_identifier'] ?? [];

        // Migrate old single-column identifier to pseudo identifier format
        $object->migrateSingleIdentifierToPseudoIdentifier();

        // utf8 conversion
        $object->conversion                  = $array['conversion'] ?? false;

        // simplefin configuration
        $object->pendingTransactions         = $array['pending_transactions'] ?? true;
        $object->accessToken                 = $array['access_token'] ?? '';

        // flow
        $object->flow                        = $array['flow'] ?? 'file';

        // overrule a setting:
        if ('none' === $object->duplicateDetectionMethod) {
            $object->ignoreDuplicateTransactions = false;
            Log::debug(sprintf('Configuration overruled from none: ignoreDuplicateTransactions = %s', var_export($object->ignoreDuplicateTransactions, true)));
        }

        $object->specifics                   = [];
        foreach ($array['specifics'] as $key => $enabled) {
            if (true === $enabled) {
                $object->specifics[] = $key;
            }
        }
        if ('csv' === $object->flow) {
            $object->flow        = 'file';
            $object->contentType = 'csv';
        }

        return $object;
    }

    /**
     * Create a standard empty configuration.
     */
    public static function make(): self
    {
        return self::fromArray([]);
    }

    public function addRequisition(string $key, string $identifier): void
    {
        $this->nordigenRequisitions[$key] = $identifier;
    }

    public function clearRequisitions(): void
    {
        $this->nordigenRequisitions = [];
    }

    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function setAccounts(array $accounts): void
    {
        $this->accounts = $accounts;
    }

    public function getNewAccounts(): array
    {
        return $this->newAccounts;
    }

    public function setNewAccounts(array $newAccounts): void
    {
        $this->newAccounts = $newAccounts;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function getCamtType(): string
    {
        return $this->camtType;
    }

    public function setCamtType(string $camtType): void
    {
        $this->camtType = $camtType;
    }

    public function getCustomTag(): string
    {
        return $this->customTag;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getDateNotAfter(): string
    {
        return $this->dateNotAfter;
    }

    public function getDateNotBefore(): string
    {
        return $this->dateNotBefore;
    }

    public function getDateRange(): string
    {
        return $this->dateRange;
    }

    public function getDateRangeNumber(): int
    {
        return $this->dateRangeNumber;
    }

    public function getDateRangeUnit(): string
    {
        return $this->dateRangeUnit;
    }

    public function getDefaultAccount(): ?int
    {
        // Log::debug(sprintf('Configuration getDefaultAccount return %s', var_export($this->defaultAccount, true)));

        return $this->defaultAccount;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getDoMapping(): array
    {
        return $this->doMapping ?? [];
    }

    public function setDoMapping(array $doMapping): void
    {
        $this->doMapping = $doMapping;
    }

    public function getDuplicateDetectionMethod(): string
    {
        return $this->duplicateDetectionMethod;
    }

    public function getFlow(): string
    {
        return $this->flow;
    }

    public function setFlow(string $flow): void
    {
        $this->flow = $flow;
    }

    public function getGroupedTransactionHandling(): string
    {
        return $this->groupedTransactionHandling;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getMapping(): array
    {
        return $this->mapping ?? [];
    }

    public function setMapping(array $mapping): void
    {
        $newMap        = [];
        foreach ($mapping as $column => $map) {
            ksort($map);
            $newMap[$column] = $map;
        }
        $this->mapping = $newMap;
    }

    public function getNordigenBank(): string
    {
        return $this->nordigenBank;
    }

    public function setNordigenBank(string $nordigenBank): void
    {
        $this->nordigenBank = $nordigenBank;
    }

    public function getNordigenCountry(): string
    {
        return $this->nordigenCountry;
    }

    public function setNordigenCountry(string $nordigenCountry): void
    {
        $this->nordigenCountry = $nordigenCountry;
    }

    public function getNordigenMaxDays(): string
    {
        return $this->nordigenMaxDays;
    }

    public function setNordigenMaxDays(string $nordigenMaxDays): void
    {
        $this->nordigenMaxDays = $nordigenMaxDays;
    }

    public function getNordigenRequisitions(): array
    {
        return $this->nordigenRequisitions;
    }

    public function getRequisition(string $key): ?string
    {
        return array_key_exists($key, $this->nordigenRequisitions) ? $this->nordigenRequisitions[$key] : null;
    }

    public function getRoles(): array
    {
        return $this->roles ?? [];
    }

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function setPendingTransactions(bool $pendingTransactions): void
    {
        $this->pendingTransactions = $pendingTransactions;
    }

    public function getSpecifics(): array
    {
        return $this->specifics;
    }

    public function getUniqueColumnIndex(): int
    {
        return $this->uniqueColumnIndex;
    }

    public function getUniqueColumnType(): string
    {
        return $this->uniqueColumnType;
    }

    public function getPseudoIdentifier(): ?array
    {
        return count($this->pseudoIdentifier) > 0 ? $this->pseudoIdentifier : null;
    }

    public function hasPseudoIdentifier(): bool
    {
        return count($this->pseudoIdentifier) > 0;
    }

    public function setPseudoIdentifier(array $pseudoIdentifier): void
    {
        $this->pseudoIdentifier = $pseudoIdentifier;
    }

    /**
     * Get unique column index display value (comma-separated if pseudo identifier exists).
     * This is used for displaying in the UI form.
     */
    public function getUniqueColumnIndexDisplay(): string
    {
        // If pseudo identifier exists, return comma-separated source columns
        if ($this->hasPseudoIdentifier() && isset($this->pseudoIdentifier['source_columns'])) {
            return implode(',', $this->pseudoIdentifier['source_columns']);
        }

        // Otherwise return single index
        return (string)$this->uniqueColumnIndex;
    }

    /**
     * Migrate old single-column identifier format to unified pseudo identifier format.
     * This ensures backward compatibility while unifying the behavior.
     */
    private function migrateSingleIdentifierToPseudoIdentifier(): void
    {
        // Only migrate if:
        // 1. Using identifier-based detection ('cell')
        // 2. No pseudo identifier exists yet (old format)
        // 3. Have a valid unique column type
        if ('cell' !== $this->duplicateDetectionMethod) {
            return;
        }

        if (count($this->pseudoIdentifier) > 0) {
            return; // Already using new format
        }

        if ('' === $this->uniqueColumnType) {
            return; // No identifier configured
        }

        // Create pseudo identifier from old single-column format
        Log::debug(sprintf('Migrating old identifier format to pseudo identifier: index=%d, type=%s', $this->uniqueColumnIndex, $this->uniqueColumnType));

        $this->pseudoIdentifier = [
            'source_columns' => [$this->uniqueColumnIndex],
            'separator'      => '|',
            'role'           => $this->uniqueColumnType,
        ];
    }

    public function getPendingTransactions(): bool
    {
        return $this->pendingTransactions;
    }

    public function hasSpecific(string $name): bool
    {
        return in_array($name, $this->specifics, true);
    }

    public function isAddImportTag(): bool
    {
        return $this->addImportTag;
    }

    public function isConversion(): bool
    {
        return $this->conversion;
    }

    public function isHeaders(): bool
    {
        return $this->headers;
    }

    public function isIgnoreDuplicateLines(): bool
    {
        return $this->ignoreDuplicateLines;
    }

    public function isIgnoreDuplicateTransactions(): bool
    {
        Log::debug(sprintf('isIgnoreDuplicateTransactions(%s)', var_export($this->ignoreDuplicateTransactions, true)));

        return $this->ignoreDuplicateTransactions;
    }

    public function isIgnoreSpectreCategories(): bool
    {
        return $this->ignoreSpectreCategories;
    }

    public function isMapAllData(): bool
    {
        return $this->mapAllData;
    }

    public function isRules(): bool
    {
        return $this->rules;
    }

    public function isSkipForm(): bool
    {
        return $this->skipForm;
    }

    public function isUseEntireOpposingAddress(): bool
    {
        return $this->useEntireOpposingAddress;
    }

    /**
     * Return the array but drop some potentially massive arrays.
     */
    public function toSessionArray(): array
    {
        $array = $this->toArray();
        unset($array['mapping'], $array['do_mapping'], $array['roles']);

        return $array;
    }

    public function toArray(): array
    {
        $array                                  = [
            'version'                      => $this->version,
            'source'                       => sprintf('ff3-importer-%s', config('importer.version')),
            'created_at'                   => date(DateTimeInterface::W3C),
            'date'                         => $this->date,
            'default_account'              => $this->defaultAccount,
            'delimiter'                    => $this->delimiter,
            'headers'                      => $this->headers,
            'rules'                        => $this->rules,
            'skip_form'                    => $this->skipForm,
            'add_import_tag'               => $this->addImportTag,
            'roles'                        => $this->roles,
            'do_mapping'                   => $this->doMapping,
            'mapping'                      => $this->mapping,
            'duplicate_detection_method'   => $this->duplicateDetectionMethod,
            'ignore_duplicate_lines'       => $this->ignoreDuplicateLines,
            'unique_column_index'          => $this->uniqueColumnIndex,
            'unique_column_type'           => $this->uniqueColumnType,
            'pseudo_identifier'            => $this->pseudoIdentifier,
            'flow'                         => $this->flow,
            'content_type'                 => $this->contentType,
            'camt_type'                    => $this->camtType,
            'custom_tag'                   => $this->customTag,

            // spectre
            'identifier'                   => $this->identifier,
            'connection'                   => $this->connection,
            'ignore_spectre_categories'    => $this->ignoreSpectreCategories,

            // camt:
            'grouped_transaction_handling' => $this->groupedTransactionHandling,
            'use_entire_opposing_address'  => $this->useEntireOpposingAddress,

            // mapping for spectre + nordigen
            'map_all_data'                 => $this->mapAllData,

            // simplefin configuration
            'pending_transactions'         => $this->pendingTransactions,
            'access_token'                 => $this->accessToken,

            // settings for spectre + nordigen
            'accounts'                     => $this->accounts,
            'new_accounts'                 => $this->newAccounts,

            // date range settings:
            'date_range'                   => $this->dateRange,
            'date_range_number'            => $this->dateRangeNumber,
            'date_range_unit'              => $this->dateRangeUnit,
            'date_range_not_after_unit'    => $this->dateRangeNotAfterUnit,
            'date_range_not_after_number'  => $this->dateRangeNotAfterNumber,
            'date_not_before'              => $this->dateNotBefore,
            'date_not_after'               => $this->dateNotAfter,

            // nordigen information:
            'nordigen_country'             => $this->nordigenCountry,
            'nordigen_bank'                => $this->nordigenBank,
            'nordigen_requisitions'        => $this->nordigenRequisitions,
            'nordigen_max_days'            => $this->nordigenMaxDays,
            'lunch_flow_api_key'           => $this->lunchFlowApiKey,

            // utf8
            'conversion'                   => $this->conversion,
        ];

        // make sure that "ignore duplicate transactions" is turned off
        // to deliver a consistent file.
        $array['ignore_duplicate_transactions'] = false;
        if ('classic' === $this->duplicateDetectionMethod) {
            $array['ignore_duplicate_transactions'] = true;
        }

        return $array;
    }

    public function updateDateRange(): void
    {
        Log::debug(sprintf('[%s] Now in %s', config('importer.version'), __METHOD__));

        // set date and time:
        switch ($this->dateRange) {
            default:
            case 'all':
                Log::debug('Range is null, set all to NULL.');
                $this->dateRangeUnit           = 'd';
                $this->dateRangeNumber         = 30;
                $this->dateNotBefore           = '';
                $this->dateNotAfter            = '';
                $this->dateRangeNotAfterUnit   = '';
                $this->dateRangeNotAfterNumber = 0;

                break;

            case 'partial':
                Log::debug('Range is partial.');
                $this->dateNotAfter            = '';
                $this->dateNotBefore           = self::calcDateNotBefore($this->dateRangeUnit, $this->dateRangeNumber);
                Log::debug(sprintf('dateNotBefore is now "%s"', $this->dateNotBefore));
                if ('' === $this->dateRangeNotAfterUnit) {
                    Log::debug('dateRangeNotAfterUnit is "", dateNotAfter will be empty.');
                    $this->dateNotAfter = '';
                    Log::debug(sprintf('dateNotAfter is now "%s"', $this->dateNotAfter));
                }
                if ('' !== $this->dateRangeNotAfterUnit && $this->dateRangeNotAfterNumber > 0) {
                    Log::debug(sprintf('dateRangeNotAfterUnit is "%s", count is %d, dateNotAfter will be calculated.', $this->dateRangeNotAfterUnit, $this->dateRangeNotAfterNumber));
                    $this->dateNotAfter = self::calcDateNotBefore($this->dateRangeNotAfterUnit, $this->dateRangeNotAfterNumber);
                    Log::debug(sprintf('dateNotAfter is now "%s"', $this->dateNotAfter));
                }

                break;

            case 'range':
                Log::debug('Range is "range", both will be created from a string.');
                $before                        = trim($this->dateNotBefore); // string
                $after                         = trim($this->dateNotAfter);  // string
                if ('' !== $before) {
                    $before = Carbon::createFromFormat('Y-m-d', $before);
                }
                if ('' !== $after) {
                    $after = Carbon::createFromFormat('Y-m-d', $after);
                }

                if ('' !== $before && '' !== $after && $before > $after) {
                    [$before, $after] = [$after, $before];
                }

                $this->dateNotBefore           = '' === $before ? '' : $before->format('Y-m-d');
                $this->dateNotAfter            = '' === $after ? '' : $after->format('Y-m-d');
                Log::debug(sprintf('dateNotBefore is now "%s", dateNotAfter is "%s"', $this->dateNotBefore, $this->dateNotAfter));
        }
        // sanity check right away.
        if ('' !== $this->dateNotBefore && '' !== $this->dateNotAfter) {
            $notBefore = Carbon::createFromFormat('Y-m-d', $this->dateNotBefore);
            $notAfter  = Carbon::createFromFormat('Y-m-d', $this->dateNotAfter);
            if ($notAfter->lt($notBefore)) {
                throw new ImporterErrorException(sprintf('The date range in your configuration is invalid. The "not before" date (%s) is after the "not after" date (%s). You must correct this manually.', $this->dateNotBefore, $this->dateNotAfter));
            }
        }
    }

    private static function calcDateNotBefore(string $unit, int $number): ?string
    {
        $functions = [
            'd' => 'subDays',
            'w' => 'subWeeks',
            'm' => 'subMonths',
            'y' => 'subYears',
        ];
        if (isset($functions[$unit])) {
            $today    = Carbon::now();
            $function = $functions[$unit];
            $today->{$function}($number);

            return $today->format('Y-m-d');
        }
        Log::error(sprintf('Could not parse date setting. Unknown key "%s"', $unit));

        return null;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getDateRangeNotAfterNumber(): int
    {
        return $this->dateRangeNotAfterNumber;
    }

    public function getDateRangeNotAfterUnit(): string
    {
        return $this->dateRangeNotAfterUnit;
    }

    public function getLunchFlowApiKey(): string
    {
        return $this->lunchFlowApiKey;
    }

    public function setDuplicateDetectionMethod(string $duplicateDetectionMethod): void
    {
        $this->duplicateDetectionMethod = $duplicateDetectionMethod;
    }

    public function setHeaders(bool $headers): void
    {
        $this->headers = $headers;
    }

    public function updateFromRequest(array $request): void
    {
        $this->headers                     = $request['headers'];
        $this->delimiter                   = $request['delimiter'];
        $this->date                        = $request['date'];
        $this->defaultAccount              = $request['default_account'];
        $this->rules                       = $request['rules'];
        $this->ignoreDuplicateLines        = $request['ignore_duplicate_lines'];
        $this->ignoreDuplicateTransactions = $request['ignore_duplicate_transactions'];
        $this->skipForm                    = $request['skip_form'];
        $this->addImportTag                = $request['add_import_tag'];
        $this->pendingTransactions         = $request['pending_transactions'];
        $this->customTag                   = $request['custom_tag'];
        $this->duplicateDetectionMethod    = $request['duplicate_detection_method'];
        $this->ignoreSpectreCategories     = $request['ignore_spectre_categories'];
        $this->mapAllData                  = $request['map_all_data'];
        $this->dateRange                   = $request['date_range'];
        $this->dateRangeNumber             = $request['date_range_number'];
        $this->dateRangeUnit               = $request['date_range_unit'];
        $this->dateRangeNotAfterNumber     = $request['date_range_not_after_number'];
        $this->dateRangeNotAfterUnit       = $request['date_range_not_after_unit'];
        $this->dateNotBefore               = (string)$request['date_not_before'];
        $this->dateNotAfter                = (string)$request['date_not_after'];
        $this->conversion                  = $request['conversion'];
        $this->groupedTransactionHandling  = $request['grouped_transaction_handling'];
        $this->useEntireOpposingAddress    = $request['use_entire_opposing_address'];
        $this->newAccounts                 = $request['to_create'];
        $this->accounts                    = $request['to_import_from'];


        // config for "cell":
        $this->uniqueColumnIndex           = $request['unique_column_index'] ?? 0;
        $this->uniqueColumnType            = $request['unique_column_type'] ?? '';

        // config for pseudo identifier (composite identifiers):
        $this->pseudoIdentifier            = $request['pseudo_identifier'] ?? [];

        // Migrate old single-column identifier to pseudo identifier format
        $this->migrateSingleIdentifierToPseudoIdentifier();
        $this->updateDateRange();
    }
}
