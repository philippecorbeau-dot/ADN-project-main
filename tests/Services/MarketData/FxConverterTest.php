<?php

declare(strict_types=1);

namespace App\Tests\Services\MarketData;

use App\Service\MarketData\FxConverter;
use App\Service\MarketData\FxRateProviderInterface;
use App\Service\MarketData\Quote;
use PHPUnit\Framework\TestCase;

final class FxConverterTest extends TestCase
{
    private FxConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new FxConverter($this->buildProvider([
            'EUR' => 1.0,
            'USD' => 1.1637,
            'GBP' => 0.86618,
            'CHF' => 0.9153,
            'JPY' => 185.52,
        ]));
    }

    /**
     * @param array<string, float>|null $rates Null = source indisponible
     */
    private function buildProvider(?array $rates): FxRateProviderInterface
    {
        return new class($rates) implements FxRateProviderInterface {
            public function __construct(private readonly ?array $rates) {}
            public function getRates(): ?array
            {
                if ($this->rates === null) {
                    return null;
                }
                return ['date' => new \DateTimeImmutable('2026-05-27'), 'rates' => $this->rates];
            }
            public function getRate(string $currency): ?float
            {
                return $this->rates[strtoupper($currency)] ?? null;
            }
        };
    }

    public function testConvertSameCurrencyIsNoop(): void
    {
        $this->assertSame(100.0, $this->converter->convert(100.0, 'EUR', 'EUR'));
        $this->assertSame(50.0, $this->converter->convert(50.0, 'USD', 'USD'));
    }

    public function testConvertUsdToEur(): void
    {
        // 1.1637 USD = 1 EUR  ⇒  225.80 USD = 225.80 / 1.1637 EUR
        $eur = $this->converter->convert(225.80, 'USD', 'EUR');
        $this->assertNotNull($eur);
        $this->assertEqualsWithDelta(194.0363, $eur, 0.001);
    }

    public function testConvertEurToUsd(): void
    {
        $usd = $this->converter->convert(100.0, 'EUR', 'USD');
        $this->assertEqualsWithDelta(116.37, $usd, 0.001);
    }

    public function testConvertCrossCurrency(): void
    {
        // CHF -> GBP via EUR
        // 100 CHF = (100 / 0.9153) EUR = 109.2538 EUR
        // 109.2538 EUR = 109.2538 * 0.86618 GBP = 94.6334 GBP
        $gbp = $this->converter->convert(100.0, 'CHF', 'GBP');
        $this->assertEqualsWithDelta(94.6334, $gbp, 0.01);
    }

    public function testConvertReturnsNullForUnknownCurrency(): void
    {
        $this->assertNull($this->converter->convert(100.0, 'XYZ', 'EUR'));
        $this->assertNull($this->converter->convert(100.0, 'EUR', 'XYZ'));
    }

    public function testConvertQuoteUsdToEur(): void
    {
        $usdQuote = new Quote(
            isin: 'LU1989766289',
            nav: 225.80,
            currency: 'USD',
            navDate: new \DateTimeImmutable('2026-05-26'),
            source: 'boursorama',
        );

        $eurQuote = $this->converter->convertQuote($usdQuote, 'EUR');
        $this->assertNotNull($eurQuote);
        $this->assertSame('LU1989766289', $eurQuote->isin);
        $this->assertSame('EUR', $eurQuote->currency);
        $this->assertEqualsWithDelta(194.0363, $eurQuote->nav, 0.001);
        $this->assertSame('boursorama+fx', $eurQuote->source);
        $this->assertEquals($usdQuote->navDate, $eurQuote->navDate);
    }

    public function testConvertQuoteSameCurrencyReturnsSame(): void
    {
        $q = new Quote('FR0010149302', 1891.26, 'EUR', null, 'boursorama');
        $converted = $this->converter->convertQuote($q, 'EUR');
        $this->assertSame($q, $converted);
    }

    public function testConvertWhenRatesUnavailableReturnsNull(): void
    {
        $converter = new FxConverter($this->buildProvider(null));
        $this->assertNull($converter->convert(100.0, 'USD', 'EUR'));
    }
}
