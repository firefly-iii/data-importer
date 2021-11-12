<?php
declare(strict_types=1);


/**
 * TrustProxies.php
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

namespace App\Http\Middleware;

use Fideloper\Proxy\TrustProxies as Middleware;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;

/**
 * Class TrustProxies
 */
class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array|string
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;

    /**
     * TrustProxies constructor.
     *
     * @param Repository $config
     */
    public function __construct(Repository $config)
    {
        $trustedProxies = (string) config('csv_importer.trusted_proxies');
        $this->proxies  = explode(',', $trustedProxies);
        if ('**' === $trustedProxies) {
            $this->proxies = '**';
        }
        parent::__construct($config);
    }
}
