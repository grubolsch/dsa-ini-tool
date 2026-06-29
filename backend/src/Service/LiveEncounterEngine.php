<?php

namespace App\Service;

use App\Entity\Combatant;
use App\Entity\LiveEncounter;

/**
 * Pure domain transitions on a LiveEncounter + its in-memory combatants.
 *
 * No persistence here — callers flush. Designed to be unit-testable without a DB.
 */
class LiveEncounterEngine
{
    public function __construct(
        private readonly TurnOrderCalculator $orderCalculator,
        private readonly DiceRoller $dice,
    ) {
    }

    /**
     * Roll +1..6 onto each combatant's initiative (start of encounter / mid-fight add).
     * Operates only on Combatant rows — never the Hero/MonsterTemplate/EncounterMonster source.
     */
    public function rollStartInitiative(Combatant $c): void
    {
        $c->setInitiative($c->getInitiative() + $this->dice->roll(1, 6));
    }

    /**
     * Build this round's frozen order and assign sortOrder reflecting it.
     *
     * @return Combatant[] ordered living combatants
     */
    public function rebuildOrder(LiveEncounter $live): array
    {
        $ordered = $this->orderCalculator->order($live->getCombatants());

        $i = 0;
        foreach ($ordered as $c) {
            $c->setSortOrder($i++);
        }

        return $ordered;
    }

    /**
     * @return Combatant[] living combatants in current frozen order
     */
    public function currentOrder(LiveEncounter $live): array
    {
        return $this->orderCalculator->order($live->getCombatants());
    }

    /**
     * Advance to the next combatant. Past the last => END_OF_ROUND.
     */
    public function nextTurn(LiveEncounter $live): void
    {
        if (LiveEncounter::PHASE_END_OF_ROUND === $live->getPhase()) {
            return;
        }

        $order = $this->currentOrder($live);
        $count = \count($order);

        if (0 === $count) {
            $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);

            return;
        }

        $next = $live->getActiveIndex() + 1;

        if ($next >= $count) {
            $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);

            return;
        }

        $live->setActiveIndex($next);
    }

    /**
     * Leave END_OF_ROUND: decrement status durations, drop those at 0, apply pending INI,
     * rebuild order, round++, activeIndex=0, phase=COMBAT.
     */
    public function nextRound(LiveEncounter $live): void
    {
        foreach ($live->getCombatants() as $c) {
            // Decrement + remove expired status effects.
            foreach ($c->getStatusEffects()->toArray() as $effect) {
                $remaining = $effect->getDurationRounds() - 1;
                if ($remaining <= 0) {
                    $c->removeStatusEffect($effect);
                } else {
                    $effect->setDurationRounds($remaining);
                }
            }

            // Apply pending initiative.
            if (null !== $c->getPendingInitiative()) {
                $c->setInitiative($c->getPendingInitiative());
                $c->setPendingInitiative(null);
            }
            $c->setIniChangedThisRound(false);
        }

        $this->rebuildOrder($live);

        $live->setRound($live->getRound() + 1);
        $live->setActiveIndex(0);
        $live->setPhase(LiveEncounter::PHASE_COMBAT);
    }

    /**
     * Apply a DM INI edit: stored as pending (applies next round), combatant flagged so it
     * does not act again this round. Current-round position is NOT re-sorted.
     */
    public function changeInitiative(Combatant $c, int $newInitiative): void
    {
        $c->setPendingInitiative($newInitiative);
        $c->setIniChangedThisRound(true);
    }

    /**
     * Apply a DM LE edit. Enemy reaching <= 0 dies and is excluded from order.
     */
    public function changeLe(Combatant $c, int $newLe): void
    {
        $c->setLe($newLe);

        if ($c->isEnemy() && $newLe <= 0) {
            $c->setIsDead(true);
        }
    }
}
