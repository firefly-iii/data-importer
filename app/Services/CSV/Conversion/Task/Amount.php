<?php
/*
 * Amount.php
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

namespace App\Services\CSV\Conversion\Task;

/**
 * Class Amount
 */
class Amount extends AbstractTask
{
    public function process(array $group): array
    {
        foreach ($group['transactions'] as $index => $transaction) {
            $group['transactions'][$index] = $this->processAmount($transaction);
        }

        return $group;
    }

    private function processAmount(array $transaction): array
    {
        app('log')->debug(sprintf('Now at the start of processAmount("%s")', $transaction['amount']));
        $amount                = null;
        if (null === $amount && $this->validAmount((string)$transaction['amount'])) {
            app('log')->debug('Transaction["amount"] value is not NULL, assume this is the correct value.');
            $amount = $transaction['amount'];
        }

        if (null === $amount && $this->validAmount((string)$transaction['amount_debit'])) {
            app('log')->debug(sprintf('Transaction["amount_debit"] value is not NULL ("%s"), assume this is the correct value.', $transaction['amount_debit']));
            $amount = $transaction['amount_debit'];
        }

        if (null === $amount && $this->validAmount((string)$transaction['amount_credit'])) {
            app('log')->debug(
                sprintf('Transaction["amount_credit"] value is not NULL ("%s"), assume this is the correct value.', $transaction['amount_credit'])
            );
            $amount = $transaction['amount_credit'];
        }

        if (null === $amount && $this->validAmount((string)$transaction['amount_negated'])) {
            app('log')->debug(
                sprintf('Transaction["amount_negated"] value is not NULL ("%s"), assume this is the correct value.', $transaction['amount_negated'])
            );
            $amount = $transaction['amount_negated'];
        }

        if (!array_key_exists('amount_modifier', $transaction)) {
            app('log')->debug('Missing default amount modifier: amount modifier is now "1".');
            $transaction['amount_modifier'] = '1';
        }
        if (array_key_exists('amount_modifier', $transaction)) {
            $transaction['amount_modifier'] = (string)$transaction['amount_modifier'];
            app('log')->debug(sprintf('Amount modifier is "%s".', $transaction['amount_modifier']));
        }
        if (array_key_exists('foreign_amount', $transaction)) {
            $transaction['foreign_amount'] = (string)$transaction['foreign_amount'];
        }
        $amount                = (string)$amount;
        if ('' === $amount) {
            app('log')->error('Amount is EMPTY. This will give problems further ahead.', $transaction);
            unset($transaction['amount_modifier']);

            return $transaction;
        }
        // modify amount:
        $amount                = bcmul($amount, $transaction['amount_modifier']);

        app('log')->debug(sprintf('Amount is now %s.', $amount));

        // modify foreign amount
        if (isset($transaction['foreign_amount'])) {
            $transaction['foreign_amount'] = bcmul($transaction['foreign_amount'], $transaction['amount_modifier']);
            app('log')->debug(sprintf('FOREIGN amount is now %s.', $transaction['foreign_amount']));
        }

        // unset those fields:
        unset($transaction['amount_credit'], $transaction['amount_debit'], $transaction['amount_negated'], $transaction['amount_modifier']);
        $transaction['amount'] = $amount;

        // depending on pos or min, also pre-set the expected type.
        if (1 === bccomp('0', $amount)) {
            app('log')->debug(sprintf('Amount %s is negative, so this is probably a withdrawal.', $amount));
            $transaction['type'] = 'withdrawal';
        }
        if (-1 === bccomp('0', $amount)) {
            app('log')->debug(sprintf('Amount %s is positive, so this is probably a deposit.', $amount));
            $transaction['type'] = 'deposit';
        }
        unset($transaction['amount_modifier']);

        return $transaction;
    }

    private function validAmount(string $amount): bool
    {
        if ('' === $amount) {
            return false;
        }
        if ('0' === $amount) {
            return false;
        }
        if (0 === bccomp('0', $amount)) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the task requires the default account.
     */
    public function requiresDefaultAccount(): bool
    {
        return false;
    }

    /**
     * Returns true if the task requires the default currency of the user.
     */
    public function requiresTransactionCurrency(): bool
    {
        return false;
    }
}
