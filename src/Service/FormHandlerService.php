<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class FormHandlerService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param FormInterface $form the already-submitted & validated form
     * @param callable|null $onSuccess callback: function(mixed $data): void
     * @param bool $persist if true, persist() is called on the form data
     * @param bool $flush if true, flush() is called automatically
     */
    public function handle(
        FormInterface $form,
        ?callable $onSuccess = null,
        bool $persist = false,
        bool $flush = true
    ): void {

        $data = $form->getData();

        if ($persist) {
            $this->em->persist($data);
        }

        if ($onSuccess) { //$onSuccess is an anonymous function to do specific treatments on form success
            $onSuccess($data); 
        }

        if ($flush) {
            $this->em->flush();
        }
    }
}
