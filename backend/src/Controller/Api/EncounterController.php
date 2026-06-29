<?php

namespace App\Controller\Api;

use App\Entity\Encounter;
use App\Entity\EncounterMonster;
use App\Entity\MonsterTemplate;
use App\Repository\EncounterRepository;
use App\Repository\MonsterTemplateRepository;
use App\Service\FileUploader;
use App\Service\GalleryService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/encounters')]
class EncounterController extends AbstractApiController
{
    public function __construct(
        private readonly EncounterRepository $encounters,
        private readonly MonsterTemplateRepository $templates,
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $uploader,
        private readonly GalleryService $gallery,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map($this->serializeSummary(...), $this->encounters->findAllNewestFirst());

        return new JsonResponse($data);
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        return new JsonResponse($this->serializeDetail($encounter));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->jsonError('Name is required', 400);
        }

        // Pre-check + catch the DB constraint to be safe under races.
        if (null !== $this->encounters->findOneByName($name)) {
            return $this->jsonError('Encounter name already exists', 409);
        }

        $encounter = new Encounter();
        $encounter->setName($name);

        $file = $request->files->get('atmospherePicture');
        if ($file) {
            try {
                $encounter->setAtmospherePicture($this->uploader->upload($file));
            } catch (\InvalidArgumentException $e) {
                return $this->jsonError($e->getMessage(), 400);
            }
        }

        $this->em->persist($encounter);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->jsonError('Encounter name already exists', 409);
        }

        return new JsonResponse($this->serializeDetail($encounter), 201);
    }

    #[Route('/{id}', methods: ['PUT', 'POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        $name = $request->request->get('name');
        if (null !== $name && '' !== trim((string) $name)) {
            $name = trim((string) $name);
            $existing = $this->encounters->findOneByName($name);
            if (null !== $existing && $existing->getId() !== $encounter->getId()) {
                return $this->jsonError('Encounter name already exists', 409);
            }
            $encounter->setName($name);
        }

        $file = $request->files->get('atmospherePicture');
        if ($file) {
            try {
                $encounter->setAtmospherePicture($this->uploader->upload($file));
            } catch (\InvalidArgumentException $e) {
                return $this->jsonError($e->getMessage(), 400);
            }
        }

        $encounter->touch();
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->jsonError('Encounter name already exists', 409);
        }

        return new JsonResponse($this->serializeDetail($encounter));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        $this->em->remove($encounter);
        $this->em->flush();

        return new Response(null, 204);
    }

    #[Route('/{id}/monsters', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function addMonster(int $id, Request $request): JsonResponse
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        $monster = new EncounterMonster();
        $monster->setEncounter($encounter);

        // When adding from a template, copy its snapshot as defaults.
        $template = null;
        $templateId = $request->request->get('monsterTemplateId');
        if (null !== $templateId && '' !== (string) $templateId) {
            $template = $this->templates->find((int) $templateId);
            if (!$template) {
                return $this->jsonError('Monster template not found', 404);
            }
            $monster->setMonsterTemplate($template);
            $monster->setName($template->getName());
            $monster->setPicture($template->getPicture());
            $monster->setInitiative($template->getInitiative());
            $monster->setLe($template->getLe());
            $monster->setDescription($template->getDescription());
        }

        // Explicit fields override template defaults.
        $name = $request->request->get('name');
        if (null !== $name && '' !== trim((string) $name)) {
            $monster->setName(trim((string) $name));
        }
        if ($request->request->has('initiative')) {
            $monster->setInitiative((int) $request->request->get('initiative'));
        }
        if ($request->request->has('le')) {
            $monster->setLe((int) $request->request->get('le'));
        }
        if ($request->request->has('description')) {
            $monster->setDescription($this->nullableString($request->request->get('description')));
        }

        if ('' === $monster->getName()) {
            return $this->jsonError('Name is required', 400);
        }

        try {
            $picture = $this->resolvePicture($request, $this->uploader, $this->gallery);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        }
        if (null !== $picture) {
            $monster->setPicture($picture);
        }

        // "Save this monster template" → persist a reusable MonsterTemplate.
        if (null === $template && $this->boolParam($request->request->get('saveTemplate'))) {
            $newTpl = new MonsterTemplate();
            $newTpl->setName($monster->getName());
            $newTpl->setPicture($monster->getPicture());
            $newTpl->setInitiative($monster->getInitiative());
            $newTpl->setLe($monster->getLe());
            $newTpl->setDescription($monster->getDescription());
            $this->em->persist($newTpl);
            $monster->setMonsterTemplate($newTpl);
        }

        $encounter->addMonster($monster);
        $encounter->touch();
        $this->em->persist($monster);
        $this->em->flush();

        return new JsonResponse($this->serializeMonster($monster), 201);
    }

    #[Route('/{id}/monsters/{monsterId}', methods: ['PUT', 'POST'], requirements: ['id' => '\d+', 'monsterId' => '\d+'])]
    public function updateMonster(int $id, int $monsterId, Request $request): JsonResponse
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        $monster = null;
        foreach ($encounter->getMonsters() as $m) {
            if ($m->getId() === $monsterId) {
                $monster = $m;
                break;
            }
        }
        if (!$monster) {
            return $this->jsonError('Monster not found', 404);
        }

        $name = $request->request->get('name');
        if (null !== $name && '' !== trim((string) $name)) {
            $monster->setName(trim((string) $name));
        }
        if ($request->request->has('initiative')) {
            $monster->setInitiative((int) $request->request->get('initiative'));
        }
        if ($request->request->has('le')) {
            $monster->setLe((int) $request->request->get('le'));
        }
        if ($request->request->has('description')) {
            $monster->setDescription($this->nullableString($request->request->get('description')));
        }

        try {
            $picture = $this->resolvePicture($request, $this->uploader, $this->gallery);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        }
        if (null !== $picture) {
            $monster->setPicture($picture);
        }

        $encounter->touch();
        $this->em->flush();

        return new JsonResponse($this->serializeMonster($monster));
    }

    #[Route('/{id}/monsters/{monsterId}', methods: ['DELETE'], requirements: ['id' => '\d+', 'monsterId' => '\d+'])]
    public function deleteMonster(int $id, int $monsterId): Response
    {
        $encounter = $this->encounters->find($id);
        if (!$encounter) {
            return $this->jsonError('Encounter not found', 404);
        }

        $monster = null;
        foreach ($encounter->getMonsters() as $m) {
            if ($m->getId() === $monsterId) {
                $monster = $m;
                break;
            }
        }
        if (!$monster) {
            return $this->jsonError('Monster not found', 404);
        }

        $encounter->removeMonster($monster);
        $encounter->touch();
        $this->em->remove($monster);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function boolParam(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return \in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function serializeSummary(Encounter $e): array
    {
        return [
            'id' => $e->getId(),
            'name' => $e->getName(),
            'atmospherePicture' => $e->getAtmospherePicture(),
            'createdAt' => $e->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $e->getUpdatedAt()->format(\DATE_ATOM),
            'monsterCount' => $e->getMonsters()->count(),
        ];
    }

    private function serializeDetail(Encounter $e): array
    {
        return [
            'id' => $e->getId(),
            'name' => $e->getName(),
            'atmospherePicture' => $e->getAtmospherePicture(),
            'createdAt' => $e->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $e->getUpdatedAt()->format(\DATE_ATOM),
            'monsters' => array_map($this->serializeMonster(...), $e->getMonsters()->toArray()),
        ];
    }

    private function serializeMonster(EncounterMonster $m): array
    {
        return [
            'id' => $m->getId(),
            'name' => $m->getName(),
            'picture' => $m->getPicture(),
            'initiative' => $m->getInitiative(),
            'le' => $m->getLe(),
            'description' => $m->getDescription(),
            'monsterTemplateId' => $m->getMonsterTemplate()?->getId(),
        ];
    }
}
