<?php
declare(strict_types=1);
/**
 * AbstractTask.php
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

namespace App\Services\Import\Task;

use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionCurrency;

/**
 * Class AbstractTask
 */
abstract class AbstractTask implements TaskInterface
{
    /** @var Account */
    protected $account;

    /** @var TransactionCurrency */
    protected $transactionCurrency;

    /**
     * @param Account $account
     */
    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }

    /**
     * @param TransactionCurrency $transactionCurrency
     */
    public function setTransactionCurrency(TransactionCurrency $transactionCurrency): void
    {
        $this->transactionCurrency = $transactionCurrency;
    }


}
