<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Camt\Conversion;

use App\Services\Camt\AbstractTransaction;
use App\Services\Camt\Conversion\TransactionConverter;
use App\Services\Camt\TransactionCamt053;
use App\Services\Shared\Configuration\Configuration;
use Genkgo\Camt\DTO\Entry;
use Genkgo\Camt\DTO\EntryTransactionDetail;
use Genkgo\Camt\DTO\RemittanceInformation;
use Genkgo\Camt\DTO\UnstructuredRemittanceInformation;
use Mockery;
use ReflectionClass;
use ReflectionProperty;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversDefaultClass \App\Services\Camt\Conversion\TransactionConverter
 */
final class TransactionConverterTest extends TestCase
{
    public function testAdditionalInfoIsPreservedAsDescription(): void
    {
        config()->set('camt.fields', [
            'entryAdditionalInfo' => [],
        ]);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('getAdditionalInfo')
            ->once()
            ->andReturn('Card payment at supermarket');

        $transaction = (new ReflectionClass(TransactionCamt053::class))
            ->newInstanceWithoutConstructor();

        (new ReflectionProperty(AbstractTransaction::class, 'levelC'))
            ->setValue($transaction, $entry);

        (new ReflectionProperty(AbstractTransaction::class, 'levelD'))
            ->setValue($transaction, []);

        $configuration = Configuration::make();
        $configuration->setRoles([
            'entryAdditionalInfo' => 'description',
        ]);

        $result = (new TransactionConverter($configuration))
            ->convert([$transaction]);

        $this->assertSame(
            ['entryAdditionalInfo' => 'Card payment at supermarket'],
            $result[0]['transactions'][0]['description']['data']
        );
    }

    public function testEntryAndRemittanceDescriptionsAreBothPreserved(): void
    {
        config()->set('camt.fields', [
            'entryAdditionalInfo' => [],
            'entryDetailRemittanceInformationUnstructuredBlockMessage' => [],
        ]);

        $entry = Mockery::mock(Entry::class);
        $entry->shouldReceive('getAdditionalInfo')
            ->once()
            ->andReturn('SEPA transfer');

        $remittance = new RemittanceInformation();
        $remittance->addUnstructuredBlock(
            new UnstructuredRemittanceInformation('Invoice 12345')
        );

        $detail = new EntryTransactionDetail();
        $detail->setRemittanceInformation($remittance);

        $transaction = (new ReflectionClass(TransactionCamt053::class))
            ->newInstanceWithoutConstructor();

        (new ReflectionProperty(AbstractTransaction::class, 'levelC'))
            ->setValue($transaction, $entry);

        (new ReflectionProperty(AbstractTransaction::class, 'levelD'))
            ->setValue($transaction, [$detail]);

        $configuration = Configuration::make();
        $configuration->setRoles([
            'entryAdditionalInfo' => 'description',
            'entryDetailRemittanceInformationUnstructuredBlockMessage'
                => 'description',
        ]);

        $result = (new TransactionConverter($configuration))
            ->convert([$transaction]);

        $this->assertSame(
            [
                'entryAdditionalInfo' => 'SEPA transfer',
                'entryDetailRemittanceInformationUnstructuredBlockMessage'
                    => 'Invoice 12345',
            ],
            $result[0]['transactions'][0]['description']['data']
        );
    }
}
