<?php

namespace App\Service\HomeFeed;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.home_feed_block_provider')]
interface HomeFeedBlockProviderInterface
{
    public function provide(HomeFeedContext $context): ?HomeFeedBlock;
}

