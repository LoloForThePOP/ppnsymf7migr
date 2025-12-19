<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\News;
use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    private const DAILY_EMAIL_LIMIT = 5;
    private const REPLY_EMAIL_LIMIT = 2;
    private const THREAD_COOLDOWN_SECONDS = 3600;
    private const DIGEST_TTL_SECONDS = 691200;
    private const DIGEST_MAX_ITEMS = 50;
    private const DIGEST_INDEX_KEY = 'notification_digest_index';
    private const TEMPLATE = 'emails/notification.html.twig';
    private const DIGEST_TEMPLATE = 'emails/notification_digest.html.twig';

    public function __construct(
        private readonly MailerService $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheItemPoolInterface $cache,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function notifyNewsCreated(News $news): void
    {
        $presentation = $news->getProject();
        if ($presentation === null) {
            return;
        }

        $projectOwner = $presentation->getCreator();
        $projectLabel = $this->presentationLabel($presentation);
        $newsExcerpt = $this->excerpt((string) $news->getTextContent());
        $url = $this->urlGenerator->generate('edit_show_project_presentation', [
            'stringId' => $presentation->getStringId(),
            '_fragment' => 'news-struct-container',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $recipients = [];
        foreach ($presentation->getFollowers() as $follow) {
            $user = $follow->getUser();
            if ($user === null || $user->getId() === null) {
                continue;
            }
            if ($projectOwner !== null && $user === $projectOwner) {
                continue;
            }
            $recipients[$user->getId()] = $user;
        }

        foreach ($recipients as $recipient) {
            $lines = [
                sprintf('Une nouvelle actualité a été publiée sur le projet "%s".', $projectLabel),
            ];
            if ($newsExcerpt !== '') {
                $lines[] = sprintf('Extrait : "%s"', $newsExcerpt);
            }

            $this->sendNotification(
                $recipient,
                sprintf('Nouvelle actualité sur %s', $projectLabel),
                [
                    'title' => 'Nouvelle actualité',
                    'messageLines' => $lines,
                    'ctaLabel' => 'Voir l’actualité',
                    'ctaUrl' => $url,
                    'footer' => 'Vous recevez cet e-mail parce que vous suivez ce projet.',
                ]
            );
        }
    }

    public function notifyCommentCreated(Comment $comment): void
    {
        $author = $comment->getCreator();
        if ($author === null) {
            return;
        }

        $sent = [];
        $excerpt = $this->excerpt((string) $comment->getContent());
        $targetUrl = $this->commentTargetUrl($comment);
        $threadKey = $this->commentThreadKey($comment);

        $replyTarget = $this->resolveReplyTarget($comment);
        if ($replyTarget !== null && $replyTarget !== $author) {
            $this->sendUnique(
                $replyTarget,
                $sent,
                sprintf('%s a répondu à votre commentaire', $this->userLabel($author)),
                [
                    'title' => 'Réponse à votre commentaire',
                    'messageLines' => array_filter([
                        sprintf('%s a répondu à votre commentaire.', $this->userLabel($author)),
                        $excerpt !== '' ? sprintf('Réponse : "%s"', $excerpt) : null,
                    ]),
                    'ctaLabel' => 'Voir la réponse',
                    'ctaUrl' => $targetUrl,
                    'footer' => 'Vous recevez cet e-mail parce que vous avez participé à cette discussion.',
                ],
                [
                    'category' => 'reply',
                    'threadKey' => $threadKey,
                ]
            );
        }

        $presentation = $comment->getProjectPresentation();
        if ($presentation !== null) {
            $projectOwner = $presentation->getCreator();
            if ($projectOwner !== null && $projectOwner !== $author) {
                $projectLabel = $this->presentationLabel($presentation);
                $this->sendUnique(
                    $projectOwner,
                    $sent,
                    sprintf('Nouveau commentaire sur votre projet %s', $projectLabel),
                    [
                        'title' => 'Nouveau commentaire',
                        'messageLines' => array_filter([
                            sprintf('%s a laissé un commentaire sur votre projet "%s".', $this->userLabel($author), $projectLabel),
                            $excerpt !== '' ? sprintf('Commentaire : "%s"', $excerpt) : null,
                        ]),
                        'ctaLabel' => 'Voir le commentaire',
                        'ctaUrl' => $targetUrl,
                        'footer' => 'Vous recevez cet e-mail parce que vous êtes propriétaire de ce projet.',
                    ],
                    [
                        'threadKey' => $threadKey,
                    ]
                );
            }
        }

        $news = $comment->getNews();
        if ($news !== null) {
            $project = $news->getProject();
            $projectOwner = $project?->getCreator();
            if ($projectOwner !== null && $projectOwner !== $author) {
                $projectLabel = $this->presentationLabel($project);
                $this->sendUnique(
                    $projectOwner,
                    $sent,
                    sprintf('Nouveau commentaire sur une actualité de %s', $projectLabel),
                    [
                        'title' => 'Nouveau commentaire sur une actualité',
                        'messageLines' => array_filter([
                            sprintf('%s a commenté une actualité de votre projet "%s".', $this->userLabel($author), $projectLabel),
                            $excerpt !== '' ? sprintf('Commentaire : "%s"', $excerpt) : null,
                        ]),
                        'ctaLabel' => 'Voir le commentaire',
                        'ctaUrl' => $targetUrl,
                        'footer' => 'Vous recevez cet e-mail parce que vous êtes propriétaire de ce projet.',
                    ],
                    [
                        'threadKey' => $threadKey,
                    ]
                );
            }

            $newsCreator = $news->getCreator();
            if ($newsCreator !== null && $newsCreator !== $author) {
                $this->sendUnique(
                    $newsCreator,
                    $sent,
                    'Nouveau commentaire sur votre actualité',
                    [
                        'title' => 'Nouveau commentaire sur votre actualité',
                        'messageLines' => array_filter([
                            sprintf('%s a commenté votre actualité.', $this->userLabel($author)),
                            $excerpt !== '' ? sprintf('Commentaire : "%s"', $excerpt) : null,
                        ]),
                        'ctaLabel' => 'Voir le commentaire',
                        'ctaUrl' => $targetUrl,
                        'footer' => 'Vous recevez cet e-mail parce que vous avez publié cette actualité.',
                    ],
                    [
                        'threadKey' => $threadKey,
                    ]
                );
            }
        }

        $article = $comment->getArticle();
        if ($article !== null) {
            $articleCreator = $article->getCreator();
            if ($articleCreator !== null && $articleCreator !== $author) {
                $articleTitle = $article->getTitle();
                $url = $this->urlGenerator->generate('show_article', [
                    'slug' => $article->getSlug(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $this->sendUnique(
                    $articleCreator,
                    $sent,
                    sprintf('Nouveau commentaire sur "%s"', $articleTitle),
                    [
                        'title' => 'Nouveau commentaire',
                        'messageLines' => array_filter([
                            sprintf('%s a commenté votre article "%s".', $this->userLabel($author), $articleTitle),
                            $excerpt !== '' ? sprintf('Commentaire : "%s"', $excerpt) : null,
                        ]),
                        'ctaLabel' => 'Voir le commentaire',
                        'ctaUrl' => $url,
                        'footer' => 'Vous recevez cet e-mail parce que vous êtes l’auteur de cet article.',
                    ],
                    [
                        'threadKey' => $threadKey,
                    ]
                );
            }
        }
    }

    public function sendWeeklyDigest(): array
    {
        $indexItem = $this->cache->getItem(self::DIGEST_INDEX_KEY);
        $userIds = $indexItem->isHit() ? (array) $indexItem->get() : [];

        $sent = 0;
        $skipped = 0;
        $empty = 0;
        $remaining = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId <= 0) {
                continue;
            }

            $entries = $this->getDigestEntries($userId);
            if ($entries === []) {
                $this->clearDigest($userId);
                $empty++;
                continue;
            }

            $user = $this->userRepository->find($userId);
            if ($user === null) {
                $this->clearDigest($userId);
                continue;
            }

            if (!$this->canNotify($user)) {
                $remaining[] = $userId;
                $skipped++;
                continue;
            }

            $periodStart = (new \DateTimeImmutable('-6 days'))->format('d/m/Y');
            $periodEnd = (new \DateTimeImmutable('today'))->format('d/m/Y');
            $subject = sprintf('Votre récapitulatif hebdomadaire (%d)', count($entries));

            $this->mailer->send(
                to: $user->getEmail(),
                subject: $subject,
                template: self::DIGEST_TEMPLATE,
                context: [
                    'recipientName' => $this->userLabel($user),
                    'entries' => $entries,
                    'total' => count($entries),
                    'periodStart' => $periodStart,
                    'periodEnd' => $periodEnd,
                ]
            );

            $this->clearDigest($userId);
            $sent++;
        }

        $indexItem->set(array_values(array_unique($remaining)));
        $indexItem->expiresAfter(self::DIGEST_TTL_SECONDS);
        $this->cache->save($indexItem);

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'empty' => $empty,
        ];
    }

    private function sendUnique(
        User $recipient,
        array &$sent,
        string $subject,
        array $context,
        array $options = []
    ): void {
        $id = $recipient->getId();
        if ($id === null || array_key_exists($id, $sent)) {
            return;
        }

        $this->sendNotification($recipient, $subject, $context, $options);
        $sent[$id] = true;
    }

    private function sendNotification(User $recipient, string $subject, array $context, array $options = []): void
    {
        if (!$this->canNotify($recipient)) {
            return;
        }

        $threadKey = $options['threadKey'] ?? null;
        if (is_string($threadKey) && $this->isThrottled($recipient, $threadKey)) {
            return;
        }

        $category = (string) ($options['category'] ?? 'general');
        if (!$this->withinDailyLimit($recipient, $category)) {
            if (($options['queueDigest'] ?? true) !== false) {
                $this->queueDigest($recipient, $subject, $context);
            }
            return;
        }

        $payload = array_merge([
            'recipientName' => $this->userLabel($recipient),
            'title' => $subject,
            'messageLines' => [],
            'ctaLabel' => 'Voir',
            'ctaUrl' => null,
            'footer' => null,
        ], $context);

        $this->mailer->send(
            to: $recipient->getEmail(),
            subject: $subject,
            template: self::TEMPLATE,
            context: $payload
        );

        $this->incrementDailyCount($recipient, $category);

        if (is_string($threadKey)) {
            $this->markThrottled($recipient, $threadKey);
        }
    }

    private function canNotify(User $user): bool
    {
        return (bool) $user->getEmail()
            && $user->isActive()
            && $user->isVerified();
    }

    private function withinDailyLimit(User $user, string $category): bool
    {
        $total = $this->getDailyCount($user, 'total');
        if ($total < self::DAILY_EMAIL_LIMIT) {
            return true;
        }

        if ($category === 'reply') {
            return $this->getDailyCount($user, 'reply') < self::REPLY_EMAIL_LIMIT;
        }

        return false;
    }

    private function getDailyCount(User $user, string $bucket = 'total'): int
    {
        $item = $this->cache->getItem($this->dailyKey($user, $bucket));
        $count = $item->isHit() ? (int) $item->get() : 0;
        return max(0, $count);
    }

    private function incrementDailyCount(User $user, string $category): void
    {
        $this->incrementDailyBucket($user, 'total');

        if ($category === 'reply') {
            $this->incrementDailyBucket($user, 'reply');
        }
    }

    private function incrementDailyBucket(User $user, string $bucket): void
    {
        $item = $this->cache->getItem($this->dailyKey($user, $bucket));
        $count = $item->isHit() ? (int) $item->get() : 0;
        $count++;
        $item->set($count);
        $item->expiresAfter($this->secondsUntilDayEnd());
        $this->cache->save($item);
    }

    private function dailyKey(User $user, string $bucket): string
    {
        $id = $user->getId() ?? 0;
        $day = (new \DateTimeImmutable('today'))->format('Ymd');
        return sprintf('email_notifications_%s_%s_%d', $day, $bucket, $id);
    }

    private function isThrottled(User $user, string $threadKey): bool
    {
        $item = $this->cache->getItem($this->threadKey($user, $threadKey));
        return $item->isHit();
    }

    private function markThrottled(User $user, string $threadKey): void
    {
        $item = $this->cache->getItem($this->threadKey($user, $threadKey));
        $item->set(1);
        $item->expiresAfter(self::THREAD_COOLDOWN_SECONDS);
        $this->cache->save($item);
    }

    private function threadKey(User $user, string $threadKey): string
    {
        $id = $user->getId() ?? 0;
        return sprintf('notification_thread_%d_%s', $id, $threadKey);
    }

    private function queueDigest(User $recipient, string $subject, array $context): void
    {
        $id = $recipient->getId();
        if ($id === null) {
            return;
        }

        $entry = $this->digestEntryFromContext($subject, $context);
        $item = $this->cache->getItem($this->digestKey($id));
        $entries = $item->isHit() ? (array) $item->get() : [];
        $entries[] = $entry;

        if (count($entries) > self::DIGEST_MAX_ITEMS) {
            $entries = array_slice($entries, -self::DIGEST_MAX_ITEMS);
        }

        $item->set($entries);
        $item->expiresAfter(self::DIGEST_TTL_SECONDS);
        $this->cache->save($item);

        $indexItem = $this->cache->getItem(self::DIGEST_INDEX_KEY);
        $index = $indexItem->isHit() ? (array) $indexItem->get() : [];
        if (!in_array($id, $index, true)) {
            $index[] = $id;
        }
        $indexItem->set(array_values(array_unique($index)));
        $indexItem->expiresAfter(self::DIGEST_TTL_SECONDS);
        $this->cache->save($indexItem);
    }

    private function getDigestEntries(int $userId): array
    {
        $item = $this->cache->getItem($this->digestKey($userId));
        return $item->isHit() ? (array) $item->get() : [];
    }

    private function clearDigest(int $userId): void
    {
        $this->cache->deleteItem($this->digestKey($userId));
    }

    private function digestKey(int $userId): string
    {
        return sprintf('notification_digest_%d', $userId);
    }

    private function digestEntryFromContext(string $subject, array $context): array
    {
        $lines = [];
        foreach (($context['messageLines'] ?? []) as $line) {
            if (!is_string($line) || $line === '') {
                continue;
            }
            $lines[] = $line;
            if (count($lines) >= 2) {
                break;
            }
        }

        return [
            'title' => $context['title'] ?? $subject,
            'lines' => $lines,
            'url' => $context['ctaUrl'] ?? null,
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    private function secondsUntilDayEnd(): int
    {
        $now = new \DateTimeImmutable();
        $end = $now->setTime(23, 59, 59);
        return max(60, $end->getTimestamp() - $now->getTimestamp());
    }

    private function resolveReplyTarget(Comment $comment): ?User
    {
        if ($comment->getRepliedUser() !== null) {
            return $comment->getRepliedUser();
        }

        return $comment->getParent()?->getCreator();
    }

    private function commentThreadKey(Comment $comment): string
    {
        $root = $comment->getParent();
        while ($root !== null && $root->getParent() !== null) {
            $root = $root->getParent();
        }

        $rootId = $root?->getId() ?? $comment->getId() ?? 0;
        $scope = $comment->getCommentedEntityType();
        $entityId = match ($scope) {
            'projectPresentation' => $comment->getProjectPresentation()?->getId(),
            'news' => $comment->getNews()?->getId(),
            'article' => $comment->getArticle()?->getId(),
            default => 0,
        };

        return sprintf('%s-%d-thread-%d', $scope, (int) $entityId, (int) $rootId);
    }

    private function commentTargetUrl(Comment $comment): ?string
    {
        $presentation = $comment->getProjectPresentation();
        if ($presentation !== null) {
            return $this->urlGenerator->generate('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
                '_fragment' => 'comments-struct-container',
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $news = $comment->getNews();
        if ($news !== null) {
            $presentation = $news->getProject();
            if ($presentation !== null) {
                return $this->urlGenerator->generate('edit_show_project_presentation', [
                    'stringId' => $presentation->getStringId(),
                    '_fragment' => 'news-struct-container',
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        $article = $comment->getArticle();
        if ($article !== null && $article->getSlug() !== null) {
            return $this->urlGenerator->generate('show_article', [
                'slug' => $article->getSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return null;
    }

    private function presentationLabel(PPBase $presentation): string
    {
        $title = trim((string) $presentation->getTitle());
        if ($title !== '') {
            return $title;
        }

        return trim((string) $presentation->getGoal());
    }

    private function userLabel(User $user): string
    {
        $username = $user->getUsername();
        return $username ? (string) $username : 'Un utilisateur';
    }

    private function excerpt(string $value, int $limit = 140): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(substr($value, 0, $limit - 1)) . '…';
    }
}
