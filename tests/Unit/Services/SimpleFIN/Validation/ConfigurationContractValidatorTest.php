<?php

/*
 * ConfigurationContractValidatorTest.php
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

namespace Tests\Unit\Services\SimpleFIN\Validation;

use Carbon\Carbon;
use App\Services\Shared\Configuration\Configuration;
use App\Services\SimpleFIN\Validation\ConfigurationContractValidator;
use App\Services\SimpleFIN\Validation\ValidationResult;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;
use Override;

/**
 * Class ConfigurationContractValidatorTest
 *
 * @internal
 *
 * @coversNothing
 */
final class ConfigurationContractValidatorTest extends TestCase
{
    use WithFaker;

    private ConfigurationContractValidator $validator;
    private Configuration $mockConfiguration;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->validator         = new ConfigurationContractValidator();

        // Create a basic valid configuration
        $this->mockConfiguration = $this->createMockConfiguration();
    }

    #[Override]
    protected function tearDown(): void
    {
        Session::flush();
        parent::tearDown();
    }

    /**
     * Test successful validation with complete valid configuration
     */
    public function testValidateConfigurationContractSuccess(): void
    {
        $this->setupValidSessionData();

        $result = $this->validator->validateConfigurationContract($this->mockConfiguration);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    /**
     * Test validation failure with invalid flow
     */
    public function testValidateConfigurationContractInvalidFlow(): void
    {
        $invalidConfig = $this->createMockConfiguration('nordigen');

        $result        = $this->validator->validateConfigurationContract($invalidConfig);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
        $this->assertStringContainsString('SimpleFIN flow', $result->getErrorMessages()[0]);
    }

    /**
     * Test validation failure with missing session data
     */
    public function testValidateConfigurationContractMissingSessionData(): void
    {
        // Don't set up session data

        $result = $this->validator->validateConfigurationContract($this->mockConfiguration);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('SimpleFIN accounts data missing', $result->getErrorMessages()[0]);
    }

    /**
     * Test validation failure with invalid SimpleFIN account structure
     */
    public function testValidateConfigurationContractInvalidAccountStructure(): void
    {
        Session::put('simplefin_accounts_data', [
            [
                'id'   => 'acc1',
                'name' => 'Test Account',
                // Missing required fields: currency, balance, balance-date, org
            ],
        ]);

        $result = $this->validator->validateConfigurationContract($this->mockConfiguration);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThanOrEqual(4, count($result->getErrors())); // Missing 4 required fields
    }

    /**
     * Test validation failure with invalid account mappings
     */
    public function testValidateConfigurationContractInvalidAccountMappings(): void
    {
        $this->setupValidSessionData();

        // Create configuration with invalid account mappings
        $config = $this->createMockConfiguration();
        $config->setAccounts([
            'acc1' => -1, // Invalid negative ID
            ''     => 0,     // Invalid empty account ID
        ]);

        $result = $this->validator->validateConfigurationContract($config);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    /**
     * Test validation failure with missing new account configuration
     */
    public function testValidateConfigurationContractMissingNewAccountConfig(): void
    {
        $this->setupValidSessionData();

        // Create configuration with account marked for creation but no new account config
        $config = $this->createMockConfiguration();
        $config->setAccounts(['acc1' => 0]); // Mark for creation
        $config->setNewAccounts([]); // But no configuration

        $result = $this->validator->validateConfigurationContract($config);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('New account configuration missing', $result->getErrorMessages()[0]);
    }

    /**
     * Test validation failure with invalid new account configuration
     */
    public function testValidateConfigurationContractInvalidNewAccountConfig(): void
    {
        $this->setupValidSessionData();

        $config = $this->createMockConfiguration();
        $config->setAccounts(['acc1' => 0]);
        $config->setNewAccounts([
            'acc1' => [
                'name'            => '',           // Empty name
                'type'            => 'invalid',    // Invalid type
                'currency'        => 'USDD',   // Invalid currency format
                'opening_balance' => 'not_numeric', // Invalid balance
            ],
        ]);

        $result = $this->validator->validateConfigurationContract($config);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThanOrEqual(4, count($result->getErrors()));
    }

    /**
     * Test validation of liability account requirements
     */
    public function testValidateConfigurationContractLiabilityAccountRequirements(): void
    {
        $this->setupValidSessionData();

        $config = $this->createMockConfiguration();
        $config->setAccounts(['acc1' => 0]);
        $config->setNewAccounts([
            'acc1' => [
                'name'            => 'Credit Card',
                'type'            => 'liability',
                'currency'        => 'USD',
                'opening_balance' => '1000.00',
                // Missing liability_type and liability_direction
            ],
        ]);

        $result = $this->validator->validateConfigurationContract($config);

        $this->assertFalse($result->isValid());
        $errors = $result->getErrors();
        $this->assertTrue(collect($errors)->contains(fn ($error) => str_contains((string) $error['message'], 'Liability type required')));
        $this->assertTrue(collect($errors)->contains(fn ($error) => str_contains((string) $error['message'], 'Liability direction required')));
    }

    /**
     * Test form field structure validation success
     */
    public function testValidateFormFieldStructureSuccess(): void
    {
        $validFormData = [
            'do_import'   => ['acc1' => '1'],
            'accounts'    => ['acc1' => 0],
            'new_account' => [
                'acc1' => [
                    'name'            => 'Test Account',
                    'type'            => 'asset',
                    'currency'        => 'USD',
                    'opening_balance' => '1000.00',
                ],
            ],
        ];

        $result        = $this->validator->validateFormFieldStructure($validFormData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    /**
     * Test form field structure validation failure
     */
    public function testValidateFormFieldStructureFailure(): void
    {
        $invalidFormData = [
            'do_import' => 'not_array', // Should be array
            // Missing 'accounts' and 'new_account'
        ];

        $result          = $this->validator->validateFormFieldStructure($invalidFormData);

        $this->assertFalse($result->isValid());
        $this->assertGreaterThanOrEqual(3, count($result->getErrors())); // Missing/invalid fields
    }

    /**
     * Test ValidationResult class functionality
     */
    public function testValidationResultClass(): void
    {
        $errors        = [
            ['field' => 'test', 'message' => 'Test error', 'value' => null],
        ];
        $warnings      = [
            ['field' => 'test', 'message' => 'Test warning', 'value' => null],
        ];

        // Test invalid result
        $invalidResult = new ValidationResult(false, $errors, $warnings);
        $this->assertFalse($invalidResult->isValid());
        $this->assertTrue($invalidResult->hasErrors());
        $this->assertTrue($invalidResult->hasWarnings());
        $this->assertSame(['Test error'], $invalidResult->getErrorMessages());
        $this->assertSame(['Test warning'], $invalidResult->getWarningMessages());

        // Test valid result
        $validResult   = new ValidationResult(true);
        $this->assertTrue($validResult->isValid());
        $this->assertFalse($validResult->hasErrors());
        $this->assertFalse($validResult->hasWarnings());
        $this->assertEmpty($validResult->getErrors());
        $this->assertEmpty($validResult->getWarnings());

        // Test toArray
        $array         = $invalidResult->toArray();
        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertFalse($array['valid']);
    }

    /**
     * Test asset account role validation
     */
    public function testAssetAccountRoleValidation(): void
    {
        $this->setupValidSessionData();

        $config = $this->createMockConfiguration();
        $config->setAccounts(['acc1' => 0]);
        $config->setNewAccounts([
            'acc1' => [
                'name'            => 'Savings Account',
                'type'            => 'asset',
                'currency'        => 'USD',
                'opening_balance' => '1000.00',
                'account_role'    => 'invalidRole', // Invalid role
            ],
        ]);

        $result = $this->validator->validateConfigurationContract($config);

        $this->assertFalse($result->isValid());
        $this->assertTrue(collect($result->getErrors())->contains(fn ($error) => str_contains((string) $error['message'], 'Invalid account role')));
    }

    /**
     * Test import selection validation
     */
    public function testImportSelectionValidation(): void
    {
        $this->setupValidSessionData();
        Session::put('do_import', ['nonexistent_acc' => '1']);

        $result = $this->validator->validateConfigurationContract($this->mockConfiguration);

        $this->assertFalse($result->isValid());
        $this->assertTrue(collect($result->getErrors())->contains(fn ($error) => str_contains((string) $error['message'], 'selected for import but not in account mappings')));
    }

    /**
     * Create a mock Configuration object for testing
     */
    private function createMockConfiguration(string $flow = 'simplefin'): Configuration
    {
        return Configuration::fromArray([
            'flow'        => $flow,
            'accounts'    => ['acc1' => 1, 'acc2' => 0],
            'new_account' => [
                'acc2' => [
                    'name'            => 'New Account',
                    'type'            => 'asset',
                    'currency'        => 'USD',
                    'opening_balance' => '1000.00',
                    'account_role'    => 'defaultAsset',
                ],
            ],
        ]);
    }

    /**
     * Set up valid session data for testing
     */
    private function setupValidSessionData(): void
    {
        Session::put('simplefin_accounts_data', [
            [
                'id'           => 'acc1',
                'name'         => 'Test Account 1',
                'currency'     => 'USD',
                'balance'      => '1000.00',
                'balance-date' => Carbon::now()->getTimestamp(),
                'org'          => ['name' => 'Test Bank'],
                'extra'        => [],
            ],
            [
                'id'           => 'acc2',
                'name'         => 'Test Account 2',
                'currency'     => 'USD',
                'balance'      => '2000.00',
                'balance-date' => Carbon::now()->getTimestamp(),
                'org'          => ['name' => 'Test Bank'],
                'extra'        => [],
            ],
        ]);

        Session::put('do_import', [
            'acc1' => '1',
            'acc2' => '1',
        ]);
    }
}
