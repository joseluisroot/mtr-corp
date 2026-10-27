<?php
declare(strict_types=1);
use App\Services\CommercialMoney;
use CodeIgniter\Test\CIUnitTestCase;

final class CommercialMoneyTest extends CIUnitTestCase
{
    public function testExactDecimalsAndBounds(): void
    {
        $this->assertSame('100.10',CommercialMoney::amount('000100.1'));
        $this->assertSame(10010,CommercialMoney::cents('100.10'));
        $this->assertSame(99999999999999,CommercialMoney::cents('999999999999.99'));
        CommercialMoney::checkTotal('0.10','0.20');
        $this->expectException(\DomainException::class);CommercialMoney::checkTotal('999999999999.99','0.01');
    }
    public function testNegativeMoneyIsRejected(): void
    {
        $this->expectException(\DomainException::class);CommercialMoney::amount('-1.00');
    }
    public function testDocumentsDoNotAcceptPublicOrTraversalPaths(): void
    {
        $this->expectException(\DomainException::class);(new \App\Services\CommercialDocuments())->path('../../.env');
    }
}
