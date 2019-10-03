<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;

class FileResponderFactoryTest extends TestCase
{
    public function testFactoryProducesMiddlewareWithSessionPersistenceInterfaceService()
    {
        $responseFactory = $this->prophesize(ResponseFactory::class)->reveal();
        $streamFactory = $this->prophesize(StreamFactory::class)->reveal();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get(ResponseFactory::class)->willReturn($responseFactory);
        $container->get(StreamFactory::class)->willReturn($streamFactory);

        $factory = new FileResponderFactory();

        $responder = $factory($container->reveal());

        $this->assertInstanceOf(FileResponder::class, $responder);
    }
}
