<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Lightweight endpoint used by TinyMCE to upload inline images for articles.
 */
class ArticleImageUploadController extends AbstractController
{
    #[Route('/articles/upload-image', name: 'upload_image', methods: ['POST'])]
    public function __invoke(Request $request, Filesystem $fs, SessionInterface $session): Response
    {
        if (!$this->isGranted('ROLE_USER')) {
            throw new AccessDeniedHttpException();
        }

        if (!$this->isCsrfTokenValid('tinymce_image_upload', (string) $request->request->get('_token'))) {
            return new JsonResponse(['message' => 'CSRF Denied'], Response::HTTP_FORBIDDEN);
        }

        // Basic same-origin guard for TinyMCE uploads
        $baseUrl = $request->getScheme().'://'.$request->getHttpHost().$request->getBasePath();
        if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== $baseUrl) {
            return new JsonResponse(['message' => 'Origin Denied'], Response::HTTP_FORBIDDEN);
        }

        // Simple session quota: max 10 uploads per session
        $imagesCount = (int) $session->get('imagesCount', 0);
        if ($imagesCount > 10) {
            return new JsonResponse(['message' => 'Limite de 10 images atteinte pour cette session.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            return new JsonResponse(['error' => 'Aucun fichier'], Response::HTTP_BAD_REQUEST);
        }

        // filename sanity
        $originalName = $file->getClientOriginalName();
        if (preg_match("/([^\w\s\d\~,;:\[\]\(\).À-ÿ6-8\-_])|([\.]{2,})/", $originalName)) {
            return new JsonResponse(['message' => 'Nom de fichier non autorisé.'], Response::HTTP_BAD_REQUEST);
        }

        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return new JsonResponse(['error' => 'Format non pris en charge'], Response::HTTP_BAD_REQUEST);
        }

        // size limit 7 MB
        $maxSize = 7 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            return new JsonResponse(['message' => 'Le poids de l\'image est trop élevé.'], Response::HTTP_BAD_REQUEST);
        }

        $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        if (!in_array($extension, $allowedExtensions, true)) {
            return new JsonResponse(['message' => 'Extensions acceptées : gif, jpg, jpeg, png, webp.'], Response::HTTP_BAD_REQUEST);
        }

        $uploadDir = rtrim($this->getParameter('app.image_upload_directory'), '/');
        if (!$fs->exists($uploadDir)) {
            $fs->mkdir($uploadDir, 0755);
        }

        $slugger = new AsciiSlugger();
        $safeName = $slugger->slug(pathinfo($originalName, PATHINFO_FILENAME));
        $newFilename = sprintf('%s-%s.%s', $safeName, uniqid(), $extension);

        $file->move($uploadDir, $newFilename);

        // derive public path from configured upload dir (assumes it lives under /public)
        $publicPath = sprintf('/media/uploads/articles/images/%s', $newFilename);
        $absoluteUrl = $request->getSchemeAndHttpHost().$request->getBasePath().$publicPath;

        $session->set('imagesCount', $imagesCount + 1);

        return new JsonResponse(['location' => $absoluteUrl], Response::HTTP_OK);
    }
}
