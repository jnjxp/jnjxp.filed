<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use Fig\Http\Message\StatusCodeInterface as Code;
use InvalidArgumentException;
use Lmc\HttpConstants\Header;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Represent a request range
 */
class RequestRange
{
    /**
     * Preg match template ot get range start and end
     */
    const PARSE_RANGE = '/bytes=\s*(?<start>\d+)-(?<end>\d*)?/i';
    /**
     * Content range sprintf template
     */
    const CONTENT_RANGE = 'bytes %s-%s/%s';

    /**
     * Total payload size
     *
     * @var int
     *
     * @access protected
     */
    protected $total;

    /**
     * Range start
     *
     * @var int
     *
     * @access protected
     */
    protected $start;

    /**
     * Range end
     *
     * @var int
     *
     * @access protected
     */
    protected $end;

    /**
     * Represent a request range
     *
     * @param int $total total payload size
     * @param int $start request start
     * @param int $end   request end
     *
     * @access public
     */
    public function __construct(int $total, int $start, int $end = null)
    {
        $this->total = $total;
        $this->start = $start;
        $this->end   = $end ? $end : $total - 1;
    }

    /**
     * Create from Range header string
     *
     * @param string $header Range header string
     * @param int    $total  total payload size
     *
     * @return self
     *
     * @access public
     * @static
     */
    public static function fromHeader(string $header, int $total) : self
    {
        $range = self::parseHeader($header);
        return new self($total, $range['start'], $range['end']);
    }

    /**
     * Parse a range header
     *
     * @param string $header range header
     *
     * @return array
     * @throws InvalidArgumentException if header canno be parsed
     *
     * @access protected
     * @static
     */
    protected static function parseHeader(string $header) : array
    {
        if (preg_match(self::PARSE_RANGE, $header, $matches)) {
            return [
                'start' => intval($matches['start']),
                'end'   => intval($matches['end']) ?: null
            ];
        }

        throw new InvalidArgumentException("Bad Range header: $header");
    }

    /**
     * fromRequest
     *
     * @param Request $request PSR-7 Request
     * @param int     $total   total payload size
     *
     * @return self
     * @throws InvalidArgumentException if not a range request
     *
     * @access public
     * @static
     */
    public static function fromRequest(Request $request, int $total) : self
    {
        if (! self::isRangeRequest($request)) {
            throw new InvalidArgumentException('No Range header');
        }
        $header = $request->getHeaderLine(Header::RANGE);
        return self::fromHeader($header, $total);
    }

    /**
     * Is this a range request?
     *
     * @param Request $request PSR7 Request to test
     *
     * @return bool
     *
     * @access public
     * @static
     */
    public static function isRangeRequest(Request $request) : bool
    {
        return $request->hasHeader(Header::RANGE);
    }

    /**
     * Add headers to Response
     *
     * @param Response $response the range response
     *
     * @return Response
     *
     * @access public
     */
    public function applyHeaders(Response $response) : Response
    {
        return $this->isSatisfiable()
            ? $this->partial($response)
            : $this->unsatisfiable($response);
    }

    /**
     * Return partial content response
     *
     * @param Response $response PSR7 Response
     *
     * @return Response
     *
     * @access protected
     */
    protected function partial(Response $response) : Response
    {
        return $response
            ->withStatus(Code::STATUS_PARTIAL_CONTENT)
            ->withHeader(Header::CONTENT_RANGE, $this->getContentRange())
            ->withHeader(Header::CONTENT_LENGTH, (string) $this->getContentLength());
    }

    /**
     * Return Unsatisfiable response
     *
     * @param Response $response PSR7 Response
     *
     * @return Response
     *
     * @access protected
     */
    protected function unsatisfiable(Response $response) : Response
    {
        return $response
            ->withStatus(Code::STATUS_RANGE_NOT_SATISFIABLE)
            ->withHeader(Header::CONTENT_RANGE, 'bytes */' . $this->total);
    }

    /**
     * Is requested range valid?
     *
     * @return bool
     *
     * @access protected
     */
    protected function isSatisfiable() : bool
    {
        return $this->start <= $this->total;
    }

    /**
     * Get content range header
     *
     * @return string
     *
     * @access protected
     */
    protected function getContentRange() : string
    {
        return sprintf(
            self::CONTENT_RANGE,
            $this->start,
            $this->end,
            $this->total
        );
    }

    /**
     * Get content length header
     *
     * @return int
     *
     * @access protected
     */
    protected function getContentLength() : int
    {
        return $this->end - $this->start + 1;
    }
}
