<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Service\LiveSavePP;
use App\Service\AssessPPScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Form\ProjectPresentation\TextDescriptionType;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TextDescriptionController extends AbstractController
{
    #[Route('/projects/{stringId}/edit/text-description', name: 'edit_pp_text_description', methods: ['POST'])]
    #[IsGranted('edit', subject: 'presentation')]
    public function save(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $entityManager,
        AssessPPScoreService $scoreService,
        #[Autowire(service: 'html_sanitizer.sanitizer.text_description')] HtmlSanitizerInterface $textDescriptionSanitizer,
    ): JsonResponse {
        $form = $this->createForm(TextDescriptionType::class, $presentation);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->json([
                'message' => 'Aucune donnée n’a été transmise.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$form->isValid()) {
            return $this->json([
                'message' => 'Le formulaire contient des erreurs.',
                //'errors' => $this->collectFormErrors($form),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $rawDescription = (string) $form->get('textDescription')->getData();
        $sanitizedDescription = $textDescriptionSanitizer->sanitize($rawDescription);
        $cleanDescription = trim(strip_tags($sanitizedDescription)) === '' ? null : $sanitizedDescription;

        $presentation->setTextDescription($cleanDescription);
        $scoreService->scoreUpdate($presentation);
        $entityManager->flush();

        return $this->json([
            'message' => 'Description enregistrée.',
            'html' => $cleanDescription,
            'plainText' => trim(strip_tags($cleanDescription ?? '')),
            'score' => $presentation->getScore(),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
   /*  private function collectFormErrors(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $name = $origin?->getName() ?? $form->getName();
            $errors[$name][] = $error->getMessage();
        }

        return $errors;
    } */




    /**
     * Allow to inline live save some presentation elements
     */
    #[Route('/project/ajax-inline-save', name: 'live_save_pp', methods: ['POST'])]
    public function ajaxPPLiveSave(LiveSavePP $liveSave, Request $request) {
        
        if ($request->isXmlHttpRequest()) {
            
            session_write_close();

            /* Getting posted data */

            $metadata = json_decode($request->request->get('metadata'), true);

            $entityName = ucfirst($metadata['entity']); //ex : "PPBase"; "Slide"
            $entityId = $metadata['id']; //ex: 2084
            $property = $metadata['property']; //ex : "websites" (websites is a key from the $otherComponents attribute from PPBase entity)

            $subId = isset($metadata["subid"]) ? $metadata["subid"] : null ; //ex: a website id
            $subProperty = isset($metadata["subproperty"]) ? $metadata["subproperty"] : null ; //ex : "url" (url is a key from above mentionned websites array)
            
            $content = trim($request->request->get('content'));

            $liveSave->hydrate($entityName, $entityId, $property, $subId, $subProperty, $content);

            if( ! $liveSave->allowUserAccess() ){

                return new JsonResponse(
                
                    [],
                    Response::HTTP_FORBIDDEN,
                );

            }

            if( ! $liveSave->allowItemAccess() ){

                return new JsonResponse(
                
                    [],
                    Response::HTTP_BAD_REQUEST,
                );

            }

            $validateContent = $liveSave->validateContent();

            if( is_string($validateContent) ){

                return new JsonResponse(
                
                    [
                        'error' =>  $validateContent,
                    ],

                    Response::HTTP_BAD_REQUEST,
                );

            }

            $liveSave->save();

            return  new JsonResponse(true);

        }

        return  new JsonResponse();

    }














}
