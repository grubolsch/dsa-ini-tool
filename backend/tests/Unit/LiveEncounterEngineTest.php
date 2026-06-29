<?php

namespace App\Tests\Unit;

use App\Entity\Combatant;
use App\Entity\LiveEncounter;
use App\Entity\StatusEffect;
use App\Service\LiveEncounterEngine;
use App\Service\TurnOrderCalculator;
use PHPUnit\Framework\TestCase;

class LiveEncounterEngineTest extends TestCase
{
    private function engine(int $fixedRoll = 3): LiveEncounterEngine
    {
        return new LiveEncounterEngine(new TurnOrderCalculator(), new FixedDiceRoller($fixedRoll));
    }

    public function testStartRollAddsDieToInitiative(): void
    {
        $engine = $this->engine(4);
        $c = CombatantFactory::make(1, Combatant::SIDE_PARTY, 10);

        $engine->rollStartInitiative($c);

        self::assertSame(14, $c->getInitiative());
    }

    public function testNextTurnAdvancesIndex(): void
    {
        $engine = $this->engine();
        $live = $this->liveWith([
            CombatantFactory::make(1, Combatant::SIDE_PARTY, 15, 0),
            CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 10, 1),
        ]);

        self::assertSame(0, $live->getActiveIndex());
        $engine->nextTurn($live);
        self::assertSame(1, $live->getActiveIndex());
        self::assertSame(LiveEncounter::PHASE_COMBAT, $live->getPhase());
    }

    public function testNextTurnPastLastEntersEndOfRound(): void
    {
        $engine = $this->engine();
        $live = $this->liveWith([
            CombatantFactory::make(1, Combatant::SIDE_PARTY, 15, 0),
            CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 10, 1),
        ]);

        $engine->nextTurn($live); // index 1 (last)
        $engine->nextTurn($live); // past last → END_OF_ROUND

        self::assertSame(LiveEncounter::PHASE_END_OF_ROUND, $live->getPhase());
    }

    public function testNextRoundIncrementsRoundResetsIndexAndPhase(): void
    {
        $engine = $this->engine();
        $live = $this->liveWith([
            CombatantFactory::make(1, Combatant::SIDE_PARTY, 15, 0),
            CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 10, 1),
        ]);
        $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);
        $live->setActiveIndex(1);

        $engine->nextRound($live);

        self::assertSame(2, $live->getRound());
        self::assertSame(0, $live->getActiveIndex());
        self::assertSame(LiveEncounter::PHASE_COMBAT, $live->getPhase());
    }

    public function testIniChangeAppliesNextRoundOnly_AndCombatantDoesNotActAgainThisRound(): void
    {
        $engine = $this->engine();
        $hero = CombatantFactory::make(1, Combatant::SIDE_PARTY, 20, 0);
        $enemy = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 10, 1);
        $live = $this->liveWith([$hero, $enemy]);

        // DM drops the hero's INI to 1 mid-round.
        $engine->changeInitiative($hero, 1);

        // Current round: stored as pending, flagged, NOT applied yet → order unchanged.
        self::assertSame(20, $hero->getInitiative());
        self::assertSame(1, $hero->getPendingInitiative());
        self::assertTrue($hero->isIniChangedThisRound());
        self::assertSame([1, 2], (new TurnOrderCalculator())->orderedIds($live->getCombatants()));

        // Next round: pending applied, flag cleared, order rebuilt → hero now last.
        $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);
        $engine->nextRound($live);

        self::assertSame(1, $hero->getInitiative());
        self::assertNull($hero->getPendingInitiative());
        self::assertFalse($hero->isIniChangedThisRound());
        self::assertSame([2, 1], (new TurnOrderCalculator())->orderedIds($live->getCombatants()));
    }

    public function testEnemyAtZeroLeRemovedFromOrder(): void
    {
        $engine = $this->engine();
        $enemy = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0, le: 8, maxLe: 10);
        $hero = CombatantFactory::make(2, Combatant::SIDE_PARTY, 5, 1);
        $live = $this->liveWith([$enemy, $hero]);

        $engine->changeLe($enemy, 0);

        self::assertTrue($enemy->isDead());
        self::assertSame([2], (new TurnOrderCalculator())->orderedIds($live->getCombatants()));
    }

    public function testStatusEffectDurationDecrementsAndRemovesAtZero(): void
    {
        $engine = $this->engine();
        $c = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0, le: 10, maxLe: 10);

        $poison = (new StatusEffect())->setName('Poison')->setDurationRounds(2);
        $bleed = (new StatusEffect())->setName('Bleed')->setDurationRounds(1);
        $c->addStatusEffect($poison);
        $c->addStatusEffect($bleed);

        $live = $this->liveWith([$c]);
        $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);

        $engine->nextRound($live);

        // Poison: 2 → 1 (kept). Bleed: 1 → 0 (removed).
        self::assertCount(1, $c->getStatusEffects());
        self::assertSame('Poison', $c->getStatusEffects()->first()->getName());
        self::assertSame(1, $c->getStatusEffects()->first()->getDurationRounds());
    }

    /**
     * @param Combatant[] $combatants
     */
    private function liveWith(array $combatants): LiveEncounter
    {
        $live = new LiveEncounter();
        $live->setCode('TESTCODE');
        foreach ($combatants as $c) {
            $live->addCombatant($c);
        }

        return $live;
    }
}
