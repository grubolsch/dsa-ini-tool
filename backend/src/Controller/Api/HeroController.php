<?php

namespace App\Controller\Api;

use App\Entity\Hero;
use App\Repository\HeroRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/heroes')]
class HeroController extends AbstractApiController
{
    public function __construct(
        private readonly HeroRepository $heroes,
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $uploader,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map($this->serialize(...), $this->heroes->findBy([], ['name' => 'ASC']));

        return new JsonResponse($data);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $name = trim((string) $request->request->get('name'));
        if ('' === $name) {
            return $this->jsonError('Name is required', 400);
        }

        $hero = new Hero();
        $hero->setName($name);
        $hero->setInitiative((int) $request->request->get('initiative', 0));

        $file = $request->files->get('picture');
        if ($file) {
            try {
                $hero->setPicture($this->uploader->upload($file));
            } catch (\InvalidArgumentException $e) {
                return $this->jsonError($e->getMessage(), 400);
            }
        }

        $this->em->persist($hero);
        $this->em->flush();

        return new JsonResponse($this->serialize($hero), 201);
    }

    #[Route('/{id}', methods: ['PUT', 'POST'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $hero = $this->heroes->find($id);
        if (!$hero) {
            return $this->jsonError('Hero not found', 404);
        }

        $name = $request->request->get('name');
        if (null !== $name && '' !== trim((string) $name)) {
            $hero->setName(trim((string) $name));
        }

        if ($request->request->has('initiative')) {
            $hero->setInitiative((int) $request->request->get('initiative'));
        }

        $file = $request->files->get('picture');
        if ($file) {
            try {
                $hero->setPicture($this->uploader->upload($file));
            } catch (\InvalidArgumentException $e) {
                return $this->jsonError($e->getMessage(), 400);
            }
        }

        $hero->touch();
        $this->em->flush();

        return new JsonResponse($this->serialize($hero));
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): Response
    {
        $hero = $this->heroes->find($id);
        if (!$hero) {
            return $this->jsonError('Hero not found', 404);
        }

        $this->em->remove($hero);
        $this->em->flush();

        return new Response(null, 204);
    }

    private function serialize(Hero $hero): array
    {
        return [
            'id' => $hero->getId(),
            'name' => $hero->getName(),
            'picture' => $hero->getPicture(),
            'initiative' => $hero->getInitiative(),
        ];
    }
}
