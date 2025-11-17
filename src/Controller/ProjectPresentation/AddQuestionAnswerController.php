<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Form\ProjectPresentation\QuestionAnswerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\QuestionAnswerComponent;

final class AddQuestionAnswerController extends AbstractController
{

#[Route(
    '/projects/{stringId}/add-question-answer',
    name: 'pp_add_question_answer',
    methods: ['POST']
)]
public function addQuestionAnswer(
    #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
    Request $request,
    EntityManagerInterface $em,
): Response {

    $this->denyAccessUnlessGranted('edit', $presentation);

    // binding the form to a QuestionAnswerComponent object
    $questionAnswer = QuestionAnswerComponent::createNew('', '');

    $form = $this->createForm(QuestionAnswerType::class, $questionAnswer, [
        'validation_groups' => ['input'], // ensures only title/url are validated
    ]);
    
    $form->handleRequest($request);

    // INVALID → re-render, do NOT redirect
    if ($form->isSubmitted() && !$form->isValid()) {

        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
            'addQuestionAnswerForm' => $form->createView(),

        ]);
    }


    if ($form->isSubmitted() && $form->isValid()) {

        $presentation->getOtherComponents()->addComponent('questions_answers', $questionAnswer);

        $em->flush();

    }

    return $this->redirectToRoute('edit_show_project_presentation', [
        'stringId' => $presentation->getStringId(),
    ]);
    
}





}
