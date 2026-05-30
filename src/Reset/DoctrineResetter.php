<?php

declare(strict_types=1);

namespace Folk\Symfony\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Psr\Container\ContainerInterface;

/**
 * Clears Doctrine EntityManagers between requests to prevent entity memory leaks.
 */
final class DoctrineResetter implements ResettableInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function reset(): void
    {
        try {
            /** @var \Doctrine\Persistence\ManagerRegistry $registry */
            $registry = $this->container->get('doctrine');
            foreach ($registry->getManagers() as $name => $manager) {
                if (!$manager->isOpen()) {
                    $registry->resetManager($name);
                } else {
                    $manager->clear();
                }
            }
        } catch (\Throwable) {
        }
    }
}
