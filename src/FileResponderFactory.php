<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;
use Micheh\Cache\CacheUtil;

class FileResponderFactory
{
    public function __invoke(ContainerInterface $container) : FileResponder
    {
        return new FileResponder(
            $container->get(ResponseFactory::class),
            $container->get(StreamFactory::class),
            new CacheUtil()
        );
    }
}
