<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SplFileInfo;

interface FileResponderInterface
{
    /**
     * Respond with a file
     *
     * @param SplFileInfo $file    File with which to respond
     * @param Request     $request Request for file
     *
     * @return Response
     *
     * @access public
     */
    public function respondWithFile(SplFileInfo $file = null, Request $request = null) : Response;

    public function fileNotFound() : Response;

    public function createResponse(int $code = 200, string $reasonPhrase = '') : Response;
}
