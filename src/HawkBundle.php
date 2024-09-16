<?php

declare(strict_types=1);

namespace HawkBundle;

use HawkBundle\DependencyInjection\HawkExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HawkBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new HawkExtension();
    }
}
