<?php

namespace App\Tests\Unit;

use App\Entity\Combatant;
use App\Service\TurnOrderCalculator;
use PHPUnit\Framework\TestCase;

class TurnOrderCalculatorTest extends TestCase
{
    private TurnOrderCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new TurnOrderCalculator();
    }

    public function testHighestInitiativeFirst(): void
    {
        $a = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 5);
        $b = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 12);
        $c = CombatantFactory::make(3, Combatant::SIDE_ENEMIES, 8);

        self::assertSame([2, 3, 1], $this->calc->orderedIds([$a, $b, $c]));
    }

    public function testTieBreakHeroBeforeEnemyOnEqualInitiative(): void
    {
        $enemy = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0);
        $hero = CombatantFactory::make(2, Combatant::SIDE_PARTY, 10, 1);

        // Equal INI → PARTY (hero) comes first regardless of sortOrder.
        self::assertSame([2, 1], $this->calc->orderedIds([$enemy, $hero]));
    }

    public function testStableSecondaryTieBreakBySortOrderThenId(): void
    {
        $a = CombatantFactory::make(10, Combatant::SIDE_PARTY, 10, 2);
        $b = CombatantFactory::make(11, Combatant::SIDE_PARTY, 10, 1);
        $c = CombatantFactory::make(12, Combatant::SIDE_PARTY, 10, 1);

        // Same INI + same side: order by sortOrder (1 before 2), then id (11 before 12).
        self::assertSame([11, 12, 10], $this->calc->orderedIds([$a, $b, $c]));
    }

    public function testDeadEnemyExcluded(): void
    {
        $alive = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10);
        $dead = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 99, 0, le: 0, maxLe: 10, isDead: true);

        self::assertSame([1], $this->calc->orderedIds([$alive, $dead]));
    }

    public function testOutOfCombatHeroStillIncluded(): void
    {
        $hero = CombatantFactory::make(1, Combatant::SIDE_PARTY, 7, 0, isOutOfCombat: true);
        $enemy = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 5);

        // The out-of-combat hero still gets a turn (still in order).
        self::assertSame([1, 2], $this->calc->orderedIds([$hero, $enemy]));
    }
}
