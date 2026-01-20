<?php

namespace App\Twig;

use App\Service\PlatformIconResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PlatformIconExtension extends AbstractExtension
{
    public function __construct(private readonly PlatformIconResolver $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('platform_icon', [$this, 'resolvePlatformIcon']),
            new TwigFunction('platform_label', [$this, 'resolvePlatformLabel']),
        ];
    }

    public function resolvePlatformIcon(?string $platform = null, ?string $sourceUrl = null): ?string
    {
        return $this->resolver->resolve($platform, $sourceUrl);
    }

    public function resolvePlatformLabel(?string $platform = null, ?string $sourceUrl = null): ?string
    {
        return $this->resolver->resolveLabel($platform, $sourceUrl);
    }
}
