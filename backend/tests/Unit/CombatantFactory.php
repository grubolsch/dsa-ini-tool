<?php

namespace App\Tests\Unit;

use App\Entity\Combatant;

/**
 * Builds in-memory Combatant entities (with a forced id via reflection) for pure unit tests.
 */
final class CombatantFactory
{
    public static function make(
        int $id,
        string $side,
        int $initiative,
        int $sortOrder = 0,
        ?int $le = null,
        ?int $maxLe = null,
        bool $isDead = false,
        bool $isOutOfCombat = false,
        string $name = 'C',
    ): Combatant {
        $c = new Combatant();
        $c->setSide($side);
        $c->setInitiative($initiative);
        $c->setSortOrder($sortOrder);
        $c->setLe($le);
        $c->setMaxLe($maxLe);
        $c->setIsDead($isDead);
        $c->setIsOutOfCombat($isOutOfCombat);
        $c->setName($name);

        self::setId($c, $id);

        return $c;
    }

    public static function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setValue($entity, $id);
    }
}
