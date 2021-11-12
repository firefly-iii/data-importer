<?php
declare(strict_types=1);
/**
 * Configuration.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III CSV importer
 * (https://github.com/firefly-iii/csv-importer).
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

namespace App\Services\CSV\Configuration;

use App\Services\CSV\Specifics\SpecificService;
use Log;
use UnexpectedValueException;

/**
 * Class Configuration
 */
class Configuration
{
    /** @var int */
    public const VERSION = 2;
    private string $date;
    private int    $defaultAccount;
    private string $delimiter;
    private bool   $headers;
    private bool   $rules;
    private bool   $skipForm;
    private array  $specifics;
    private array  $roles;
    private int    $version;
    private array  $doMapping;
    private bool   $addImportTag;

    // how to do double transaction detection?
    private array $mapping; // 'classic' or 'cell'

    // configuration for "classic" method:
    private string $duplicateDetectionMethod;
    private bool   $ignoreDuplicateTransactions;

    // configuration for "cell" method:
    private bool   $ignoreDuplicateLines;
    private int    $uniqueColumnIndex;
    private string $uniqueColumnType;

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

        // double transaction detection:
        $this->duplicateDetectionMethod = 'classic';

        // config for "classic":
        $this->ignoreDuplicateTransactions = true;
        $this->ignoreDuplicateLines        = true;

        // config for "cell":
        $this->uniqueColumnIndex = 0;
        $this->uniqueColumnType  = 'internal_reference';

        $this->version = self::VERSION;
    }

    /**
     * Create a standard empty configuration.
     *
     * @return static
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * @param array $array
     *
     * @return $this
     */
    public static function fromRequest(array $array): self
    {
        $delimiters             = config('csv_importer.delimiters_reversed');
        $object                 = new self;
        $object->version        = self::VERSION;
        $object->headers        = $array['headers'];
        $object->date           = $array['date'];
        $object->defaultAccount = $array['default_account'];
        $object->delimiter      = $delimiters[$array['delimiter']] ?? 'comma';
        $object->rules          = $array['rules'];
        $object->skipForm       = $array['skip_form'];
        $object->addImportTag   = $array['add_import_tag'] ?? true;
        $object->roles          = $array['roles'] ?? [];
        $object->mapping        = $array['mapping'] ?? [];
        $object->doMapping      = $array['do_mapping'] ?? [];

        // duplicate transaction detection
        $object->duplicateDetectionMethod = $array['duplicate_detection_method'] ?? 'classic';

        // config for "classic":
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'];
        $object->ignoreDuplicateTransactions = true;

        // config for "cell":
        $object->uniqueColumnIndex = $array['unique_column_index'] ?? 0;
        $object->uniqueColumnType  = $array['unique_column_type'] ?? '';

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

        return $object;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public static function fromFile(array $data): self
    {
        Log::debug('Now in Configuration::fromFile. Data is omitted and will not be printed.');
        $version = $data['version'] ?? 1;
        if (1 === $version) {
            Log::debug('v1, going for classic.');

            return self::fromClassicFile($data);
        }
        if (2 === $version) {
            Log::debug('v2 config file!');

            return self::fromVersionTwo($data);
        }
        throw new UnexpectedValueException(sprintf('Configuration file version "%s" cannot be parsed.', $version));
    }

    /**
     * @param array $data
     *
     * @return static
     */
    private static function fromClassicFile(array $data): self
    {
        $delimiters             = config('csv_importer.delimiters_reversed');
        $classicRoleNames       = config('csv_importer.classic_roles');
        $object                 = new self;
        $object->headers        = $data['has-headers'] ?? false;
        $object->date           = $data['date-format'] ?? $object->date;
        $object->delimiter      = $delimiters[$data['delimiter']] ?? 'comma';
        $object->defaultAccount = $data['import-account'] ?? $object->defaultAccount;
        $object->rules          = $data['apply-rules'] ?? true;

        $object->ignoreDuplicateTransactions = $data['ignore_duplicate_transactions'] ?? true;

        if (isset($data['ignore_duplicates']) && true === $data['ignore_duplicates']) {
            Log::debug('Will ignore duplicates.');
            $object->ignoreDuplicateTransactions = true;
            $object->duplicateDetectionMethod    = 'classic';
        }

        if (isset($data['ignore_duplicates']) && false === $data['ignore_duplicates']) {
            Log::debug('Will NOT ignore duplicates.');
            $object->ignoreDuplicateTransactions = false;
            $object->duplicateDetectionMethod    = 'none';
        }

        if (isset($data['ignore_lines']) && true === $data['ignore_lines']) {
            Log::debug('Will ignore duplicate lines.');
            $object->ignoreDuplicateLines = true;
        }

        // array values
        $object->specifics = [];
        $object->roles     = [];
        $object->doMapping = [];
        $object->mapping   = [];

        // loop specifics from classic file:
        // Fix as suggested by @FelikZ in https://github.com/firefly-iii/csv-importer/pull/4
        $specifics = array_keys($data['specifics'] ?? []);

        foreach ($specifics as $name) {
            $class = SpecificService::fullClass($name);
            if (class_exists($class)) {
                $object->specifics[] = $name;
            }
        }

        // loop roles from classic file:
        $roles = $data['column-roles'] ?? [];
        foreach ($roles as $role) {
            // some roles have been given a new name some time in the past.
            $role = $classicRoleNames[$role] ?? $role;

            $config = config(sprintf('csv_importer.import_roles.%s', $role));
            if (null !== $config) {
                $object->roles[] = $role;
            }
        }

        // loop do mapping from classic file.
        $doMapping = $data['column-do-mapping'] ?? [];
        foreach ($doMapping as $index => $map) {
            $index                     = (int) $index;
            $object->doMapping[$index] = $map;
        }

        // loop mapping from classic file.
        $mapping = $data['column-mapping-config'] ?? [];
        foreach ($mapping as $index => $map) {
            $index                   = (int) $index;
            $object->mapping[$index] = $map;
        }
        // set version to "2" and return.
        $object->version = 2;

        return $object;
    }

    /**
     * @param array $data
     *
     * @return static
     */
    private static function fromVersionTwo(array $data): self
    {
        return self::fromArray($data);
    }

    /**
     * @param array $array
     *
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $version                = $array['version'] ?? 1;
        $delimiters             = config('csv_importer.delimiters_reversed');
        $object                 = new self;
        $object->headers        = $array['headers'];
        $object->date           = $array['date'];
        $object->defaultAccount = $array['default_account'];
        $object->delimiter      = $delimiters[$array['delimiter']] ?? 'comma';
        $object->rules          = $array['rules'];
        $object->skipForm       = $array['skip_form'];
        $object->addImportTag   = $array['add_import_tag'] ?? true;
        $object->specifics      = $array['specifics'];
        $object->roles          = $array['roles'] ?? [];
        $object->mapping        = $array['mapping'] ?? [];
        $object->doMapping      = $array['do_mapping'] ?? [];
        $object->version        = $version;

        // duplicate transaction detection
        $object->duplicateDetectionMethod = $array['duplicate_detection_method'] ?? 'classic';

        // config for "classic":
        $object->ignoreDuplicateLines        = $array['ignore_duplicate_lines'];
        $object->ignoreDuplicateTransactions = $array['ignore_duplicate_transactions'];

        if (!array_key_exists('duplicate_detection_method', $array)) {
            if (false === $object->ignoreDuplicateTransactions) {
                Log::debug('Set the duplicate method to "none".');
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

        $firstValue = count(array_values($array['specifics'])) > 0 ? array_values($array['specifics'])[0] : null;
        $firstKey   = count(array_values($array['specifics'])) > 0 ? array_keys($array['specifics'])[0] : null;

        // due to a bug, the "specifics" array could still be broken at this point.
        // do a quick check and verification.
        if (is_bool($firstValue) && is_string($firstKey)) {
            $actualSpecifics = [];
            foreach ($array['specifics'] as $key => $value) {
                if (true === $value) {
                    $actualSpecifics[] = $key;
                }
            }
            $object->specifics = $actualSpecifics;
        }

        //Log::debug(var_export($object->ignoreDuplicateLines, true));
        //Log::debug(var_export($object->ignoreDuplicateTransactions, true));

        return $object;
    }

    /**
     * @return bool
     */
    public function isSkipForm(): bool
    {
        return $this->skipForm;
    }

    /**
     * @param string $name
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
     * @return array
     */
    public function getRoles(): array
    {
        return $this->roles ?? [];
    }

    /**
     * @param array $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
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
     * Return the array but drop some potentially massive arrays.
     * @return array
     */
    public function toSessionArray(): array
    {
        $array = $this->toArray();
        unset($array['mapping'], $array['do_mapping'], $array['roles']);
        return $array;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $array = [
            'date'                          => $this->date,
            'default_account'               => $this->defaultAccount,
            'delimiter'                     => $this->delimiter,
            'headers'                       => $this->headers,
            'rules'                         => $this->rules,
            'skip_form'                     => $this->skipForm,
            'add_import_tag'                => $this->addImportTag,
            'specifics'                     => $this->specifics,
            'roles'                         => $this->roles,
            'do_mapping'                    => $this->doMapping,
            'mapping'                       => $this->mapping,
            'duplicate_detection_method'    => $this->duplicateDetectionMethod,
            'ignore_duplicate_lines'        => $this->ignoreDuplicateLines,
            'ignore_duplicate_transactions' => $this->ignoreDuplicateTransactions,
            'unique_column_index'           => $this->uniqueColumnIndex,
            'unique_column_type'            => $this->uniqueColumnType,
            'version'                       => $this->version,
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
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * @return int
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
     * @return bool
     */
    public function isHeaders(): bool
    {
        return $this->headers;
    }

    /**
     * @return bool
     */
    public function isRules(): bool
    {
        return $this->rules;
    }

    /**
     * @return array
     */
    public function getDoMapping(): array
    {
        return $this->doMapping ?? [];
    }

    /**
     * @param array $doMapping
     */
    public function setDoMapping(array $doMapping): void
    {
        $this->doMapping = $doMapping;
    }

    /**
     * @return array
     */
    public function getMapping(): array
    {
        return $this->mapping ?? [];
    }

    /**
     * @param array $mapping
     */
    public function setMapping(array $mapping): void
    {
        $newMap = [];
        foreach($mapping as $column => $map) {
            ksort($map);
            $newMap[$column] = $map;
        }
        $this->mapping = $newMap;
    }

    /**
     * @return array
     */
    public function getSpecifics(): array
    {
        return $this->specifics;
    }

    /**
     * @return string
     */
    public function getDuplicateDetectionMethod(): string
    {
        return $this->duplicateDetectionMethod;
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


}
