<?php

declare(strict_types=1);

namespace App\Tests\Services\MarketData;

use App\Service\MarketData\BoursoramaQuoteProvider;
use PHPUnit\Framework\TestCase;

final class BoursoramaQuoteProviderTest extends TestCase
{
    public function testParseFrenchNumber(): void
    {
        $this->assertSame(330.28, BoursoramaQuoteProvider::parseFrenchNumber('330,28'));
        $this->assertSame(1891.26, BoursoramaQuoteProvider::parseFrenchNumber('1 891,26'));
        $this->assertSame(1891.26, BoursoramaQuoteProvider::parseFrenchNumber("1\xc2\xa0891,26"));
        $this->assertSame(225.80, BoursoramaQuoteProvider::parseFrenchNumber('225.80'));
        $this->assertSame(1234.56, BoursoramaQuoteProvider::parseFrenchNumber('1,234.56'));
        $this->assertNull(BoursoramaQuoteProvider::parseFrenchNumber(''));
        $this->assertNull(BoursoramaQuoteProvider::parseFrenchNumber('not a number'));
    }

    public function testParseQuoteWithEuroPrice(): void
    {
        $html = <<<'HTML'
<html><body>
<div class="c-faceplate__body">
  <div class="c-faceplate__price">
    <span class="c-instrument c-instrument--last" data-ist-last>1 891,26</span>
    <span class="c-faceplate__price-currency"> EUR</span>
  </div>
  <div class="c-faceplate__update">cours du 26/05/2026 à 22:35</div>
</div>
</body></html>
HTML;
        $quote = BoursoramaQuoteProvider::parseQuote('FR0010149302', $html, 'https://www.boursorama.com/cours/FR0010149302/');
        $this->assertNotNull($quote);
        $this->assertSame('FR0010149302', $quote->isin);
        $this->assertSame(1891.26, $quote->nav);
        $this->assertSame('EUR', $quote->currency);
        $this->assertSame('2026-05-26', $quote->navDate?->format('Y-m-d'));
        $this->assertSame('boursorama', $quote->source);
    }

    public function testParseQuoteWithUsdPriceForMultilistedFund(): void
    {
        $html = <<<'HTML'
<html><body>
<section class="c-faceplate__price-section">
  <div class="c-faceplate__price">
    <span class="c-instrument c-instrument--last" data-ist-last>225,80</span>
    <span class="c-faceplate__price-currency"> USD</span>
  </div>
  Date: 26/05/2026
</section>
</body></html>
HTML;
        $quote = BoursoramaQuoteProvider::parseQuote('LU1989766289', $html);
        $this->assertNotNull($quote);
        $this->assertSame(225.80, $quote->nav);
        $this->assertSame('USD', $quote->currency);
    }

    public function testParseQuoteReturnsNullWhenNoFaceplate(): void
    {
        $html = '<html><body><p>No price here, just a search page.</p></body></html>';
        $this->assertNull(BoursoramaQuoteProvider::parseQuote('FR0010149302', $html));
    }

    public function testParseQuoteIgnoresFuturesPricesOutsideFaceplate(): void
    {
        // Cas critique : si la page n'a PAS de bloc faceplate (= ISIN inconnu redirigé sur home),
        // on ne doit PAS capturer un prix de future qui traîne dans les widgets.
        $html = <<<'HTML'
<html><body>
<ul class="c-list-trading">
  <li>
    <span data-ist-last>64 999,41</span> <!-- ceci est un future, pas notre ISIN -->
  </li>
</ul>
</body></html>
HTML;
        $this->assertNull(BoursoramaQuoteProvider::parseQuote('LU2309388624', $html));
    }
}
