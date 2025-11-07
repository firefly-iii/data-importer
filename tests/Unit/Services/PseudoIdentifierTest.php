<?php

/*
 * PseudoIdentifierTest.php
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

namespace Tests\Unit\Services;

use App\Services\Shared\Configuration\Configuration;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Services\Shared\Configuration\Configuration
 */
final class PseudoIdentifierTest extends TestCase
{
    /**
     * Returns base configuration array with common fields
     */
    private function getBaseConfiguration(): array
    {
        return [
            'version'                       => 3,
            'headers'                       => true,
            'delimiter'                     => 'comma',
            'date'                          => 'Y-m-d',
            'default_account'               => 1,
            'rules'                         => true,
            'ignore_duplicate_lines'        => true,
            'ignore_duplicate_transactions' => true,
            'roles'                         => [],
            'do_mapping'                    => [],
            'mapping'                       => [],
            'flow'                          => 'file',
            'content_type'                  => 'csv',
        ];
    }

    /**
     * Test backward compatibility: old single-column identifier migrates to pseudo identifier
     *
     * @covers ::migrateSingleIdentifierToPseudoIdentifier
     */
    public function testOldSingleColumnIdentifierMigration(): void
    {
        // Old configuration format (pre-v1.9.0)
        $oldConfig = [
            'version'                       => 3,
            'duplicate_detection_method'    => 'cell',
            'unique_column_index'           => 2,
            'unique_column_type'            => 'external-id',
            // No pseudo_identifier key (old format)
            'headers'                       => true,
            'delimiter'                     => 'comma',
            'date'                          => 'Y-m-d',
            'default_account'               => 1,
            'rules'                         => true,
            'ignore_duplicate_lines'        => true,
            'ignore_duplicate_transactions' => true,
            'roles'                         => [],
            'do_mapping'                    => [],
            'mapping'                       => [],
            'flow'                          => 'file',
            'content_type'                  => 'csv',
        ];

        $config    = Configuration::fromArray($oldConfig);

        // Should auto-migrate to pseudo identifier
        $this->assertTrue($config->hasPseudoIdentifier());

        $pseudo    = $config->getPseudoIdentifier();
        $this->assertNotNull($pseudo);
        $this->assertSame([2], $pseudo['source_columns']);
        $this->assertSame('|', $pseudo['separator']);
        $this->assertSame('external-id', $pseudo['role']);

        // Display value should show original index
        $this->assertSame('2', $config->getUniqueColumnIndexDisplay());
    }

    /**
     * Test new multi-column identifier format is preserved
     *
     * @covers ::getPseudoIdentifier
     * @covers ::hasPseudoIdentifier
     */
    public function testMultiColumnIdentifierPreserved(): void
    {
        $newConfig = [
            'version'                           => 3,
            'duplicate_detection_method'        => 'cell',
            'unique_column_index'               => 0,
            'unique_column_type'                => 'external-id',
            'pseudo_identifier'                 => [
                'source_columns' => [0, 3, 5],
                'separator'      => '|',
                'role'           => 'external-id',
            ],
            'headers'                           => true,
            'delimiter'                         => 'comma',
            'date'                              => 'Y-m-d',
            'default_account'                   => 1,
            'rules'                             => true,
            'ignore_duplicate_lines'            => true,
            'ignore_duplicate_transactions'     => true,
            'roles'                             => [],
            'do_mapping'                        => [],
            'mapping'                           => [],
            'flow'                              => 'file',
            'content_type'                      => 'csv',
        ];

        $config    = Configuration::fromArray($newConfig);

        $this->assertTrue($config->hasPseudoIdentifier());

        $pseudo    = $config->getPseudoIdentifier();
        $this->assertNotNull($pseudo);

        // Multi-column configuration should be preserved
        $this->assertSame([0, 3, 5], $pseudo['source_columns']);

        // Display value should show comma-separated indices
        $this->assertSame('0,3,5', $config->getUniqueColumnIndexDisplay());
    }

    /**
     * Test non-identifier-based detection doesn't create pseudo identifier
     *
     * @covers ::migrateSingleIdentifierToPseudoIdentifier
     */
    public function testClassicDetectionNoPseudoIdentifier(): void
    {
        $classicConfig = [
            'version'                       => 3,
            'duplicate_detection_method'    => 'classic',
            'unique_column_index'           => 0,
            'unique_column_type'            => '',
            'headers'                       => true,
            'delimiter'                     => 'comma',
            'date'                          => 'Y-m-d',
            'default_account'               => 1,
            'rules'                         => true,
            'ignore_duplicate_lines'        => true,
            'ignore_duplicate_transactions' => true,
            'roles'                         => [],
            'do_mapping'                    => [],
            'mapping'                       => [],
            'flow'                          => 'file',
            'content_type'                  => 'csv',
        ];

        $config        = Configuration::fromArray($classicConfig);

        // Should NOT create pseudo identifier for classic detection
        $this->assertFalse($config->hasPseudoIdentifier());
        $this->assertNull($config->getPseudoIdentifier());
    }

    /**
     * Test 'none' detection method doesn't create pseudo identifier
     *
     * @covers ::migrateSingleIdentifierToPseudoIdentifier
     */
    public function testNoneDetectionNoPseudoIdentifier(): void
    {
        $noneConfig = [
            'version'                       => 3,
            'duplicate_detection_method'    => 'none',
            'unique_column_index'           => 0,
            'unique_column_type'            => '',
            'headers'                       => true,
            'delimiter'                     => 'comma',
            'date'                          => 'Y-m-d',
            'default_account'               => 1,
            'rules'                         => true,
            'ignore_duplicate_lines'        => false,
            'ignore_duplicate_transactions' => false,
            'roles'                         => [],
            'do_mapping'                    => [],
            'mapping'                       => [],
            'flow'                          => 'file',
            'content_type'                  => 'csv',
        ];

        $config     = Configuration::fromArray($noneConfig);

        $this->assertFalse($config->hasPseudoIdentifier());
        $this->assertNull($config->getPseudoIdentifier());
    }

    /**
     * Test pseudo identifier configuration round-trip (save and load)
     *
     * @covers ::getPseudoIdentifier
     * @covers ::toArray
     */
    public function testPseudoIdentifierConfigurationRoundTrip(): void
    {
        $originalConfig = array_merge($this->getBaseConfiguration(), [
            'duplicate_detection_method'        => 'cell',
            'unique_column_index'               => 1,
            'unique_column_type'                => 'internal_reference',
            'pseudo_identifier'                 => [
                'source_columns' => [1, 4],
                'separator'      => '|',
                'role'           => 'internal_reference',
            ],
        ]);

        // Load configuration
        $config         = Configuration::fromArray($originalConfig);

        // Convert back to array
        $savedConfig    = $config->toArray();

        // Verify pseudo identifier is preserved in save
        $this->assertArrayHasKey('pseudo_identifier', $savedConfig);

        $pseudo         = $savedConfig['pseudo_identifier'];
        $this->assertSame([1, 4], $pseudo['source_columns']);
        $this->assertSame('|', $pseudo['separator']);
    }
}
