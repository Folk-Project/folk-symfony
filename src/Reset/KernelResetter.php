<?php

declare(strict_types=1);

namespace Folk\Symfony\Reset;

use Folk\Sdk\Reset\ResettableInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Resets Symfony services between requests.
 *
 * Uses the built-in services_resetter which calls reset()
 * on all services tagged with kernel.reset.
 */
final class KernelResetter implements ResettableInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {}

    public function reset(): void
    {
        try {
            $container = $this->kernel->getContainer();
            if ($container->has('services_resetter')) {
                /** @var \Symfony\Contracts\Service\ResetInterface $resetter */
                $resetter = $container->get('services_resetter');
                $resetter->reset();
            }
        } catch (\Throwable) {
        }
    }
}
