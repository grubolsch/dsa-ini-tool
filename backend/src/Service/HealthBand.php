<?php

namespace App\Service;

use App\Entity\Combatant;

/**
 * Derives the qualitative enemy health band from le / maxLe (CONTRACT §health band).
 *
 * - le >= maxLe        => FULL
 * - le > 0.5 * maxLe   => DAMAGED
 * - le > 0.25 * maxLe  => HEAVILY_DAMAGED
 * - le > 0             => ALMOST_DEFEATED
 * - le <= 0            => dead (removed) — returns null here, death handled separately
 *
 * Heroes (PARTY) have no health => null.
 */
class HealthBand
{
    public const FULL = 'FULL';
    public const DAMAGED = 'DAMAGED';
    public const HEAVILY_DAMAGED = 'HEAVILY_DAMAGED';
    public const ALMOST_DEFEATED = 'ALMOST_DEFEATED';

    public function derive(?int $le, ?int $maxLe): ?string
    {
        if (null === $le || null === $maxLe || $maxLe <= 0) {
            return null;
        }

        if ($le <= 0) {
            return null;
        }

        if ($le >= $maxLe) {
            return self::FULL;
        }

        if ($le > 0.5 * $maxLe) {
            return self::DAMAGED;
        }

        if ($le > 0.25 * $maxLe) {
            return self::HEAVILY_DAMAGED;
        }

        return self::ALMOST_DEFEATED;
    }

    public function forCombatant(Combatant $c): ?string
    {
        if (!$c->isEnemy()) {
            return null;
        }

        return $this->derive($c->getLe(), $c->getMaxLe());
    }
}
