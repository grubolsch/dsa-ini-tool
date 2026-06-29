<?php

namespace App\Tests\Functional;

use App\Entity\Encounter;
use App\Entity\EncounterMonster;
use App\Entity\Hero;
use App\Entity\MonsterTemplate;

class LiveEncounterApiTest extends ApiTestCase
{
    private function seedEncounterWithHeroAndMonster(): array
    {
        $hero = (new Hero())->setName('Aria')->setInitiative(10);
        $this->em->persist($hero);

        $tpl = (new MonsterTemplate())->setName('Goblin')->setInitiative(8)->setLe(15);
        $this->em->persist($tpl);

        $enc = (new Encounter())->setName('Ambush');
        $this->em->persist($enc);

        $monster = new EncounterMonster();
        $monster->setEncounter($enc)->setMonsterTemplate($tpl)
            ->setName('Goblin')->setInitiative(8)->setLe(15);
        $enc->addMonster($monster);
        $this->em->persist($monster);

        $this->em->flush();

        return [$hero, $tpl, $enc, $monster];
    }

    public function testStartRollsInitiativeAndDoesNotMutateTemplates(): void
    {
        [$hero, $tpl, $enc] = $this->seedEncounterWithHeroAndMonster();
        $heroBaseIni = $hero->getInitiative();
        $tplBaseIni = $tpl->getInitiative();

        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        self::assertSame(200, $this->statusCode());

        self::assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $state['code']);
        self::assertCount(2, $state['combatants']);

        // Combatant INI = base + 1..6.
        foreach ($state['combatants'] as $c) {
            $base = 'PARTY' === $c['side'] ? $heroBaseIni : $tplBaseIni;
            self::assertGreaterThanOrEqual($base + 1, $c['initiative']);
            self::assertLessThanOrEqual($base + 6, $c['initiative']);
        }

        // Source rows untouched.
        $this->em->clear();
        $freshHero = $this->em->getRepository(Hero::class)->find($hero->getId());
        $freshTpl = $this->em->getRepository(MonsterTemplate::class)->find($tpl->getId());
        self::assertSame($heroBaseIni, $freshHero->getInitiative());
        self::assertSame($tplBaseIni, $freshTpl->getInitiative());
    }

    public function testNextTurnAndEndOfRoundThenNextRound(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        self::assertSame('COMBAT', $state['phase']);
        self::assertSame(0, $state['activeIndex']);
        self::assertSame(1, $state['round']);

        // 2 combatants → advance once (index 1), advance again → END_OF_ROUND.
        $state = $this->json('POST', "/api/live/{$code}/next-turn");
        self::assertSame(1, $state['activeIndex']);

        $state = $this->json('POST', "/api/live/{$code}/next-turn");
        self::assertSame('END_OF_ROUND', $state['phase']);

        // next-round resets.
        $state = $this->json('POST', "/api/live/{$code}/next-round");
        self::assertSame('COMBAT', $state['phase']);
        self::assertSame(2, $state['round']);
        self::assertSame(0, $state['activeIndex']);
    }

    public function testPatchInitiativeAppliesNextRoundOnly(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $first = $state['order'][0];

        // Drop the first actor's INI hard.
        $state = $this->json('PATCH', "/api/live/{$code}/combatants/{$first}", ['initiative' => -5]);

        // Same round: flagged, position unchanged, INI not yet applied.
        $patched = $this->findCombatant($state, $first);
        self::assertTrue($patched['iniChangedThisRound']);
        self::assertSame($first, $state['order'][0]); // still first this round

        // Next round: pending applied → order rebuilt, flag cleared.
        $this->json('POST', "/api/live/{$code}/next-turn");
        $this->json('POST', "/api/live/{$code}/next-turn");
        $state = $this->json('POST', "/api/live/{$code}/next-round");

        $patched = $this->findCombatant($state, $first);
        self::assertFalse($patched['iniChangedThisRound']);
        self::assertSame(-5, $patched['initiative']);
        self::assertNotSame($first, $state['order'][0]); // dropped down the order
    }

    public function testEnemyLeZeroRemovedFromOrder(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $enemyId = null;
        foreach ($state['combatants'] as $c) {
            if ('ENEMIES' === $c['side']) {
                $enemyId = $c['id'];
            }
        }
        self::assertNotNull($enemyId);

        $state = $this->json('PATCH', "/api/live/{$code}/combatants/{$enemyId}", ['le' => 0]);

        $enemy = $this->findCombatant($state, $enemyId);
        self::assertTrue($enemy['isDead']);
        self::assertNotContains($enemyId, $state['order']);
    }

    public function testOutOfCombatOnlyForHeroes(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $heroId = $enemyId = null;
        foreach ($state['combatants'] as $c) {
            if ('PARTY' === $c['side']) {
                $heroId = $c['id'];
            } else {
                $enemyId = $c['id'];
            }
        }

        // Enemy → 400.
        $this->json('POST', "/api/live/{$code}/combatants/{$enemyId}/out-of-combat");
        self::assertSame(400, $this->statusCode());

        // Hero → ok, still in order, then resurrect.
        $state = $this->json('POST', "/api/live/{$code}/combatants/{$heroId}/out-of-combat");
        self::assertSame(200, $this->statusCode());
        $hero = $this->findCombatant($state, $heroId);
        self::assertTrue($hero['isOutOfCombat']);
        self::assertContains($heroId, $state['order']); // still acts

        $state = $this->json('POST', "/api/live/{$code}/combatants/{$heroId}/resurrect");
        $hero = $this->findCombatant($state, $heroId);
        self::assertFalse($hero['isOutOfCombat']);
    }

    public function testPlayerPayloadSanitizesEnemyNumbers(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $player = $this->json('GET', "/api/live/{$code}");
        foreach ($player['combatants'] as $c) {
            if ('ENEMIES' === $c['side']) {
                self::assertNull($c['le']);
                self::assertNull($c['maxLe']);
                self::assertNotNull($c['healthBand']);
            }
        }

        $dm = $this->json('GET', "/api/live/{$code}?dm=1");
        foreach ($dm['combatants'] as $c) {
            if ('ENEMIES' === $c['side']) {
                self::assertNotNull($c['le']);
                self::assertNotNull($c['maxLe']);
            }
        }
    }

    public function testStatusEffectAllEnemiesGetGroupTag(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $state = $this->json('POST', "/api/live/{$code}/status-effects", [
            'name' => 'Poison',
            'description' => '1 dmg',
            'durationRounds' => 2,
            'triggerAtRoundEnd' => true,
            'targets' => ['combatantIds' => [], 'allEnemies' => true, 'allHeroes' => false],
        ]);
        self::assertSame(201, $this->statusCode());

        $enemyEffects = [];
        foreach ($state['combatants'] as $c) {
            if ('ENEMIES' === $c['side']) {
                $enemyEffects = $c['statusEffects'];
            }
        }
        self::assertCount(1, $enemyEffects);
        self::assertSame('ALL_ENEMIES', $enemyEffects[0]['groupTag']);

        // Advance until END_OF_ROUND, then assert round-end wording groups under "All enemies".
        for ($i = 0; $i < 10 && 'END_OF_ROUND' !== ($state['phase'] ?? null); ++$i) {
            $state = $this->json('POST', "/api/live/{$code}/next-turn");
        }
        self::assertSame('END_OF_ROUND', $state['phase']);

        $labels = array_map(static fn ($e) => $e['label'], $state['roundEndEffects']);
        self::assertContains('All enemies', $labels);
        // One grouped row for the side-wide poison (not one per enemy).
        $poisonRows = array_filter($state['roundEndEffects'], static fn ($e) => 'Poison' === $e['name']);
        self::assertCount(1, $poisonRows);
    }

    public function testAddCombatantWithQuantityCreatesMultiple(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];
        $before = \count($state['combatants']);

        $state = $this->multipart('POST', "/api/live/{$code}/combatants", [
            'name' => 'Kobold',
            'side' => 'ENEMIES',
            'le' => 7,
            'quantity' => 3,
        ]);
        self::assertSame(201, $this->statusCode());

        self::assertCount($before + 3, $state['combatants']);

        $kobolds = array_values(array_filter(
            $state['combatants'],
            static fn ($c) => 'Kobold' === $c['name']
        ));
        self::assertCount(3, $kobolds);

        // Each rolled its own INI (base 0 + 1..6) and carries enemy LE/maxLe.
        foreach ($kobolds as $k) {
            self::assertGreaterThanOrEqual(1, $k['initiative']);
            self::assertLessThanOrEqual(6, $k['initiative']);
            self::assertSame(7, $k['le']);
            self::assertSame(7, $k['maxLe']);
        }

        // Auto-numbered displayName for the duplicate group.
        $displayNames = array_map(static fn ($c) => $c['displayName'], $kobolds);
        sort($displayNames);
        self::assertSame(['Kobold #1', 'Kobold #2', 'Kobold #3'], $displayNames);
    }

    public function testAddCombatantFromTemplateCopiesNameAndLe(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $tpl = (new MonsterTemplate())->setName('Troll')->setInitiative(5)->setLe(42)
            ->setDescription('Regenerates');
        $this->em->persist($tpl);
        $this->em->flush();

        $state = $this->multipart('POST', "/api/live/{$code}/combatants", [
            'monsterTemplateId' => $tpl->getId(),
        ]);
        self::assertSame(201, $this->statusCode());

        $troll = null;
        foreach ($state['combatants'] as $c) {
            if ('Troll' === $c['name']) {
                $troll = $c;
            }
        }
        self::assertNotNull($troll);
        self::assertSame(42, $troll['le']);
        self::assertSame(42, $troll['maxLe']);
        self::assertSame('Regenerates', $troll['description']);
        // INI = template base (5) + 1..6 roll.
        self::assertGreaterThanOrEqual(6, $troll['initiative']);
        self::assertLessThanOrEqual(11, $troll['initiative']);
    }

    public function testAddCombatantUnknownTemplateReturns404(): void
    {
        [, , $enc] = $this->seedEncounterWithHeroAndMonster();
        $state = $this->json('POST', "/api/encounters/{$enc->getId()}/start");
        $code = $state['code'];

        $this->multipart('POST', "/api/live/{$code}/combatants", [
            'monsterTemplateId' => 999999,
        ]);
        self::assertSame(404, $this->statusCode());
    }

    private function findCombatant(array $state, int $id): array
    {
        foreach ($state['combatants'] as $c) {
            if ($c['id'] === $id) {
                return $c;
            }
        }
        self::fail("Combatant {$id} not found in state");
    }
}
