<?php

namespace App\EventListener;

use App\Entity\User;
use App\Entity\Profile;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
class ProfileCreatorListener
{
    public function prePersist(User $user, PrePersistEventArgs $event): void
    {
        // If the user already has a profile, skip
        if ($user->getProfile()) {
            return;
        }

        $profile = new Profile();
        $profile->setUser($user);

        // The cascade=["persist"] on the relation ensures the profile is persisted automatically
        $user->setProfile($profile);
    }
}
