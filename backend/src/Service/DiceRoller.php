<?php

namespace App\Service;

/**
 * Tiny indirection over randomness so tests can inject deterministic rolls.
 */
class DiceRoller
{
    /**
     * Roll a single die between $min and $max inclusive (default d6: 1..6).
     */
    public function roll(int $min = 1, int $max = 6): int
    {
        return random_int($min, $max);
    }
}
