<?php


/*
 * Connection.php
 * Copyright (c) 2026 james@firefly-iii.org
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

namespace App\Services\Akahu\Model\Account;

final class Connection
{
    // The name of the provider.
    private ?string $name           = null;

    // A URL pointing to an image of the provider's logo.
    private ?string $logo           = null;

    // The type of integration used to connect to this institution.
    // This will be one of:
    //  - classic: A classic Akahu connection, which uses Akahu's custom built integration
    //    to connect to the institution.
    //  - official: An official open banking connection, which uses the institution's
    //    official open banking APIs.
    private ?string $connectionType = null;

    /**
     * Parse a connection structure from an Akahu api json response
     */
    public static function fromJson(array $json): self
    {
        $connection                 = new self();

        $connection->name           = $json['name'] ?? null;
        $connection->logo           = $json['logo'] ?? null;
        $connection->connectionType = $json['connection_type'] ?? null;

        return $connection;
    }

    /**
     * Serialize a connection structure to store on disk
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'logo' => $this->logo, 'connectionType' => $this->connectionType];
    }

    /**
     * Deserialize a connection structure from disk
     */
    public static function fromArray(array $data): self
    {
        $connection                 = new self();

        $connection->name           = $data['name'] ?? null;
        $connection->logo           = $data['logo'] ?? null;
        $connection->connectionType = $data['connectionType'] ?? null;

        return $connection;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getConnectionType(): ?string
    {
        return $this->connectionType;
    }
}
