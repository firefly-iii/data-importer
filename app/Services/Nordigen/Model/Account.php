<?php

/*
 * Account.php
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

namespace App\Services\Nordigen\Model;

/**
 * Class Account
 */
class Account
{
    private array  $balances = [];
    private string $bban;
    private string $bic;
    private string $cashAccountType;
    private string $currency;
    private string $details;
    private string $displayName;
    private string $iban;
    private string $identifier;
    private string $linkedAccounts;
    private string $msisdn;
    private string $name;
    private array  $ownerAddressUnstructured;
    private string $ownerName;
    private string $product;
    private string $resourceId;
    private string $status;
    private string $usage;

    /**
     * Make sure all fields have an (empty) value
     */
    public function __construct()
    {
        $this->identifier               = '';
        $this->bban                     = '';
        $this->bic                      = '';
        $this->cashAccountType          = '';
        $this->currency                 = '';
        $this->details                  = '';
        $this->displayName              = '';
        $this->iban                     = '';
        $this->linkedAccounts           = '';
        $this->msisdn                   = '';
        $this->name                     = '';
        $this->ownerAddressUnstructured = [];
        $this->ownerName                = '';
        $this->product                  = '';
        $this->resourceId               = '';
        $this->status                   = '';
        $this->usage                    = '';
        $this->balances                 = [];
    }

    public static function createFromIdentifier(string $identifier): self
    {
        $self = new self();
        $self->setIdentifier($identifier);

        return $self;
    }

    /**
     * @return static
     */
    public static function fromLocalArray(array $array): self
    {
        $object                           = new self();
        $object->identifier               = $array['identifier'];
        $object->bban                     = $array['bban'];
        $object->bic                      = $array['bic'];
        $object->cashAccountType          = $array['cash_account_type'];
        $object->currency                 = $array['currency'];
        $object->details                  = $array['details'];
        $object->displayName              = $array['display_name'];
        $object->iban                     = $array['iban'];
        $object->linkedAccounts           = $array['linked_accounts'];
        $object->msisdn                   = $array['msisdn'];
        $object->name                     = $array['name'];
        $object->ownerAddressUnstructured = $array['owner_address_unstructured'];
        $object->ownerName                = $array['owner_name'];
        $object->product                  = $array['product'];
        $object->resourceId               = $array['resource_id'];
        $object->status                   = $array['status'];
        $object->usage                    = $array['usage'];
        $object->balances                 = [];
        foreach ($array['balances'] as $arr) {
            $object->balances[] = Balance::fromLocalArray($arr);
        }

        return $object;
    }

    public function addBalance(Balance $balance): void
    {
        $this->balances[] = $balance;
    }

    public function getBalances(): array
    {
        return $this->balances;
    }

    public function getBban(): string
    {
        return $this->bban;
    }

    public function setBban(string $bban): void
    {
        $this->bban = $bban;
    }

    public function getBic(): string
    {
        return $this->bic;
    }

    public function setBic(string $bic): void
    {
        $this->bic = $bic;
    }

    public function getCashAccountType(): string
    {
        return $this->cashAccountType;
    }

    public function setCashAccountType(string $cashAccountType): void
    {
        $this->cashAccountType = $cashAccountType;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function setDetails(string $details): void
    {
        $this->details = $details;
    }

    public function getFullName(): string
    {
        app('log')->debug('Account::getFullName()');
        if ('' !== $this->getName()) {
            app('log')->debug(sprintf('Return getName(): "%s"', $this->getName()));

            return $this->getName();
        }
        if ('' !== $this->getDisplayName()) {
            app('log')->debug(sprintf('Return getDisplayName(): "%s"', $this->getDisplayName()));

            return $this->getDisplayName();
        }
        if ('' !== $this->getOwnerName()) {
            app('log')->debug(sprintf('Return getOwnerName(): "%s"', $this->getOwnerName()));

            return $this->getOwnerName();
        }
        if ('' !== $this->getIban()) {
            app('log')->debug(sprintf('Return getIban(): "%s"', $this->getIban()));

            return $this->getIban();
        }
        app('log')->warning('Account::getFullName(): no field with name, return "(no name)"');

        return '(no name)';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getOwnerName(): string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): void
    {
        $this->ownerName = $ownerName;
    }

    public function getIban(): string
    {
        return $this->iban;
    }

    public function setIban(string $iban): void
    {
        $this->iban = $iban;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getLinkedAccounts(): string
    {
        return $this->linkedAccounts;
    }

    public function setLinkedAccounts(string $linkedAccounts): void
    {
        $this->linkedAccounts = $linkedAccounts;
    }

    public function getMsisdn(): string
    {
        return $this->msisdn;
    }

    public function setMsisdn(string $msisdn): void
    {
        $this->msisdn = $msisdn;
    }

    public function getOwnerAddressUnstructured(): array
    {
        return $this->ownerAddressUnstructured;
    }

    public function setOwnerAddressUnstructured(array $ownerAddressUnstructured): void
    {
        $this->ownerAddressUnstructured = $ownerAddressUnstructured;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function setProduct(string $product): void
    {
        $this->product = $product;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function setResourceId(string $resourceId): void
    {
        $this->resourceId = $resourceId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getUsage(): string
    {
        return $this->usage;
    }

    public function setUsage(string $usage): void
    {
        $this->usage = $usage;
    }

    public function toLocalArray(): array
    {
        $array = [
            'identifier'                 => $this->identifier,
            'bban'                       => $this->bban,
            'bic'                        => $this->bic,
            'cash_account_type'          => $this->cashAccountType,
            'currency'                   => $this->currency,
            'details'                    => $this->details,
            'display_name'               => $this->displayName,
            'iban'                       => $this->iban,
            'linked_accounts'            => $this->linkedAccounts,
            'msisdn'                     => $this->msisdn,
            'name'                       => $this->name,
            'owner_address_unstructured' => $this->ownerAddressUnstructured,
            'owner_name'                 => $this->ownerName,
            'product'                    => $this->product,
            'resource_id'                => $this->resourceId,
            'status'                     => $this->status,
            'usage'                      => $this->usage,
            'balances'                   => [],
        ];

        /** @var Balance $balance */
        foreach ($this->balances as $balance) {
            $array['balances'][] = $balance->toLocalArray();
        }

        return $array;
    }
}
