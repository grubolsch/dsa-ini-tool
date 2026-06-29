<?php

namespace App\Tests\Unit;

use App\Service\DiceRoller;

/**
 * Deterministic dice for tests: returns a fixed value (or cycles a queue).
 */
final class FixedDiceRoller extends DiceRoller
{
    /** @var int[] */
    private array $queue;

    public function __construct(private readonly int $fixed = 3, array $queue = [])
    {
        $this->queue = $queue;
    }

    public function roll(int $min = 1, int $max = 6): int
    {
        if (!empty($this->queue)) {
            return array_shift($this->queue);
        }

        return $this->fixed;
    }
}
