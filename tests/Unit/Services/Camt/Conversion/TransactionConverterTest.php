
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Camt\Conversion;

use App\Services\Camt\AbstractTransaction;
use App\Services\Camt\Conversion\TransactionConverter;
use App\Services\Shared\Configuration\Configuration;
use Tests\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Services\Camt\Conversion\TransactionConverter
 */
final class TransactionConverterTest extends TestCase
{
    public function testSingleDescriptionIsPreserved(): void
    {
        $result = $this->convert(
            [
                'entryAdditionalInfo' => 'Card payment at supermarket',
            ],
            [
                'entryAdditionalInfo' => 'description',
            ]
        );

        $this->assertSame(
            [
                'entryAdditionalInfo' => 'Card payment at supermarket',
            ],
            $result[0]['transactions'][0]['description']['data']
        );
    }

    public function testMultipleDescriptionFieldsArePreserved(): void
    {
        $result = $this->convert(
            [
                'entryAdditionalInfo' =>
                    'SEPA transfer',
                'entryDetailRemittanceInformationUnstructuredBlockMessage' =>
                    'Invoice 12345',
            ],
            [
                'entryAdditionalInfo' => 'description',
                'entryDetailRemittanceInformationUnstructuredBlockMessage' =>
                    'description',
            ]
        );

        $this->assertSame(
            [
                'entryAdditionalInfo' => 'SEPA transfer',
                'entryDetailRemittanceInformationUnstructuredBlockMessage' =>
                    'Invoice 12345',
            ],
            $result[0]['transactions'][0]['description']['data']
        );
    }

    private function convert(array $fields, array $roles): array
    {
        $configuration = Configuration::make();
        $configuration->setRoles($roles);

        $transaction = new class ($fields) extends AbstractTransaction {
            public function __construct(private array $fields)
            {
                $this->levelD = [];
            }

            public function getFieldByIndex(string $field, int $index): string
            {
                return $this->fields[$field] ?? '';
            }
        };

        return (new TransactionConverter($configuration))
            ->convert([$transaction]);
    }
}

