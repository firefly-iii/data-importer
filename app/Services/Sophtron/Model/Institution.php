<?php
/*
 * Institution.php
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

namespace App\Services\Sophtron\Model;

use App\Exceptions\ImporterHttpException;

class Institution
{
    public string $id;
    public string $name;
    public string $url;
    public string $logo              = '';
    public string $countryCode;
    public string $languageCode;
    private bool  $isFinancial;
    public string $loginFormUserName = '';
    public string $loginFormPassword = '';
    public string $routingNumber     = '';
    public array  $loginFormFields   = [];

    private function __construct()
    {
    }

    public static function fromArray(array $data): Institution
    {
        $institution               = new self();
        $institution->id           = $data['InstitutionID'];
        $institution->name         = $data['InstitutionName'];
        $institution->url          = $data['URL'];
        $institution->logo         = trim($data['Logo'] ?? '');
        $institution->countryCode  = $data['CountryCode'];
        $institution->languageCode = $data['LanguageCode'];
        $institution->isFinancial  = $data['IsFinancial'];

        // LoginFormUserName is connected to LoginFormFields[x][DisplayText]

        $fields = ['LoginFormUserName', 'LoginFormPassword', 'RoutingNumber', 'LoginFormFields', 'MultipleRoutingNumbers'];
        if (array_key_exists('InstitutionDetail', $data) && is_array($data['InstitutionDetail'])) {
            foreach ($data['InstitutionDetail'] as $field => $value) {
                if (!in_array($field, $fields)) {
                    throw new ImporterHttpException(sprintf('Institution has unknown field "%s": %s', $field, json_encode($value)));
                }
                switch ($field) {
                    case 'MultipleRoutingNumbers':
                        if (is_string($value)) {
                            $institution->routingNumber = $value;
                            break;
                        }
                        throw new ImporterHttpException(sprintf('Institution has non-string field "%s": %s', $field, json_encode($value)));
                    case 'LoginFormUserName':
                        $institution->loginFormUserName = $value;
                        break;
                    case 'LoginFormPassword':
                        $institution->loginFormPassword = $value;
                        break;
                    case 'RoutingNumber':
                        $institution->routingNumber = $value;
                        break;
                    case 'LoginFormFields':
                        if (!is_array($value)) {
                            throw new ImporterHttpException('Institution field "LoginFormFields" is not an array.', $field);
                        }
                        $institution->loginFormFields = $value;
                        break;
                }
            }
        }

        return $institution;

    }

    public function toArray(): array
    {
        return [
            'InstitutionID'     => $this->id,
            'InstitutionName'   => $this->name,
            'URL'               => $this->url,
            'Logo'              => $this->logo,
            'CountryCode'       => $this->countryCode,
            'LanguageCode'      => $this->languageCode,
            'IsFinancial'       => $this->isFinancial,
            'InstitutionDetail' => [
                'MultipleRoutingNumbers' => $this->routingNumber,
                'LoginFormUserName'      => $this->loginFormUserName,
                'LoginFormPassword'      => $this->loginFormPassword,
                'RoutingNumber'          => $this->routingNumber,
                'LoginFormFields'        => $this->loginFormFields,
            ],
        ];
    }

}
