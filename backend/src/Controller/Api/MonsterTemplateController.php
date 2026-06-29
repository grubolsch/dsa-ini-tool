<?php

namespace App\Controller\Api;

use App\Entity\MonsterTemplate;
use App\Repository\MonsterTemplateRepository;
use App\Service\FileUploader;
use App\Service\GalleryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/monster-templates')]
class MonsterTemplateController extends AbstractApiController
{
    public function __construct(
        private readonly MonsterTemplateRepository $templates,
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $uploader,
        private readonly GalleryService $gallery,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $q = $request->query->get('q');
        $data = array_map($this->serialize(...), $this->templates->search($q));

        return new JsonResponse($data);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->jsonError('Name is required', 400);
        }

        $tpl = new MonsterTemplate();
        $tpl->setName($name);
        $tpl->setInitiative((int) $request->request->get('initiative', 0));
        $tpl->setLe((int) $request->request->get('le', 0));
        $tpl->setDescription($this->nullableString($request->request->get('description')));

        try {
            $picture = $this->resolvePicture($request, $this->uploader, $this->gallery);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        }
        if (null !== $picture) {
            $tpl->setPicture($picture);
        }

        $this->em->persist($tpl);
        $this->em->flush();

        return new JsonResponse($this->serialize($tpl), 201);
    }

    #[Route('/{id}', methods: ['PUT', 'POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tpl = $this->templates->find($id);
        if (!$tpl) {
            return $this->jsonError('Monster template not found', 404);
        }

        $name = $request->request->get('name');
        if (null !== $name && '' !== trim((string) $name)) {
            $tpl->setName(trim((string) $name));
        }
        if ($request->request->has('initiative')) {
            $tpl->setInitiative((int) $request->request->get('initiative'));
        }
        if ($request->request->has('le')) {
            $tpl->setLe((int) $request->request->get('le'));
        }
        if ($request->request->has('description')) {
            $tpl->setDescription($this->nullableString($request->request->get('description')));
        }

        try {
            $picture = $this->resolvePicture($request, $this->uploader, $this->gallery);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        }
        if (null !== $picture) {
            $tpl->setPicture($picture);
        }

        $tpl->touch();
        $this->em->flush();

        return new JsonResponse($this->serialize($tpl));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        $tpl = $this->templates->find($id);
        if (!$tpl) {
            return $this->jsonError('Monster template not found', 404);
        }

        $this->em->remove($tpl);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function serialize(MonsterTemplate $tpl): array
    {
        return [
            'id' => $tpl->getId(),
            'name' => $tpl->getName(),
            'picture' => $tpl->getPicture(),
            'initiative' => $tpl->getInitiative(),
            'le' => $tpl->getLe(),
            'description' => $tpl->getDescription(),
        ];
    }
}
