<?php

/*
 * Configuration.php
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

namespace App\Services\Shared\Configuration;

use Carbon\Carbon;
use DateTimeInterface;
use UnexpectedValueException;

/**
 * Class Configuration
 */
class Configuration
{
    public const VERSION = 3;
    private array  $accounts;
    private bool   $addImportTag;
    private string $connection;
    private string $contentType;
    private bool   $conversion;
    private string $date;
    private string $dateNotAfter;
    private string $dateNotBefore;
    private string $dateRange;
    private int    $dateRangeNumber;
    private string $dateRangeUnit;
    private int    $defaultAccount;
    private string $customTag;

    // nordigen configuration
    private string $delimiter;
    private array  $doMapping;

    // flow and file type
    private string $duplicateDetectionMethod;
    private string $flow;

    // csv config
    private string $groupedTransactionHandling;

    // spectre + nordigen configuration
    private bool $headers;

    // spectre configuration
    private string $identifier;
    private bool   $ignoreDuplicateLines;
    private bool   $ignoreDuplicateTransactions;

    // camt configuration
    private bool $ignoreSpectreCategories;
    private bool $mapAllData;

    // date range settings
    private array  $mapping;
    private string $nordigenBank;
    private string $nordigenCountry;
    private string $nordigenMaxDays;
    private array  $nordigenRequisitions;

    // what type of import?
    private array $roles;

    // how to do double transaction detection?
    private bool $rules; // 'classic' or 'cell'

    // configuration for "classic" method:
    private bool  $skipForm;
    private array $specifics;

    // configuration for "cell" method:
    private int    $uniqueColumnIndex;
    private string $uniqueColumnType;
    private bool   $useEntireOpposingAddress;

    // configuration for utf-8
    private int $version;

    /**
     * Configuration constructor.
     */
    private function __construct()
    {
        $this->date           = 'Y-m-d';
        $this->defaultAccount = 1;
        $this->delimiter      = 'comma';
        $this->headers        = false;
        $this->rules          = true;
        $this->skipForm       = false;
        $this->addImportTag   = true;
        $this->specifics      = [];
        $this->roles          = [];
        $this->mapping        = [];
        $this->doMapping      = [];
        $this->flow           = 'file';
        $this->contentType    = 'csv';
        $this->customTag      = '';

        // date range settings
        $this->dateRange       = 'all';
        $this->dateRangeNumber = 30;
        $this->dateRangeUnit   = 'd';
        $this->dateNotBefore   = '';
        $this->dateNotAfter    = '';

        // camt settings
        $this->groupedTransactionHandling = 'single';
        $this->useEntireOpposingAddress   = false;

        // nordigen configuration
        $this->nordigenCountry      = '';
        $this->nordigenBank         = '';
        $this->nordigenRequisitions = [];
        $this->nordigenMaxDays      = '90';

        // spectre + nordigen configuration
        $this->accounts = [];

        // spectre
        $this->identifier              = '0';
        $this->connection              = '0';
        $this->ignoreSpectreCategories = false;

        // mapping for spectre + nordigen
        $this->mapAllData = false;

        // double transaction detection:
        $this->duplicateDetectionMethod = 'classic';

        // config for "classic":
        $this->ignoreDuplicateTransactions = true;
        $this->ignoreDuplicateLines        = true;

        // config for "cell":
        $this->uniqueColumnIndex = 0;
        $this->uniqueColumnType  = 'internal_reference';

        // utf8
        $this->conversion = false;

        $this->version = self::VERSION;
    }

    /**
     * @param  array  $array
     *
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $delimiters             = config('csv.delimiters_reversed');
        $object                 = new self();
        $object->headers        = $array['headers'] ?? false;
        $object->date           = $array['date'] ?? '';
        $object->defaultAccount = $array['default_account'] ?? 0;
        $object->delimiter      = $delimiters[$array['delimiter'] ?? ','] ?? 'comma';
        $object->rules          = $array['rules'] ?? true;
        $object->skipForm       = $array['skip_form'] ?? false;
        $object->addImportTag   = $array['add_import_tag'] ?? true;
        $object->roles          = $array['roles'] ?? [];
        $object->mapping        = $array['mapping'] ?? [];
        $object->doMapping      = $array['do_mapping'] ?? [];
        $object->version        = self::VERSION;
        $object->flow           = $array['flow'] ?? 'file';
        $object->contentType    = $array['content_type'] ?? 'csv';
        $object->customTag      = $array['custom_tag'] ?? '';

        // sort
        ksort($object->doMapping);
        ksort($object->mapping);
        ksort($object->roles);

        // settings for spectre + nordigen
        $object->mapAllData = $array['map_all_data'] ?? false;
        $object->accounts   = $array['accounts'] ?? [];


        // spectre
        $object->identifier              = $array['identifier'] ?? '0';
        $object->connection              = $array['connection'] ?? '0';
        $object->ignoreSpectreCategories = $array['ignore_spectre_categories'] ?? false;

        // date range settings
        $object->dateRange       = $array['date_range'] ?? 'all';
        $object->dateRangeNumber = $array['date_range_number'] ?? 30;
        $object->dateRangeUnit   = $array['date_range_unit'] ?? 'd';
        $object->dateNotBefore   = $array['date_not_before'] ?? '';
        $object->dateNotAfter    = $array['date_not_after'] ?? '';

        // camt
        $object->groupedTransactionHandling = $array['grouped_transaction_handling'] ?? 'single';
        $object->useEntireOpposingAddress   = $array['use_entire_opposing_address'] ?? false;

        // nordigen information:
        $object->nordigenCountry      = $array['nordigen_country'] ?? '';
        $object->nordigenBank         = $array['nordigen_bank'] ?? '';
        $object->nordigenRequisitions = $array['nordigen_requisitions'] ?? [];
        $object->nordigenMaxDays      = $array['nordigen_max_days'] ?? '90';

        // duplicate transaction detection
        $object->duplicateDetectionMethod = $array['duplicate_detection_method'] ?? 'classic';

        // config for "classic":
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'] ?? false;
        $object->ignoreDuplicateTransactions = $array['ignore_duplicate_transactions'] ?? true;

        if (!array_key_exists('duplicate_detection_method', $array)) {
            if (false === $object->ignoreDuplicateTransactions) {
                app('log')->debug('Set the duplicate method to "none".');
                $object->duplicateDetectionMethod = 'none';
            }
        }

        // overrule a setting:
        if ('none' === $object->duplicateDetectionMethod) {
            $object->ignoreDuplicateTransactions = false;
        }

        // config for "cell":
        $object->uniqueColumnIndex = $array['unique_column_index'] ?? 0;
        $object->uniqueColumnType  = $array['unique_column_type'] ?? '';

        // utf8
        $object->conversion = $array['conversion'] ?? false;

        if ('csv' === $object->flow) {
            $object->flow        = 'file';
            $object->contentType = 'csv';
        }

        return $object;
    }

    /**
     * @param  array  $data
     *
     * @return $this
     */
    public static function fromFile(array $data): self
    {
        app('log')->debug('Now in Configuration::fromFile. Data is omitted and will not be printed.');
        $version = $data['version'] ?? 1;
        if (1 === $version) {
            app('log')->debug('v1, going for classic.');

            return self::fromClassicFile($data);
        }
        if (2 === $version) {
            app('log')->debug('v2 config file!');

            return self::fromVersionTwo($data);
        }
        if (3 === $version) {
            app('log')->debug('v3 config file!');

            return self::fromVersionThree($data);
        }

        throw new UnexpectedValueException(sprintf('Configuration file version "%s" cannot be parsed.', $version));
    }

    /**
     * @param  array  $array
     *
     * @return $this
     */
    public static function fromRequest(array $array): self
    {
        $delimiters             = config('csv.delimiters_reversed');
        $object                 = new self();
        $object->version        = self::VERSION;
        $object->headers        = $array['headers'] ?? false;
        $object->date           = $array['date'];
        $object->defaultAccount = $array['default_account'];
        $object->delimiter      = $delimiters[$array['delimiter']] ?? 'comma';
        $object->rules          = $array['rules'];
        $object->skipForm       = $array['skip_form'];
        $object->addImportTag   = $array['add_import_tag'] ?? true;
        $object->roles          = $array['roles'] ?? [];
        $object->mapping        = $array['mapping'] ?? [];
        $object->doMapping      = $array['do_mapping'] ?? [];
        $object->contentType    = $array['content_type'] ?? 'csv';
        $object->customTag      = $array['custom_tag'] ?? '';

        // mapping for spectre + nordigen
        $object->mapAllData = $array['map_all_data'] ?? false;

        // spectre
        $object->identifier              = $array['identifier'] ?? '0';
        $object->connection              = $array['connection'] ?? '0';
        $object->ignoreSpectreCategories = $array['ignore_spectre_categories'] ?? false;

        // nordigen:
        $object->nordigenCountry      = $array['nordigen_country'] ?? '';
        $object->nordigenBank         = $array['nordigen_bank'] ?? '';
        $object->nordigenRequisitions = $array['nordigen_requisitions'] ?? [];
        $object->nordigenMaxDays      = $array['nordigen_max_days'] ?? '90';

        $object->groupedTransactionHandling = $array['grouped_transaction_handling'] ?? 'single';
        $object->useEntireOpposingAddress   = $array['use_entire_opposing_address'] ?? false;

        // spectre + nordigen
        $object->accounts = $array['accounts'] ?? [];

        // date range settings
        $object->dateRange       = $array['date_range'] ?? 'all';
        $object->dateRangeNumber = $array['date_range_number'] ?? 30;
        $object->dateRangeUnit   = $array['date_range_unit'] ?? 'd';

        // null or Carbon because fromRequest will give Carbon object.
        $object->dateNotBefore = null === $array['date_not_before'] ? '' : $array['date_not_before']->format('Y-m-d');
        $object->dateNotAfter  = null === $array['date_not_after'] ? '' : $array['date_not_after']->format('Y-m-d');

        // duplicate transaction detection
        $object->duplicateDetectionMethod = $array['duplicate_detection_method'] ?? 'classic';

        // config for "classic":
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'];
        $object->ignoreDuplicateTransactions = true;

        // config for "cell":
        $object->uniqueColumnIndex = $array['unique_column_index'] ?? 0;
        $object->uniqueColumnType  = $array['unique_column_type'] ?? '';

        // utf8 conversion
        $object->conversion = $array['conversion'] ?? false;

        // flow
        $object->flow = $array['flow'] ?? 'file';

        // overrule a setting:
        if ('none' === $object->duplicateDetectionMethod) {
            $object->ignoreDuplicateTransactions = false;
        }

        $object->specifics = [];
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
     *
     * @return Configuration
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * @param  string  $unit
     * @param  int  $number
     *
     * @return string|null
     */
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
            $today->$function($number);

            return $today->format('Y-m-d');
        }
        app('log')->error(sprintf('Could not parse date setting. Unknown key "%s"', $unit));

        return null;
    }

    /**
     * @param  array  $data
     *
     * @return static
     */
    private static function fromClassicFile(array $data): self
    {
        $delimiters             = config('csv.delimiters_reversed');
        $classicRoleNames       = config('csv.classic_roles');
        $object                 = new self();
        $object->headers        = $data['has-headers'] ?? false;
        $object->date           = $data['date-format'] ?? $object->date;
        $object->delimiter      = $delimiters[$data['delimiter']] ?? 'comma';
        $object->defaultAccount = $data['import-account'] ?? $object->defaultAccount;
        $object->rules          = $data['apply-rules'] ?? true;
        $object->flow           = $data['flow'] ?? 'file';
        $object->contentType    = $data['content_type'] ?? 'csv';
        $object->customTag      = $data['custom_tag'] ?? '';

        // camt settings
        $object->groupedTransactionHandling = $data['grouped_transaction_handling'] ?? 'single';
        $object->useEntireOpposingAddress   = $data['use_entire_opposing_address'] ?? false;

        // other settings (are not in v1 anyway)
        $object->dateRange       = $data['date_range'] ?? 'all';
        $object->dateRangeNumber = $data['date_range_number'] ?? 30;
        $object->dateRangeUnit   = $data['date_range_unit'] ?? 'd';
        $object->dateNotBefore   = $data['date_not_before'] ?? '';
        $object->dateNotAfter    = $data['date_not_after'] ?? '';

        // spectre settings (are not in v1 anyway)
        $object->identifier              = $data['identifier'] ?? '0';
        $object->connection              = $data['connection'] ?? '0';
        $object->ignoreSpectreCategories = $data['ignore_spectre_categories'] ?? false;

        // nordigen settings (are not in v1 anyway)
        $object->nordigenCountry      = $data['nordigen_country'] ?? '';
        $object->nordigenBank         = $data['nordigen_bank'] ?? '';
        $object->nordigenRequisitions = $data['nordigen_requisitions'] ?? [];
        $object->nordigenMaxDays      = $data['nordigen_max_days'] ?? '90';

        // settings for spectre + nordigen (are not in v1 anyway)
        $object->mapAllData = $data['map_all_data'] ?? false;
        $object->accounts   = $data['accounts'] ?? [];

        $object->ignoreDuplicateTransactions = $data['ignore_duplicate_transactions'] ?? true;

        if (isset($data['ignore_duplicates']) && true === $data['ignore_duplicates']) {
            app('log')->debug('Will ignore duplicates.');
            $object->ignoreDuplicateTransactions = true;
            $object->duplicateDetectionMethod    = 'classic';
        }

        if (isset($data['ignore_duplicates']) && false === $data['ignore_duplicates']) {
            app('log')->debug('Will NOT ignore duplicates.');
            $object->ignoreDuplicateTransactions = false;
            $object->duplicateDetectionMethod    = 'none';
        }

        if (isset($data['ignore_lines']) && true === $data['ignore_lines']) {
            app('log')->debug('Will ignore duplicate lines.');
            $object->ignoreDuplicateLines = true;
        }

        // array values
        $object->specifics = [];
        $object->roles     = [];
        $object->doMapping = [];
        $object->mapping   = [];
        $object->accounts  = [];

        // utf8
        $object->conversion = $data['conversion'] ?? false;


        // loop roles from classic file:
        $roles = $data['column-roles'] ?? [];
        foreach ($roles as $index => $role) {
            // some roles have been given a new name some time in the past.
            $role   = $classicRoleNames[$role] ?? $role;
            $config = config(sprintf('csv.import_roles.%s', $role));
            if (null !== $config) {
                $object->roles[$index] = $role;
            }
            if (null === $config) {
                app('log')->warn(sprintf('There is no config for "%s"!', $role));
            }
        }
        ksort($object->roles);

        // loop do mapping from classic file.
        $doMapping = $data['column-do-mapping'] ?? [];
        foreach ($doMapping as $index => $map) {
            $index                     = (int)$index;
            $object->doMapping[$index] = $map;
        }
        ksort($object->doMapping);

        // loop mapping from classic file.
        $mapping = $data['column-mapping-config'] ?? [];
        foreach ($mapping as $index => $map) {
            $index                   = (int)$index;
            $object->mapping[$index] = $map;
        }
        ksort($object->mapping);

        // set version to latest version and return.
        $object->version = self::VERSION;

        if ('csv' === $object->flow) {
            $object->flow = 'file';
        }

        return $object;
    }

    /**
     * @param  array  $data
     *
     * @return static
     */
    private static function fromVersionThree(array $data): self
    {
        $object            = self::fromArray($data);
        $object->specifics = [];

        return $object;
    }

    /**
     * @param  array  $data
     *
     * @return static
     */
    private static function fromVersionTwo(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * @param  string  $key
     * @param  string  $identifier
     */
    public function addRequisition(string $key, string $identifier)
    {
        $this->nordigenRequisitions[$key] = $identifier;
    }

    /**
     * @return array
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    /**
     * @param  array  $accounts
     */
    public function setAccounts(array $accounts): void
    {
        $this->accounts = $accounts;
    }

    /**
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * @param  string  $connection
     */
    public function setConnection(string $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * @return string
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * @param  string  $contentType
     */
    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getDateNotAfter(): string
    {
        return $this->dateNotAfter;
    }

    /**
     * @return string
     */
    public function getDateNotBefore(): string
    {
        return $this->dateNotBefore;
    }

    /**
     * @return string
     */
    public function getDateRange(): string
    {
        return $this->dateRange;
    }

    /**
     * @return int
     */
    public function getDateRangeNumber(): int
    {
        return $this->dateRangeNumber;
    }

    /**
     * @return string
     */
    public function getDateRangeUnit(): string
    {
        return $this->dateRangeUnit;
    }

    /**
     * @return int|null
     */
    public function getDefaultAccount(): ?int
    {
        return $this->defaultAccount;
    }

    /**
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * @return array
     */
    public function getDoMapping(): array
    {
        return $this->doMapping ?? [];
    }

    /**
     * @param  array  $doMapping
     */
    public function setDoMapping(array $doMapping): void
    {
        $this->doMapping = $doMapping;
    }

    /**
     * @return string
     */
    public function getDuplicateDetectionMethod(): string
    {
        return $this->duplicateDetectionMethod;
    }

    /**
     * @return string
     */
    public function getFlow(): string
    {
        return $this->flow;
    }

    /**
     * @param  string  $flow
     */
    public function setFlow(string $flow): void
    {
        $this->flow = $flow;
    }

    /**
     * @return string
     */
    public function getGroupedTransactionHandling(): string
    {
        return $this->groupedTransactionHandling;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param  string  $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    /**
     * @return array
     */
    public function getMapping(): array
    {
        return $this->mapping ?? [];
    }

    /**
     * @param  array  $mapping
     */
    public function setMapping(array $mapping): void
    {
        $newMap = [];
        foreach ($mapping as $column => $map) {
            ksort($map);
            $newMap[$column] = $map;
        }
        $this->mapping = $newMap;
    }

    /**
     * @return string
     */
    public function getNordigenBank(): string
    {
        return $this->nordigenBank;
    }

    /**
     * @param  string  $nordigenBank
     */
    public function setNordigenBank(string $nordigenBank): void
    {
        $this->nordigenBank = $nordigenBank;
    }

    /**
     * @return string
     */
    public function getNordigenCountry(): string
    {
        return $this->nordigenCountry;
    }

    /**
     * @param  string  $nordigenCountry
     */
    public function setNordigenCountry(string $nordigenCountry): void
    {
        $this->nordigenCountry = $nordigenCountry;
    }

    /**
     * @return string
     */
    public function getNordigenMaxDays(): string
    {
        return $this->nordigenMaxDays;
    }

    /**
     * @param  string  $nordigenMaxDays
     */
    public function setNordigenMaxDays(string $nordigenMaxDays): void
    {
        $this->nordigenMaxDays = $nordigenMaxDays;
    }

    /**
     * @return array
     */
    public function getNordigenRequisitions(): array
    {
        return $this->nordigenRequisitions;
    }

    /**
     * @param  string  $key
     *
     * @return string|null
     */
    public function getRequisition(string $key): ?string
    {
        return array_key_exists($key, $this->nordigenRequisitions) ? $this->nordigenRequisitions[$key] : null;
    }

    /**
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles ?? [];
    }

    /**
     * @param  array  $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * @return array
     */
    public function getSpecifics(): array
    {
        return $this->specifics;
    }

    /**
     * @return int
     */
    public function getUniqueColumnIndex(): int
    {
        return $this->uniqueColumnIndex;
    }

    /**
     * @return string
     */
    public function getUniqueColumnType(): string
    {
        return $this->uniqueColumnType;
    }

    /**
     * @param  string  $name
     *
     * @return bool
     */
    public function hasSpecific(string $name): bool
    {
        return in_array($name, $this->specifics, true);
    }

    /**
     * @return bool
     */
    public function isAddImportTag(): bool
    {
        return $this->addImportTag;
    }

    /**
     * @return bool
     */
    public function isConversion(): bool
    {
        return $this->conversion;
    }

    /**
     * @return bool
     */
    public function isHeaders(): bool
    {
        return $this->headers;
    }

    /**
     * @return bool
     */
    public function isIgnoreDuplicateLines(): bool
    {
        return $this->ignoreDuplicateLines;
    }

    /**
     * @return bool
     */
    public function isIgnoreDuplicateTransactions(): bool
    {
        return $this->ignoreDuplicateTransactions;
    }

    /**
     * @return bool
     */
    public function isIgnoreSpectreCategories(): bool
    {
        return $this->ignoreSpectreCategories;
    }

    /**
     * @return bool
     */
    public function isMapAllData(): bool
    {
        return $this->mapAllData;
    }

    /**
     * @return bool
     */
    public function isRules(): bool
    {
        return $this->rules;
    }

    /**
     * @return bool
     */
    public function isSkipForm(): bool
    {
        return $this->skipForm;
    }

    /**
     * @return bool
     */
    public function isUseEntireOpposingAddress(): bool
    {
        return $this->useEntireOpposingAddress;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $array = [
            'version'                      => $this->version,
            'source'                       => sprintf('fidi-%s', config('importer.version')),
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
            'flow'                         => $this->flow,
            'content_type'                 => $this->contentType,
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

            // settings for spectre + nordigen
            'accounts'                     => $this->accounts,

            // date range settings:
            'date_range'                   => $this->dateRange,
            'date_range_number'            => $this->dateRangeNumber,
            'date_range_unit'              => $this->dateRangeUnit,
            'date_not_before'              => $this->dateNotBefore,
            'date_not_after'               => $this->dateNotAfter,

            // nordigen information:
            'nordigen_country'             => $this->nordigenCountry,
            'nordigen_bank'                => $this->nordigenBank,
            'nordigen_requisitions'        => $this->nordigenRequisitions,
            'nordigen_max_days'            => $this->nordigenMaxDays,

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

    /**
     * Return the array but drop some potentially massive arrays.
     *
     * @return array
     */
    public function toSessionArray(): array
    {
        $array = $this->toArray();
        unset($array['mapping'], $array['do_mapping'], $array['roles']);

        return $array;
    }

    /**
     *
     */
    public function updateDateRange(): void
    {
        app('log')->debug('Now in updateDateRange()');
        // set date and time:
        switch ($this->dateRange) {
            default:
            case 'all':
                app('log')->debug('Range is null, set all to NULL.');
                $this->dateRangeUnit   = 'd';
                $this->dateRangeNumber = 30;
                $this->dateNotBefore   = '';
                $this->dateNotAfter    = '';
                break;
            case 'partial':
                app('log')->debug('Range is partial, after is NULL, dateNotBefore will be calculated.');
                $this->dateNotAfter  = '';
                $this->dateNotBefore = self::calcDateNotBefore($this->dateRangeUnit, $this->dateRangeNumber);
                app('log')->debug(sprintf('dateNotBefore is now "%s"', $this->dateNotBefore));
                break;
            case 'range':
                app('log')->debug('Range is "range", both will be created from a string.');
                $before = trim($this->dateNotBefore); // string
                $after  = trim($this->dateNotAfter);  // string
                if ('' !== $before) {
                    $before = Carbon::createFromFormat('Y-m-d', $before);
                }
                if ('' !== $after) {
                    $after = Carbon::createFromFormat('Y-m-d', $after);
                }

                if ('' !== $before && '' !== $after && $before > $after) {
                    [$before, $after] = [$after, $before];
                }

                $this->dateNotBefore = '' === $before ? '' : $before->format('Y-m-d');
                $this->dateNotAfter  = '' === $after ? '' : $after->format('Y-m-d');
                app('log')->debug(sprintf('dateNotBefore is now "%s", dateNotAfter is "%s"', $this->dateNotBefore, $this->dateNotAfter));
        }
    }

    /**
     * @return string
     */
    public function getCustomTag(): string
    {
        return $this->customTag;
    }


}
