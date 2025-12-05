<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Thin helper around Symfony Mailer with sane defaults and optional Twig templates.
 */
class MailerService
{
    private const DEFAULT_FROM_NAME = 'Propon';

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $defaultFrom,
        private readonly string $defaultContact,
    ) {
    }

    /**
     * Send an email. Use $template + $context or a raw $htmlBody.
     */
    public function send(
        string $to,
        string $subject,
        ?string $template = null,
        array $context = [],
        ?string $htmlBody = null,
        ?string $from = null,
        ?string $fromName = null,
        ?string $replyTo = null
    ): void {
        $fromAddress = new Address($from ?? $this->defaultFrom, $fromName ?? self::DEFAULT_FROM_NAME);
        $toAddress = new Address($to);

        if ($template !== null) {
            $email = (new TemplatedEmail())
                ->from($fromAddress)
                ->to($toAddress)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);
        } else {
            $email = (new Email())
                ->from($fromAddress)
                ->to($toAddress)
                ->subject($subject)
                ->html($htmlBody ?? '');
        }

        if ($replyTo) {
            $email->replyTo($replyTo);
        }

        $this->mailer->send($email);
    }

    /**
     * Shortcut to notify site admins.
     */
    public function mailAdmin(string $subject, string $content, ?string $from = null): void
    {
        $this->send(
            to: $this->defaultContact,
            subject: $subject,
            htmlBody: $content,
            from: $from ?? $this->defaultFrom
        );
    }
}
