<?php

namespace Fisharebest\Webtrees;

class Registry
{
    /**
     * Store or retrieve a PSR-11 container.
     * Was added in webtrees 2.2.x
     *
     * @param ContainerInterface|null $container
     *
     * @return ContainerInterface
     */
    public static function container(ContainerInterface|null $container = null): ContainerInterface {
        throw new \Exception("This is a stub method.");
    }
}
