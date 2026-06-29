<?php

namespace App\Tests\Unit;

use App\Entity\Combatant;
use App\Entity\LiveEncounter;
use App\Entity\StatusEffect;
use App\Service\HealthBand;
use App\Service\LiveEncounterSerializer;
use App\Service\TurnOrderCalculator;
use PHPUnit\Framework\TestCase;

class LiveEncounterSerializerTest extends TestCase
{
    private LiveEncounterSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new LiveEncounterSerializer(new TurnOrderCalculator(), new HealthBand());
    }

    public function testPlayerPayloadStripsEnemyNumbersButKeepsBand(): void
    {
        $enemy = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0, le: 6, maxLe: 20, name: 'Ogre');
        $enemy->setDescription('Secret stats');
        $live = $this->liveWith([$enemy]);

        $dm = $this->serializer->serialize($live, true);
        $player = $this->serializer->serialize($live, false);

        // DM sees everything.
        self::assertSame(6, $dm['combatants'][0]['le']);
        self::assertSame(20, $dm['combatants'][0]['maxLe']);
        self::assertSame('Secret stats', $dm['combatants'][0]['description']);
        self::assertSame(HealthBand::HEAVILY_DAMAGED, $dm['combatants'][0]['healthBand']);

        // Player sees only the band.
        self::assertNull($player['combatants'][0]['le']);
        self::assertNull($player['combatants'][0]['maxLe']);
        self::assertNull($player['combatants'][0]['description']);
        self::assertSame(HealthBand::HEAVILY_DAMAGED, $player['combatants'][0]['healthBand']);
    }

    public function testHeroHasNullHealthFields(): void
    {
        $hero = CombatantFactory::make(1, Combatant::SIDE_PARTY, 10, 0, name: 'Aria');
        $live = $this->liveWith([$hero]);

        $dm = $this->serializer->serialize($live, true);

        self::assertNull($dm['combatants'][0]['le']);
        self::assertNull($dm['combatants'][0]['maxLe']);
        self::assertNull($dm['combatants'][0]['healthBand']);
    }

    public function testRoundEndEffectsOnlyWhenEndOfRound(): void
    {
        $enemy = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0, le: 10, maxLe: 10);
        $enemy->addStatusEffect(
            (new StatusEffect())->setName('Poison')->setDurationRounds(2)->setTriggerAtRoundEnd(true)
        );
        $live = $this->liveWith([$enemy]);

        // COMBAT → empty.
        self::assertSame([], $this->serializer->serialize($live, true)['roundEndEffects']);

        // END_OF_ROUND → populated.
        $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);
        $effects = $this->serializer->serialize($live, true)['roundEndEffects'];
        self::assertCount(1, $effects);
        self::assertSame('Poison', $effects[0]['name']);
    }

    public function testRoundEndEffectsGroupWording(): void
    {
        // Two enemies sharing the same ALL_ENEMIES poison → one grouped "All enemies" row.
        $e1 = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0, le: 10, maxLe: 10, name: 'Goblin A');
        $e2 = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 9, 1, le: 10, maxLe: 10, name: 'Goblin B');
        foreach ([$e1, $e2] as $e) {
            $e->addStatusEffect(
                (new StatusEffect())->setName('Curse')->setDurationRounds(3)
                    ->setTriggerAtRoundEnd(true)->setGroupTag(StatusEffect::GROUP_ALL_ENEMIES)
            );
        }

        // One hero with an individual round-end effect → labeled by name.
        $hero = CombatantFactory::make(3, Combatant::SIDE_PARTY, 8, 2, name: 'Aria');
        $hero->addStatusEffect(
            (new StatusEffect())->setName('Blessed')->setDurationRounds(2)->setTriggerAtRoundEnd(true)
        );

        $live = $this->liveWith([$e1, $e2, $hero]);
        $live->setPhase(LiveEncounter::PHASE_END_OF_ROUND);

        $effects = $this->serializer->serialize($live, true)['roundEndEffects'];

        $labels = array_map(static fn ($e) => $e['label'].':'.$e['name'], $effects);
        self::assertContains('All enemies:Curse', $labels);
        self::assertContains('Aria:Blessed', $labels);
        // Grouped: the two enemies collapse to a single "All enemies" Curse row.
        self::assertCount(2, $effects);
    }

    public function testDisplayNameNumbersDuplicatesBySortOrderAndKeepsUniqueNamesPlain(): void
    {
        // Two Goblins (out of sortOrder sequence) + one unique Ogre.
        $g2 = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 1, le: 10, maxLe: 10, name: 'Goblin');
        $g1 = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 9, 0, le: 10, maxLe: 10, name: 'Goblin');
        $ogre = CombatantFactory::make(3, Combatant::SIDE_ENEMIES, 8, 2, le: 10, maxLe: 10, name: 'Ogre');

        $live = $this->liveWith([$g2, $g1, $ogre]);

        $dm = $this->serializer->serialize($live, true);
        $byId = [];
        foreach ($dm['combatants'] as $c) {
            $byId[$c['id']] = $c['displayName'];
        }

        // Numbered by sortOrder ascending: id=2 (sort 0) → #1, id=1 (sort 1) → #2.
        self::assertSame('Goblin #1', $byId[2]);
        self::assertSame('Goblin #2', $byId[1]);
        // Unique name stays plain.
        self::assertSame('Ogre', $byId[3]);
    }

    public function testDisplayNameNumberingIsStableWhenOneIsDead(): void
    {
        $g1 = CombatantFactory::make(1, Combatant::SIDE_ENEMIES, 10, 0, le: 10, maxLe: 10, name: 'Goblin');
        $g2 = CombatantFactory::make(2, Combatant::SIDE_ENEMIES, 9, 1, le: 0, maxLe: 10, isDead: true, name: 'Goblin');
        $g3 = CombatantFactory::make(3, Combatant::SIDE_ENEMIES, 8, 2, le: 10, maxLe: 10, name: 'Goblin');

        $live = $this->liveWith([$g1, $g2, $g3]);

        $dm = $this->serializer->serialize($live, true);
        $byId = [];
        foreach ($dm['combatants'] as $c) {
            $byId[$c['id']] = $c['displayName'];
        }

        // Dead combatant still occupies its slot so labels remain stable.
        self::assertSame('Goblin #1', $byId[1]);
        self::assertSame('Goblin #2', $byId[2]);
        self::assertSame('Goblin #3', $byId[3]);
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
