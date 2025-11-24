<?php

namespace App\Service;

use Psr\Container\ContainerInterface;

class KernelServiceLocator
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function get(string $id)
    {
        return $this->container->get($id);
    }
}
