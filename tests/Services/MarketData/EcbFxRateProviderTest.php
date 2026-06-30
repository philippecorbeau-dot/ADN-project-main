<?php

declare(strict_types=1);

namespace App\Tests\Services\MarketData;

use App\Service\MarketData\EcbFxRateProvider;
use PHPUnit\Framework\TestCase;

final class EcbFxRateProviderTest extends TestCase
{
    public function testParsesValidEcbXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <gesmes:subject>Reference rates</gesmes:subject>
    <gesmes:Sender>
        <gesmes:name>European Central Bank</gesmes:name>
    </gesmes:Sender>
    <Cube>
        <Cube time='2026-05-27'>
            <Cube currency='USD' rate='1.1637'/>
            <Cube currency='GBP' rate='0.86618'/>
            <Cube currency='CHF' rate='0.9153'/>
            <Cube currency='JPY' rate='185.52'/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        $result = EcbFxRateProvider::parseEcbXml($xml);
        $this->assertIsArray($result);
        $this->assertSame('2026-05-27', $result['date']->format('Y-m-d'));

        $rates = $result['rates'];
        $this->assertSame(1.0, $rates['EUR']);
        $this->assertSame(1.1637, $rates['USD']);
        $this->assertSame(0.86618, $rates['GBP']);
        $this->assertSame(0.9153, $rates['CHF']);
        $this->assertSame(185.52, $rates['JPY']);
    }

    public function testReturnsNullOnInvalidXml(): void
    {
        $this->assertNull(EcbFxRateProvider::parseEcbXml('<bogus>not ecb</bogus>'));
        $this->assertNull(EcbFxRateProvider::parseEcbXml('not xml at all'));
    }

    public function testReturnsNullWhenNoDatedCube(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube></Cube>
</gesmes:Envelope>
XML;
        $this->assertNull(EcbFxRateProvider::parseEcbXml($xml));
    }
}
