<?php

namespace App\Tests\Unit;

use App\Service\HealthBand;
use PHPUnit\Framework\TestCase;

class HealthBandTest extends TestCase
{
    private HealthBand $band;

    protected function setUp(): void
    {
        $this->band = new HealthBand();
    }

    public function testFullWhenAtMax(): void
    {
        self::assertSame(HealthBand::FULL, $this->band->derive(20, 20));
        self::assertSame(HealthBand::FULL, $this->band->derive(25, 20)); // overhealed counts full
    }

    public function testDamagedAboveHalf(): void
    {
        // > 50% and below full
        self::assertSame(HealthBand::DAMAGED, $this->band->derive(19, 20));
        self::assertSame(HealthBand::DAMAGED, $this->band->derive(11, 20)); // 55%
    }

    public function testHeavilyDamagedAboveQuarterToHalf(): void
    {
        self::assertSame(HealthBand::HEAVILY_DAMAGED, $this->band->derive(10, 20)); // exactly 50%
        self::assertSame(HealthBand::HEAVILY_DAMAGED, $this->band->derive(6, 20));  // 30%
    }

    public function testAlmostDefeatedBelowQuarter(): void
    {
        self::assertSame(HealthBand::ALMOST_DEFEATED, $this->band->derive(5, 20)); // exactly 25%
        self::assertSame(HealthBand::ALMOST_DEFEATED, $this->band->derive(1, 20));
    }

    public function testDeadOrInvalidReturnsNull(): void
    {
        self::assertNull($this->band->derive(0, 20));
        self::assertNull($this->band->derive(-3, 20));
        self::assertNull($this->band->derive(null, 20));
        self::assertNull($this->band->derive(10, null));
        self::assertNull($this->band->derive(10, 0));
    }
}
