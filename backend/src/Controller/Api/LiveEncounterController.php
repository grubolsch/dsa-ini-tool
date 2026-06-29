<?php

namespace App\Controller\Api;

use App\Entity\Combatant;
use App\Entity\Encounter;
use App\Entity\LiveEncounter;
use App\Entity\StatusEffect;
use App\Repository\EncounterRepository;
use App\Repository\HeroRepository;
use App\Repository\LiveEncounterRepository;
use App\Repository\MonsterTemplateRepository;
use App\Service\CodeGenerator;
use App\Service\FileUploader;
use App\Service\GalleryService;
use App\Service\LiveEncounterEngine;
use App\Service\LiveEncounterSerializer;
use App\Service\StatePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class LiveEncounterController extends AbstractApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EncounterRepository $encounters,
        private readonly HeroRepository $heroes,
        private readonly LiveEncounterRepository $lives,
        private readonly MonsterTemplateRepository $templates,
        private readonly LiveEncounterEngine $engine,
        private readonly LiveEncounterSerializer $serializer,
        private readonly StatePublisher $publisher,
        private readonly CodeGenerator $codeGenerator,
        private readonly FileUploader $uploader,
        private readonly GalleryService $gallery,
    ) {
    }

    #[Route('/encounters/{id}/start', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function start(int $id): JsonResponse
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        $heroes = $this->heroes->findBy([], ['name' => 'ASC']);
        if (0 === \count($heroes)) {
            return $this->jsonError('Cannot start: the party has no heroes', 400);
        }

        $live = new LiveEncounter();
        $live->setEncounter($encounter);
        $live->setCode($this->codeGenerator->generateUnique());
        $live->setRound(1);
        $live->setActiveIndex(0);
        $live->setPhase(LiveEncounter::PHASE_COMBAT);

        $sort = 0;

        // Party combatants from heroes (no health). Source rows are never mutated.
        foreach ($heroes as $hero) {
            $c = new Combatant();
            $c->setSide(Combatant::SIDE_PARTY);
            $c->setName($hero->getName());
            $c->setPicture($hero->getPicture());
            $c->setInitiative($hero->getInitiative());
            $c->setLe(null);
            $c->setMaxLe(null);
            $c->setSortOrder($sort++);
            $this->engine->rollStartInitiative($c);
            $live->addCombatant($c);
            $this->em->persist($c);
        }

        // Enemy combatants from encounter monsters (snapshots).
        foreach ($encounter->getMonsters() as $monster) {
            $c = new Combatant();
            $c->setSide(Combatant::SIDE_ENEMIES);
            $c->setName($monster->getName());
            $c->setPicture($monster->getPicture());
            $c->setInitiative($monster->getInitiative());
            $c->setLe($monster->getLe());
            $c->setMaxLe($monster->getLe());
            $c->setDescription($monster->getDescription());
            $c->setSortOrder($sort++);
            $this->engine->rollStartInitiative($c);
            $live->addCombatant($c);
            $this->em->persist($c);
        }

        $this->em->persist($live);
        $this->em->flush();

        // Freeze round-1 order.
        $this->engine->rebuildOrder($live);
        $this->em->flush();

        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true));
    }

    #[Route('/live/{code}', methods: ['GET'], requirements: ['code' => '[A-Z0-9]{8}'])]
    public function show(string $code, Request $request): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $forDm = '1' === (string) $request->query->get('dm');

        return new JsonResponse($this->serializer->serialize($live, $forDm));
    }

    #[Route('/live/{code}/next-turn', methods: ['POST'], requirements: ['code' => '[A-Z0-9]{8}'])]
    public function nextTurn(string $code): JsonResponse
    {
        return $this->mutate($code, function (LiveEncounter $live): void {
            $this->engine->nextTurn($live);
        });
    }

    #[Route('/live/{code}/next-round', methods: ['POST'], requirements: ['code' => '[A-Z0-9]{8}'])]
    public function nextRound(string $code): JsonResponse
    {
        return $this->mutate($code, function (LiveEncounter $live): void {
            $this->engine->nextRound($live);
        });
    }

    #[Route('/live/{code}/combatants/{cid}', methods: ['PATCH'], requirements: ['code' => '[A-Z0-9]{8}', 'cid' => '\d+'])]
    public function patchCombatant(string $code, int $cid, Request $request): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $combatant = $this->findCombatant($live, $cid);
        if (!$combatant) {
            return $this->jsonError('Combatant not found', 404);
        }

        $data = $this->decodeJson($request);

        if (\array_key_exists('initiative', $data) && null !== $data['initiative']) {
            $this->engine->changeInitiative($combatant, (int) $data['initiative']);
        }

        if (\array_key_exists('le', $data) && null !== $data['le']) {
            $this->engine->changeLe($combatant, (int) $data['le']);
        }

        $this->em->flush();
        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true));
    }

    #[Route('/live/{code}/combatants/{cid}/out-of-combat', methods: ['POST'], requirements: ['code' => '[A-Z0-9]{8}', 'cid' => '\d+'])]
    public function outOfCombat(string $code, int $cid): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $combatant = $this->findCombatant($live, $cid);
        if (!$combatant) {
            return $this->jsonError('Combatant not found', 404);
        }

        if (!$combatant->isParty()) {
            return $this->jsonError('Only heroes can be marked out of combat', 400);
        }

        $combatant->setIsOutOfCombat(true);
        $this->em->flush();
        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true));
    }

    #[Route('/live/{code}/combatants/{cid}/resurrect', methods: ['POST'], requirements: ['code' => '[A-Z0-9]{8}', 'cid' => '\d+'])]
    public function resurrect(string $code, int $cid): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $combatant = $this->findCombatant($live, $cid);
        if (!$combatant) {
            return $this->jsonError('Combatant not found', 404);
        }

        $combatant->setIsOutOfCombat(false);
        $this->em->flush();
        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true));
    }

    #[Route('/live/{code}/combatants', methods: ['POST'], requirements: ['code' => '[A-Z0-9]{8}'])]
    public function addCombatant(string $code, Request $request): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $side = strtoupper((string) $request->request->get('side', Combatant::SIDE_ENEMIES));
        if (!\in_array($side, [Combatant::SIDE_PARTY, Combatant::SIDE_ENEMIES], true)) {
            return $this->jsonError('Invalid side', 400);
        }

        // Optional: load defaults from the monster library. Explicit fields override these.
        $template = null;
        $templateId = $request->request->get('monsterTemplateId');
        if (null !== $templateId && '' !== (string) $templateId) {
            $template = $this->templates->find((int) $templateId);
            if (!$template) {
                return $this->jsonError('Monster template not found', 404);
            }
        }

        // Resolve effective field values (template defaults overridden by explicit submissions).
        $name = trim((string) $request->request->get('name'));
        if ('' === $name && null !== $template) {
            $name = $template->getName();
        }
        if ('' === $name) {
            return $this->jsonError('Name is required', 400);
        }

        $initiative = $request->request->has('initiative')
            ? (int) $request->request->get('initiative')
            : ($template?->getInitiative() ?? 0);

        $description = $this->nullableString($request->request->get('description'));
        if (null === $description && null !== $template) {
            $description = $template->getDescription();
        }

        $le = $request->request->has('le')
            ? (int) $request->request->get('le')
            : ($template?->getLe() ?? 0);

        // Picture: uploaded file / gallery image wins over the template's picture.
        try {
            $picture = $this->resolvePicture($request, $this->uploader, $this->gallery);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        }
        if (null === $picture && null !== $template) {
            $picture = $template->getPicture();
        }

        $quantity = (int) $request->request->get('quantity', 1);
        $quantity = max(1, min(20, $quantity));

        // New combatants sort after all existing ones; each rolls its own INI; plays next round.
        $nextSort = 0;
        foreach ($live->getCombatants() as $existing) {
            $nextSort = max($nextSort, $existing->getSortOrder() + 1);
        }

        for ($i = 0; $i < $quantity; ++$i) {
            $c = new Combatant();
            $c->setSide($side);
            $c->setName($name);
            $c->setInitiative($initiative);
            $c->setDescription($description);

            if (Combatant::SIDE_ENEMIES === $side) {
                $c->setLe($le);
                $c->setMaxLe($le);
            }

            if (null !== $picture) {
                $c->setPicture($picture);
            }

            $c->setSortOrder($nextSort++);
            $this->engine->rollStartInitiative($c);

            $live->addCombatant($c);
            $this->em->persist($c);
        }

        $this->em->flush();

        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true), 201);
    }

    #[Route('/live/{code}/status-effects', methods: ['POST'], requirements: ['code' => '[A-Z0-9]{8}'])]
    public function addStatusEffect(string $code, Request $request): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $data = $this->decodeJson($request);

        $name = trim((string) ($data['name'] ?? ''));
        if ('' === $name) {
            return $this->jsonError('Name is required', 400);
        }

        $description = isset($data['description']) ? (string) $data['description'] : null;
        $duration = (int) ($data['durationRounds'] ?? 1);
        $triggerAtRoundEnd = (bool) ($data['triggerAtRoundEnd'] ?? false);

        $targets = $data['targets'] ?? [];
        $combatantIds = array_map('intval', (array) ($targets['combatantIds'] ?? []));
        $allEnemies = (bool) ($targets['allEnemies'] ?? false);
        $allHeroes = (bool) ($targets['allHeroes'] ?? false);

        // Resolve targets: explicit ids get groupTag null; side-wide get matching tag.
        $applied = [];
        foreach ($live->getCombatants() as $c) {
            $tag = null;
            $shouldApply = false;

            if (\in_array($c->getId(), $combatantIds, true)) {
                $shouldApply = true;
            }
            if ($allEnemies && $c->isEnemy()) {
                $shouldApply = true;
                $tag = StatusEffect::GROUP_ALL_ENEMIES;
            }
            if ($allHeroes && $c->isParty()) {
                $shouldApply = true;
                $tag = StatusEffect::GROUP_ALL_HEROES;
            }

            if (!$shouldApply) {
                continue;
            }

            $effect = new StatusEffect();
            $effect->setName($name);
            $effect->setDescription($description);
            $effect->setDurationRounds(max(1, $duration));
            $effect->setTriggerAtRoundEnd($triggerAtRoundEnd);
            $effect->setGroupTag($tag);
            $c->addStatusEffect($effect);
            $this->em->persist($effect);
            $applied[] = $effect;
        }

        if (0 === \count($applied)) {
            return $this->jsonError('No targets resolved', 400);
        }

        $this->em->flush();
        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true), 201);
    }

    #[Route('/live/{code}/status-effects/{seId}', methods: ['DELETE'], requirements: ['code' => '[A-Z0-9]{8}', 'seId' => '\d+'])]
    public function deleteStatusEffect(string $code, int $seId): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $found = null;
        foreach ($live->getCombatants() as $c) {
            foreach ($c->getStatusEffects() as $effect) {
                if ($effect->getId() === $seId) {
                    $found = $effect;
                    $c->removeStatusEffect($effect);
                    break 2;
                }
            }
        }

        if (!$found) {
            return $this->jsonError('Status effect not found', 404);
        }

        $this->em->remove($found);
        $this->em->flush();
        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true));
    }

    /**
     * Shared mutation wrapper: load by code, apply, flush, publish, return DM state.
     */
    private function mutate(string $code, callable $apply): JsonResponse
    {
        $live = $this->lives->findOneByCode($code);
        if (!$live) {
            return $this->jsonError('Live encounter not found', 404);
        }

        $apply($live);

        $this->em->flush();
        $this->publisher->publish($live);

        return new JsonResponse($this->serializer->serialize($live, true));
    }

    private function findCombatant(LiveEncounter $live, int $cid): ?Combatant
    {
        foreach ($live->getCombatants() as $c) {
            if ($c->getId() === $cid) {
                return $c;
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
