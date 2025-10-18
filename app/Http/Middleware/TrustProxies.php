<?php

/*
 * TrustProxies.php
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

namespace App\Http\Middleware;

use Illuminate\Config\Repository;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class TrustProxies
 */
class TrustProxies extends Middleware
{
    /**
     * The headers that should be used to detect proxies.
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX
        | Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * The trusted proxies for this application.
     */
    protected $proxies;

    /**
     * TrustProxies constructor.
     */
    public function __construct(Repository $config) // @phpstan-ignore-line
    {
        $trustedProxies = (string) config('trustedproxy.proxies');
        $this->proxies  = explode(',', $trustedProxies);
        if ('**' === $trustedProxies) {
            $this->proxies = '**';
        }
        if ('*' === $trustedProxies) {
            $this->proxies = '*';
        }
    }
}
