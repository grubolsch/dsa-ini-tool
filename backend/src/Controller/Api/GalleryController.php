<?php

namespace App\Controller\Api;

use App\Service\GalleryService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/gallery')]
class GalleryController extends AbstractApiController
{
    public function __construct(
        private readonly GalleryService $gallery,
    ) {
    }

    /** List selectable gallery images (png/jpg/jpeg/bmp). */
    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->gallery->list());
    }

    /**
     * Serve a single gallery image by name. .bmp is converted to PNG on the fly so
     * it renders reliably in every browser; png/jpg/jpeg are streamed as-is.
     */
    #[Route('/image', methods: ['GET'])]
    public function image(Request $request): Response
    {
        $name = (string) $request->query->get('name', '');
        $path = $this->gallery->resolvePath($name);
        if (null === $path) {
            return $this->jsonError('Gallery image not found', 404);
        }

        $ext = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        if ('bmp' === $ext) {
            if (!\function_exists('imagecreatefrombmp')) {
                return $this->jsonError('BMP conversion unavailable on this server', 500);
            }
            $image = @imagecreatefrombmp($path);
            if (false === $image) {
                return $this->jsonError('Failed to read BMP image', 500);
            }
            ob_start();
            imagepng($image);
            imagedestroy($image);
            $png = (string) ob_get_clean();

            $response = new Response($png, 200, ['Content-Type' => 'image/png']);
            $response->setMaxAge(86400);
            $response->setPublic();

            return $response;
        }

        $response = new BinaryFileResponse($path);
        $response->setMaxAge(86400);
        $response->setPublic();

        return $response;
    }
}
