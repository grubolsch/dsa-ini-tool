<?php

namespace App\Service;

use App\Entity\Combatant;

/**
 * Pure (DB-independent) turn-order logic.
 *
 * Rules (CLAUDE §3):
 * - Highest initiative first.
 * - Tie-break: PARTY (heroes) before ENEMIES.
 * - Stable secondary tie-break: sortOrder then id.
 * - Dead combatants are excluded.
 * - Out-of-combat heroes are STILL included (they still get a turn).
 */
class TurnOrderCalculator
{
    /**
     * @param iterable<Combatant> $combatants
     *
     * @return Combatant[] living combatants in this round's turn order
     */
    public function order(iterable $combatants): array
    {
        $living = [];
        foreach ($combatants as $c) {
            if (!$c->isDead()) {
                $living[] = $c;
            }
        }

        usort($living, function (Combatant $a, Combatant $b): int {
            // Highest initiative first.
            if ($a->getInitiative() !== $b->getInitiative()) {
                return $b->getInitiative() <=> $a->getInitiative();
            }

            // Tie-break: PARTY before ENEMIES.
            $aParty = $a->isParty() ? 0 : 1;
            $bParty = $b->isParty() ? 0 : 1;
            if ($aParty !== $bParty) {
                return $aParty <=> $bParty;
            }

            // Stable secondary: sortOrder then id.
            if ($a->getSortOrder() !== $b->getSortOrder()) {
                return $a->getSortOrder() <=> $b->getSortOrder();
            }

            return ($a->getId() ?? PHP_INT_MAX) <=> ($b->getId() ?? PHP_INT_MAX);
        });

        return $living;
    }

    /**
     * @param iterable<Combatant> $combatants
     *
     * @return int[] ordered living combatant ids
     */
    public function orderedIds(iterable $combatants): array
    {
        return array_map(static fn (Combatant $c) => $c->getId(), $this->order($combatants));
    }
}
