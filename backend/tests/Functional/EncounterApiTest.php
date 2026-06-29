<?php

namespace App\Tests\Functional;

use App\Entity\MonsterTemplate;

class EncounterApiTest extends ApiTestCase
{
    public function testEncounterUniqueNameReturns409(): void
    {
        $this->multipart('POST', '/api/encounters', ['name' => 'Goblin Ambush']);
        self::assertSame(201, $this->statusCode());

        $this->multipart('POST', '/api/encounters', ['name' => 'Goblin Ambush']);
        self::assertSame(409, $this->statusCode());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Encounter name already exists', $body['error']);
    }

    public function testSaveTemplatePersistsMonsterTemplate(): void
    {
        $enc = $this->multipart('POST', '/api/encounters', ['name' => 'Cave']);
        $id = $enc['id'];

        self::assertCount(0, $this->em->getRepository(MonsterTemplate::class)->findAll());

        $monster = $this->multipart('POST', "/api/encounters/{$id}/monsters", [
            'name' => 'Kobold',
            'initiative' => '7',
            'le' => '8',
            'description' => 'Sneaky',
            'saveTemplate' => 'true',
        ]);

        self::assertSame(201, $this->statusCode());
        self::assertNotNull($monster['monsterTemplateId']);

        $templates = $this->em->getRepository(MonsterTemplate::class)->findAll();
        self::assertCount(1, $templates);
        self::assertSame('Kobold', $templates[0]->getName());
    }

    public function testAddingSameMonsterTwiceCreatesTwoEncounterMonsterRows(): void
    {
        // Create a template to add from.
        $tpl = $this->multipart('POST', '/api/monster-templates', [
            'name' => 'Goblin',
            'initiative' => '8',
            'le' => '15',
        ]);
        $tplId = $tpl['id'];

        $enc = $this->multipart('POST', '/api/encounters', ['name' => 'Ambush']);
        $encId = $enc['id'];

        $this->multipart('POST', "/api/encounters/{$encId}/monsters", ['monsterTemplateId' => (string) $tplId]);
        self::assertSame(201, $this->statusCode());
        $this->multipart('POST', "/api/encounters/{$encId}/monsters", ['monsterTemplateId' => (string) $tplId]);
        self::assertSame(201, $this->statusCode());

        $detail = $this->json('GET', "/api/encounters/{$encId}");
        self::assertCount(2, $detail['monsters']);
        self::assertNotSame($detail['monsters'][0]['id'], $detail['monsters'][1]['id']);
        self::assertSame('Goblin', $detail['monsters'][0]['name']);
        self::assertSame('Goblin', $detail['monsters'][1]['name']);
    }

    public function testEncountersListedNewestFirst(): void
    {
        $this->multipart('POST', '/api/encounters', ['name' => 'First']);
        $this->multipart('POST', '/api/encounters', ['name' => 'Second']);

        $list = $this->json('GET', '/api/encounters');
        self::assertGreaterThanOrEqual(2, \count($list));
        // Newest (higher id / later createdAt) first.
        self::assertSame('Second', $list[0]['name']);
    }
}
