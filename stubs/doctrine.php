<?php

/**
 * PHPStan stubs for Doctrine (optional dependency).
 */

namespace Doctrine\Persistence;

interface ManagerRegistry
{
    /** @return array<string, ObjectManager> */
    public function getManagers(): array;
    public function resetManager(?string $name = null): ObjectManager;
}

interface ObjectManager
{
    public function isOpen(): bool;
    public function clear(): void;
}
