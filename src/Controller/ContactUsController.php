<?php

namespace App\Controller;

use App\Form\ContactUsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactUsController extends AbstractController
{
    #[Route('/contact', name: 'contact_us', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        MailerInterface $mailer,
    ): Response {
        $form = $this->createForm(ContactUsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $email = (new Email())
                ->from($data['authorEmail'])
                ->to($this->getParameter('app.email.contact'),)
                ->subject('[Contact] ' . $data['subject'])
                ->text($data['content']);

            $mailer->send($email);
            $this->addFlash('success', 'Merci, votre message a bien été envoyé.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('static/contact_us.html.twig', [
            'form' => $form->createView(),
            'contactUsPhone' => $this->getParameter('app.phone.contact'),
        ]);
    }
}
