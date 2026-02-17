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
        $context = (string) $request->query->get('context', '');
        $item = (string) $request->query->get('item', '');
        $identifier = (string) $request->query->get('identifier', '');

        $subject = 'Contact';
        if ($context === 'report_abuse' && $item && $identifier) {
            $subject = sprintf('Signalement %s (%s)', $item, $identifier);
        } elseif ($context === 'claim_project_presentation' && $item && $identifier) {
            $subject = sprintf('Demande de modification/retrait %s (%s)', $item, $identifier);
        } elseif ($context === 'feedback') {
            $subject = 'Retour utilisateur';
        }

        $form = $this->createForm(ContactUsType::class, [
            'subject' => $subject,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $body = $data['content'];
            if ($context || $item || $identifier) {
                $body .= "\n\n---\nContexte: {$context}\nObjet: {$item}\nIdentifiant: {$identifier}\n";
            }
            $email = (new Email())
                ->from($data['authorEmail'])
                ->to($this->getParameter('app.email.contact'),)
                ->subject('[Contact] ' . $data['subject'])
                ->text($body);

            $mailer->send($email);
            $this->addFlash('success', 'Merci, votre message a bien été envoyé.');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('static/contact_us.html.twig', [
            'form' => $form->createView(),
            'contactUsPhone' => $this->getParameter('app.phone.contact'),
            'context' => $context,
            'item' => $item,
            'identifier' => $identifier,
        ]);
    }
}
