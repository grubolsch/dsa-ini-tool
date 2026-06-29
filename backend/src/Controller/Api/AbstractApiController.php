<?php

namespace App\Controller\Api;

use App\Service\FileUploader;
use App\Service\GalleryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractApiController extends AbstractController
{
    protected function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    /**
     * Resolve the picture for a create/edit request. A monster's picture can come
     * from either an uploaded file (`picture`) OR a chosen gallery image
     * (`galleryImage` = the gallery filename). Returns the renderable picture path,
     * or null when neither was supplied (caller should then keep the existing value).
     *
     * @throws \InvalidArgumentException on upload validation failure or unknown gallery image
     */
    protected function resolvePicture(Request $request, FileUploader $uploader, GalleryService $gallery): ?string
    {
        $file = $request->files->get('picture');
        if ($file) {
            return $uploader->upload($file);
        }

        $galleryImage = $request->request->get('galleryImage');
        if (null !== $galleryImage && '' !== (string) $galleryImage) {
            $name = (string) $galleryImage;
            if (null === $gallery->resolvePath($name)) {
                throw new \InvalidArgumentException('Gallery image not found: '.$name);
            }

            return $gallery->publicUrl($name);
        }

        return null;
    }

    /**
     * Decode a JSON request body into an array (empty array if no/invalid body).
     */
    protected function decodeJson(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($data) ? $data : [];
    }
}
