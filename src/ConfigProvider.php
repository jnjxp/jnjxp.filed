<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => [
                'factories'  => [
                    FileResponderInterface::class => FileResponderFactory::class
                ],
            ]
        ];
    }
}
