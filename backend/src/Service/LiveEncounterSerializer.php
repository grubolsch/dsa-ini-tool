<?php

namespace App\Service;

use App\Entity\Combatant;
use App\Entity\LiveEncounter;
use App\Entity\StatusEffect;

/**
 * Produces the exact LiveEncounter broadcast payload (CONTRACT §LiveEncounter).
 *
 * $forDm = true  → full data (raw le/maxLe/description for enemies).
 * $forDm = false → player payload: enemy le/maxLe/description stripped, healthBand kept.
 */
class LiveEncounterSerializer
{
    public function __construct(
        private readonly TurnOrderCalculator $orderCalculator,
        private readonly HealthBand $healthBand,
    ) {
    }

    public function serialize(LiveEncounter $live, bool $forDm): array
    {
        $ordered = $this->orderCalculator->order($live->getCombatants());
        $orderIds = array_map(static fn (Combatant $c) => $c->getId(), $ordered);

        $activeIndex = $live->getActiveIndex();
        $activeCombatantId = $orderIds[$activeIndex] ?? null;

        // All combatants (incl. dead) — full list, sorted by sortOrder/id for stable display.
        $all = $live->getCombatants()->toArray();
        usort($all, static function (Combatant $a, Combatant $b): int {
            if ($a->getSortOrder() !== $b->getSortOrder()) {
                return $a->getSortOrder() <=> $b->getSortOrder();
            }

            return ($a->getId() ?? PHP_INT_MAX) <=> ($b->getId() ?? PHP_INT_MAX);
        });

        $displayNames = $this->computeDisplayNames($live);

        $encounter = $live->getEncounter();

        return [
            'code' => $live->getCode(),
            'encounter' => $encounter ? [
                'id' => $encounter->getId(),
                'name' => $encounter->getName(),
                'atmospherePicture' => $encounter->getAtmospherePicture(),
            ] : null,
            'round' => $live->getRound(),
            'activeIndex' => $activeIndex,
            'phase' => $live->getPhase(),
            'order' => $orderIds,
            'activeCombatantId' => $activeCombatantId,
            'combatants' => array_map(
                fn (Combatant $c) => $this->serializeCombatant($c, $forDm, $displayNames[spl_object_id($c)]),
                $all
            ),
            'roundEndEffects' => LiveEncounter::PHASE_END_OF_ROUND === $live->getPhase()
                ? $this->roundEndEffects($live)
                : [],
        ];
    }

    /**
     * Compute the stable displayName for every combatant of the live encounter.
     *
     * A name unique within the encounter is used verbatim. When 2+ combatants share a name,
     * each gets a " #n" suffix numbered by sortOrder ascending (1-based). Dead combatants are
     * included in the numbering so labels stay stable across a fight.
     *
     * @return array<int, string> map of spl_object_id(Combatant) => displayName
     */
    private function computeDisplayNames(LiveEncounter $live): array
    {
        $combatants = $live->getCombatants()->toArray();

        // Stable order for numbering: sortOrder asc, then id (nulls last).
        usort($combatants, static function (Combatant $a, Combatant $b): int {
            if ($a->getSortOrder() !== $b->getSortOrder()) {
                return $a->getSortOrder() <=> $b->getSortOrder();
            }

            return ($a->getId() ?? PHP_INT_MAX) <=> ($b->getId() ?? PHP_INT_MAX);
        });

        // Count occurrences per name across ALL combatants (incl. dead).
        $counts = [];
        foreach ($combatants as $c) {
            $counts[$c->getName()] = ($counts[$c->getName()] ?? 0) + 1;
        }

        $seen = [];
        $displayNames = [];
        foreach ($combatants as $c) {
            $name = $c->getName();
            if (($counts[$name] ?? 0) > 1) {
                $index = ($seen[$name] = ($seen[$name] ?? 0) + 1);
                $displayNames[spl_object_id($c)] = $name.' #'.$index;
            } else {
                $displayNames[spl_object_id($c)] = $name;
            }
        }

        return $displayNames;
    }

    private function serializeCombatant(Combatant $c, bool $forDm, string $displayName): array
    {
        $isEnemy = $c->isEnemy();
        $band = $isEnemy ? $this->healthBand->forCombatant($c) : null;

        $data = [
            'id' => $c->getId(),
            'side' => $c->getSide(),
            'name' => $c->getName(),
            'displayName' => $displayName,
            'picture' => $c->getPicture(),
            'initiative' => $c->getInitiative(),
            'le' => $isEnemy ? $c->getLe() : null,
            'maxLe' => $isEnemy ? $c->getMaxLe() : null,
            'description' => $c->getDescription(),
            'isDead' => $c->isDead(),
            'isOutOfCombat' => $c->isOutOfCombat(),
            'sortOrder' => $c->getSortOrder(),
            'iniChangedThisRound' => $c->isIniChangedThisRound(),
            'healthBand' => $band,
            'statusEffects' => array_map(
                fn (StatusEffect $e) => $this->serializeEffect($e),
                $c->getStatusEffects()->toArray()
            ),
        ];

        // Player payload: strip raw enemy numbers + description, keep the band.
        if (!$forDm && $isEnemy) {
            $data['le'] = null;
            $data['maxLe'] = null;
            $data['description'] = null;
        }

        return $data;
    }

    private function serializeEffect(StatusEffect $e): array
    {
        return [
            'id' => $e->getId(),
            'name' => $e->getName(),
            'description' => $e->getDescription(),
            'durationRounds' => $e->getDurationRounds(),
            'triggerAtRoundEnd' => $e->isTriggerAtRoundEnd(),
            'groupTag' => $e->getGroupTag(),
        ];
    }

    /**
     * Round-end effects: only "trigger at round end" effects, grouped by (name, groupTag).
     * Group label: groupTag present → "All enemies"/"All heroes"; else the combatant's name.
     */
    private function roundEndEffects(LiveEncounter $live): array
    {
        $groups = [];

        foreach ($live->getCombatants() as $c) {
            foreach ($c->getStatusEffects() as $effect) {
                if (!$effect->isTriggerAtRoundEnd()) {
                    continue;
                }

                $groupTag = $effect->getGroupTag();
                $label = match ($groupTag) {
                    StatusEffect::GROUP_ALL_ENEMIES => 'All enemies',
                    StatusEffect::GROUP_ALL_HEROES => 'All heroes',
                    default => $c->getName(),
                };

                $key = $label.'|'.$effect->getName().'|'.($groupTag ?? '');

                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'label' => $label,
                        'name' => $effect->getName(),
                        'description' => $effect->getDescription(),
                        'durationRounds' => $effect->getDurationRounds(),
                    ];
                }
            }
        }

        return array_values($groups);
    }
}
