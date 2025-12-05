<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\ArticleType;
use App\Service\MailerService;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ArticleController extends AbstractController
{

    
    #[Route('/articles', name: 'index_articles')]
    public function index(ArticleRepository $repo): Response
    {
        $articles = $repo->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);

        return $this->render('article/index.html.twig', [
            'articles' => $articles,
        ]);
    }
    

    #[Route('/articles/edit/{id?}', name: 'edit_article')]
    public function edit(
        ArticleRepository $repo,
        ?int $id,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        MailerService $mailer
    ): Response
    {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($id !== null) {
            $article = $repo->find($id);
            if (!$article) {
                throw $this->createNotFoundException('Article introuvable.');
            }

            $initialArticleContentHtml = $article->getContent() ?? '';

            if (!$this->isGranted('user_edit', $article) && !$this->isGranted('admin_edit', $article)) {
                throw $this->createAccessDeniedException();
            }
        } else {
            $article = new Article();
            // fallback to creator if available
            if (method_exists($article, 'setCreator')) {
                $article->setCreator($this->getUser());
            }
            $initialArticleContentHtml = '';
        }

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($article->getId() !== null) {
                $afterSubmissionArticleContentHtml = $article->getContent() ?? '';

                $imageNames1 = $this->extractImageNames($initialArticleContentHtml);
                $imageNames2 = $this->extractImageNames($afterSubmissionArticleContentHtml);
                $deletedImages = array_diff($imageNames1, $imageNames2);

                $deletedImagesDirectory = $this->getParameter('app.image_upload_directory');
                foreach ($deletedImages as $imageName) {
                    $imageFilePath = rtrim($deletedImagesDirectory, '/').'/'.$imageName;
                    if (is_file($imageFilePath)) {
                        @unlink($imageFilePath);
                    }
                }
            } else { // new article
                $sender = $this->getParameter('app.email.noreply');
                $receiver = $this->getParameter('app.email.contact');

                $mailer->send(
                    to: $receiver,
                    subject: 'A New Article Has Been Created',
                    htmlBody: 'Article Title: '.$article->getTitle(),
                    from: $sender
                );
            }

            if ($article->getSlug() === null || trim($article->getSlug()) === '') {
                $article->setSlug(strtolower($slugger->slug($article->getTitle())));
            }

            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('show_article', [

                'slug' => $article->getSlug(),

            ]);

        }

        return $this->render('/article/edit.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
        ]);

    }


    private function extractImageNames(?string $html): array
    {
        $matches = [];
        $pattern = '/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i';
        
        if (preg_match_all($pattern, $html, $matches)) {
            return $matches[1];
        }
        
        return [];
    }
 
    #[Route('/articles/show/{slug}', name: 'show_article')]
    public function show(ArticleRepository $repo, EntityManagerInterface $em, string $slug): Response
    {
        $article = $repo->findOneBy(['slug' => $slug]);
        if (!$article) {
            throw new NotFoundHttpException('Article introuvable.');
        }

        $article->setViewsCount(($article->getViewsCount() ?? 0) + 1);
        $em->flush();

        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }

    





}
