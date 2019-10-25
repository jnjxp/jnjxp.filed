<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use Fig\Http\Message\StatusCodeInterface as Code;
use Lmc\HttpConstants\Header;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;
use SplFileInfo;

class FileResponder implements FileResponderInterface
{

    const DATE_FORMAT = 'D, d M Y H:i:s T';

    /**
     * Response factory
     *
     * @var ResponseFactory
     *
     * @access private
     */
    protected $responseFactory;

    /**
     * StreamFactory
     *
     * @var StreamFactory
     *
     * @access protected
     */
    protected $streamFactory;

    /**
     * Accepts Range header?
     *
     * @var bool
     *
     * @access protected
     */
    protected $canServeBytes = true;

    /**
     * Response
     *
     * @var Response
     *
     * @access protected
     */
    protected $response;

    /**
     * Create a file responder
     *
     * @param ResponseFactory $responseFactory PSR-17 ResponseFactoryInterface
     * @param StreamFactory   $streamFactory   PSR-17 StreamFactoryInterface
     *
     * @return void
     *
     * @access public
     */
    public function __construct(ResponseFactory $responseFactory, StreamFactory $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Set if this respondes to range requests
     *
     * @param bool $can true if it can
     *
     * @return void
     *
     * @access public
     */
    public function setCanServeBytes(bool $can) : void
    {
        $this->canServeBytes = $can;
    }

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
    public function respondWithFile(SplFileInfo $file = null, Request $request = null) : Response
    {
        if (! $file || ! $file->isFile()) {
            return $this->fileNotFound();
        }

        $this->response = $this->responseFactory->createResponse();

        $this->withHeaders($file);
        $this->withBody($file);

        if ($request && $this->shouldAddRange($request)) {
            $this->withRange($request, $file);
        }

        return $this->response;
    }

    /**
     * File not found response
     *
     * @return Response
     *
     * @access public
     */
    public function fileNotFound() : Response
    {
        return $this->responseFactory->createResponse(Code::STATUS_NOT_FOUND);
    }

    /**
     * Create response
     *
     * @param int $code
     * @param string $reasonPhrase
     *
     * @return Response
     *
     * @access public
     */
    public function createResponse(int $code = 200, string $reasonPhrase = '') : Response
    {
        return $this->responseFactory->createResponse($code, $reasonPhrase);
    }

    /**
     * Add headers to response
     *
     * @param SplFileInfo $file File to respond with
     *
     * @return void
     *
     * @access protected
     */
    protected function withHeaders(SplFileInfo $file) : void
    {
        $headers = [
            Header::LAST_MODIFIED  => gmdate(self::DATE_FORMAT, $file->getMTime()),
            Header::CONTENT_LENGTH => (string) $file->getSize()
        ];

        $mime = mime_content_type((string) $file);

        if ($mime) {
            $headers[Header::CONTENT_TYPE] = $mime;
        }

        if ($this->canServeBytes()) {
            $headers[Header::ACCEPT_RANGES]  = 'bytes';
        }

        foreach ($headers as $header => $value) {
            $this->response = $this->response->withHeader($header, $value);
        }
    }

    /**
     * Add Body to response
     *
     * @param SplFileInfo $file File to respond with
     *
     * @return void
     *
     * @access protected
     */
    protected function withBody(SplFileInfo $file) : void
    {
        $body = $this->streamFactory->createStreamFromFile((string) $file);
        $this->response = $this->response->withBody($body);
    }

    /**
     * Add Range headers to response
     *
     * @param Request     $request Request for file
     * @param SplFileInfo $file    Requested File
     *
     * @return void
     *
     * @access protected
     */
    protected function withRange(Request $request, SplFileInfo $file) : void
    {
        $range = RequestRange::fromRequest($request, $file->getSize());
        $this->response = $range->applyHeaders($this->response);
    }

    /**
     * Should range headers be added?
     *
     * @param Request $request PSR7 Request
     *
     * @return bool
     *
     * @access protected
     */
    protected function shouldAddRange(Request $request) : bool
    {
        return (
            $this->canServeBytes()
            && $this->isRangeRequest($request)
        );
    }

    /**
     * Is this a range request?
     *
     * @param Request $request PSR7 Request
     *
     * @return bool
     *
     * @access protected
     */
    protected function isRangeRequest(Request $request) : bool
    {
        return RequestRange::isRangeRequest($request);
    }

    /**
     * Can we server bytes to range requests?
     *
     * @return bool
     *
     * @access protected
     */
    protected function canServeBytes() : bool
    {
        return $this->canServeBytes;
    }
}
