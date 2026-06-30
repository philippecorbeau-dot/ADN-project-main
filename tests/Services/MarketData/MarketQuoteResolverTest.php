<?php

declare(strict_types=1);

namespace App\Tests\Services\MarketData;

use App\Service\MarketData\FxConverter;
use App\Service\MarketData\FxRateProviderInterface;
use App\Service\MarketData\MarketQuoteProviderInterface;
use App\Service\MarketData\MarketQuoteResolver;
use App\Service\MarketData\Quote;
use PHPUnit\Framework\TestCase;

final class MarketQuoteResolverTest extends TestCase
{
    private function buildFxConverter(): FxConverter
    {
        $stub = new class implements FxRateProviderInterface {
            public function getRates(): ?array
            {
                return [
                    'date' => new \DateTimeImmutable('2026-05-27'),
                    'rates' => ['EUR' => 1.0, 'USD' => 1.1637, 'GBP' => 0.86618],
                ];
            }
            public function getRate(string $currency): ?float
            {
                return $this->getRates()['rates'][strtoupper($currency)] ?? null;
            }
        };
        return new FxConverter($stub);
    }

    /**
     * Provider stub qui renvoie une Quote prédéfinie ou null.
     */
    private function buildProvider(?Quote $quote, string $name = 'stub'): MarketQuoteProviderInterface
    {
        return new class($quote, $name) implements MarketQuoteProviderInterface {
            public function __construct(private readonly ?Quote $quote, private readonly string $name) {}
            public function getQuote(string $isin): ?Quote { return $this->quote; }
            public function getSourceName(): string { return $this->name; }
        };
    }

    public function testResolveEurDirectWhenProviderReturnsEur(): void
    {
        $quote = new Quote('FR0010149302', 1891.26, 'EUR', new \DateTimeImmutable('2026-05-26'), 'stub');
        $resolver = new MarketQuoteResolver(
            [$this->buildProvider($quote, 'stub')],
            $this->buildFxConverter(),
        );

        $r = $resolver->resolveEur('FR0010149302');
        $this->assertNotNull($r);
        $this->assertFalse($r->isConverted());
        $this->assertSame(1891.26, $r->quote->nav);
        $this->assertSame('EUR', $r->quote->currency);
    }

    public function testResolveEurAppliesFxConversionWhenProviderReturnsUsd(): void
    {
        $quote = new Quote('LU1989766289', 225.80, 'USD', new \DateTimeImmutable('2026-05-26'), 'stub');
        $resolver = new MarketQuoteResolver(
            [$this->buildProvider($quote, 'stub')],
            $this->buildFxConverter(),
        );

        $r = $resolver->resolveEur('LU1989766289');
        $this->assertNotNull($r);
        $this->assertTrue($r->isConverted());
        $this->assertSame('EUR', $r->quote->currency);
        $this->assertEqualsWithDelta(194.0363, $r->quote->nav, 0.001);
        $this->assertSame('USD', $r->nativeQuote->currency);
        $this->assertSame(225.80, $r->nativeQuote->nav);
        $this->assertNotNull($r->fxRate);
    }

    public function testResolveEurFallsThroughToNextProviderOnFailure(): void
    {
        $primary = $this->buildProvider(null, 'primary');
        $secondary = $this->buildProvider(
            new Quote('FR0010149302', 1900.00, 'EUR', null, 'secondary'),
            'secondary',
        );

        $resolver = new MarketQuoteResolver(
            [$primary, $secondary],
            $this->buildFxConverter(),
        );

        $r = $resolver->resolveEur('FR0010149302');
        $this->assertNotNull($r);
        $this->assertSame(1900.00, $r->quote->nav);
        $this->assertSame('secondary', $r->quote->source);
    }

    public function testResolveEurReturnsNullWhenNoProviderSucceeds(): void
    {
        $resolver = new MarketQuoteResolver(
            [$this->buildProvider(null), $this->buildProvider(null)],
            $this->buildFxConverter(),
        );

        $this->assertNull($resolver->resolveEur('NON12345678X'));
    }
}
