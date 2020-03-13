<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use Fig\Http\Message\StatusCodeInterface as Code;
use Lmc\HttpConstants\Header;
use Micheh\Cache\CacheUtil;
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
     * Cache
     *
     * @var CacheUtil
     *
     * @access protected
     */
    protected $cache;

    /**
     * Should PSR7 CacheUtil be used?
     *
     * @var bool
     *
     * @access protected
     */
    protected $cacheEnabled = true;

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
    public function __construct(
        ResponseFactory $responseFactory,
        StreamFactory $streamFactory,
        CacheUtil $cache = null
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->cache = $cache;
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
     * Set if CacheUtil should be used
     *
     * @param bool $enabled false to disable cache
     *
     * @return void
     *
     * @access public
     */
    public function setCacheEnabled(bool $enabled) : void
    {
        $this->cacheEnabled = $enabled;
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

        if ($this->isNotModified($request)) {
            return $this->response->withStatus(Code::STATUS_NOT_MODIFIED);
        }

        $this->withBody($file);

        if ($request && $this->shouldAddRange($request)) {
            $this->withRange($request, $file);
        }

        return $this->response;
    }

    /**
     * File not found response
     *
     * @param SplFileInfo $file optional not found body
     *
     * @return Response
     *
     * @access public
     */
    public function fileNotFound(SplFileInfo $file = null) : Response
    {
        $response = $this->responseFactory->createResponse(Code::STATUS_NOT_FOUND);
        if ($this->isCacheEnabled()) {
            $response = $this->cache->withCachePrevention($response);
        }

        if ($file && $file->isFile()) {
            $headers = $this->basicHeaders($file);
            $response = $this->addHeaders($response, $headers);
            $body = $this->streamFactory->createStreamFromFile((string) $file);
            $response = $response->withBody($body);
        }

        return $response;
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
        $headers = $this->basicHeaders($file);
        $headers[Header::LAST_MODIFIED]  = gmdate(self::DATE_FORMAT, $file->getMTime());

        if ($this->canServeBytes()) {
            $headers[Header::ACCEPT_RANGES]  = 'bytes';
        }

        $this->response = $this->addHeaders($this->response, $headers);

        if ($this->isCacheEnabled()) {
            $etag = $this->generateEtag($file);
            $this->response = $this->cache->withCache($this->response);
            $this->response = $this->cache->withETag($this->response, $etag);
        }
    }

    /**
     * Generate basic headers for file response, length and type
     *
     * @param SplFileInfo $file
     *
     * @return array
     *
     * @access protected
     */
    protected function basicHeaders(SplFileInfo $file) : array
    {
        return array_filter([
            Header::CONTENT_LENGTH => (string) $file->getSize(),
            Header::CONTENT_TYPE => mime_content_type((string) $file)
        ]);
    }

    /**
     * Add headers to a response
     *
     *
     * @param Response $response
     * @param array    $headers
     *
     * @return Response
     *
     * @access protected
     */
    protected function addHeaders(Response $response, array $headers) : Response
    {
        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }
        return $response;
    }

    /**
     * generateEtag
     *
     * Generates Etag from files MTime and path
     *
     * @param SplFileInfo $file file to generate tag for
     *
     * @return string
     *
     * @access protected
     */
    protected function generateEtag(SplFileInfo $file) : string
    {
        return md5($file->getMTime() . (string) $file);
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
     * Is requests response no modified?
     *
     * Is cache enabled and requested file not modified?
     *
     * @param Request $request PSR7 Request
     *
     * @return bool
     *
     * @access protected
     */
    protected function isNotModified(Request $request = null) : bool
    {
        if ($request && $this->isCacheEnabled()) {
            return $this->cache->isNotModified($request, $this->response);
        }
        return false;
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

    /**
     * Is cache enabled?
     *
     * Should we try and use Cache Util? Returns true if utility is present and
     * enable flag is set to true.
     *
     * @return bool
     *
     * @access protected
     */
    protected function isCacheEnabled() : bool
    {
        return $this->cache && $this->cacheEnabled;
    }
}
