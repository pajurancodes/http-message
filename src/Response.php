<?php

namespace PajuranCodes\Http\Message;

use function array_key_exists;
use Psr\Http\Message\{
    StreamInterface,
    ResponseInterface,
};
use PajuranCodes\Http\Message\Message;
use Fig\Http\Message\StatusCodeInterface as StatusCode;

/**
 * A HTTP response.
 * 
 * A HTTP/1.1 response consists of a status-line followed by a 
 * sequence of zero or more header fields (the "header section"), 
 * an empty line indicating the end of the header section, and 
 * an optional message body.
 * 
 * An example of a response:
 * 
 *  HTTP/1.1 200 OK
 *  Server: Apache/2.4.1 (Unix)
 * 
 *  foo=bar&baz=bat
 * 
 * @link https://tools.ietf.org/html/rfc7230 Hypertext Transfer Protocol (HTTP/1.1): Message Syntax and Routing
 * @link https://tools.ietf.org/html/rfc7231 Hypertext Transfer Protocol (HTTP/1.1): Semantics and Content
 * @link https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml Hypertext Transfer Protocol (HTTP) Status Code Registry
 * 
 * @author pajurancodes
 */
class Response extends Message implements ResponseInterface {

    /**
     * A list of status codes with their reason phrases.
     * 
     * The list entries correspond to the ones
     * maintained in the "HTTP Status Code Registry".
     * 
     * @link https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml HTTP Status Code Registry
     * @link https://tools.ietf.org/html/rfc7231#section-6 Response Status Codes
     * 
     * @var string[]
     */
    private const STATUS_CODES = [
        /*
         * =================
         * Informational 1xx
         * =================
         */
        StatusCode::STATUS_CONTINUE => 'Continue',
        StatusCode::STATUS_SWITCHING_PROTOCOLS => 'Switching Protocols',
        StatusCode::STATUS_PROCESSING => 'Processing',
        StatusCode::STATUS_EARLY_HINTS => 'Early Hints',
        // 104-199 Unassigned
        /*
         * ==============
         * Successful 2xx
         * ==============
         */
        StatusCode::STATUS_OK => 'OK',
        StatusCode::STATUS_CREATED => 'Created',
        StatusCode::STATUS_ACCEPTED => 'Accepted',
        StatusCode::STATUS_NON_AUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
        StatusCode::STATUS_NO_CONTENT => 'No Content',
        StatusCode::STATUS_RESET_CONTENT => 'Reset Content',
        StatusCode::STATUS_PARTIAL_CONTENT => 'Partial Content',
        StatusCode::STATUS_MULTI_STATUS => 'Multi-Status',
        StatusCode::STATUS_ALREADY_REPORTED => 'Already Reported',
        // 209-225 Unassigned
        StatusCode::STATUS_IM_USED => 'IM Used',
        // 227-299 Unassigned
        /*
         * ===============
         * Redirection 3xx
         * ===============
         */
        StatusCode::STATUS_MULTIPLE_CHOICES => 'Multiple Choices',
        StatusCode::STATUS_MOVED_PERMANENTLY => 'Moved Permanently',
        StatusCode::STATUS_FOUND => 'Found',
        StatusCode::STATUS_SEE_OTHER => 'See Other',
        StatusCode::STATUS_NOT_MODIFIED => 'Not Modified',
        StatusCode::STATUS_USE_PROXY => 'Use Proxy',
        StatusCode::STATUS_RESERVED => '(Unused)',
        StatusCode::STATUS_TEMPORARY_REDIRECT => 'Temporary Redirect',
        StatusCode::STATUS_PERMANENT_REDIRECT => 'Permanent Redirect',
        // 309-399 Unassigned
        /*
         * =================
         * Client Errors 4xx
         * =================
         */
        StatusCode::STATUS_BAD_REQUEST => 'Bad Request',
        StatusCode::STATUS_UNAUTHORIZED => 'Unauthorized',
        StatusCode::STATUS_PAYMENT_REQUIRED => 'Payment Required',
        StatusCode::STATUS_FORBIDDEN => 'Forbidden',
        StatusCode::STATUS_NOT_FOUND => 'Not Found',
        StatusCode::STATUS_METHOD_NOT_ALLOWED => 'Method Not Allowed',
        StatusCode::STATUS_NOT_ACCEPTABLE => 'Not Acceptable',
        StatusCode::STATUS_PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
        StatusCode::STATUS_REQUEST_TIMEOUT => 'Request Timeout',
        StatusCode::STATUS_CONFLICT => 'Conflict',
        StatusCode::STATUS_GONE => 'Gone',
        StatusCode::STATUS_LENGTH_REQUIRED => 'Length Required',
        StatusCode::STATUS_PRECONDITION_FAILED => 'Precondition Failed',
        StatusCode::STATUS_PAYLOAD_TOO_LARGE => 'Payload Too Large',
        StatusCode::STATUS_URI_TOO_LONG => 'URI Too Long',
        StatusCode::STATUS_UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
        StatusCode::STATUS_RANGE_NOT_SATISFIABLE => 'Range Not Satisfiable',
        StatusCode::STATUS_EXPECTATION_FAILED => 'Expectation Failed',
        StatusCode::STATUS_IM_A_TEAPOT => 'I am a teapot',
        // 419-420 Unassigned
        StatusCode::STATUS_MISDIRECTED_REQUEST => 'Misdirected Request',
        StatusCode::STATUS_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        StatusCode::STATUS_LOCKED => 'Locked',
        StatusCode::STATUS_FAILED_DEPENDENCY => 'Failed Dependency',
        StatusCode::STATUS_TOO_EARLY => 'Too Early',
        StatusCode::STATUS_UPGRADE_REQUIRED => 'Upgrade Required',
        // 427 Unassigned
        StatusCode::STATUS_PRECONDITION_REQUIRED => 'Precondition Required',
        StatusCode::STATUS_TOO_MANY_REQUESTS => 'Too Many Requests',
        // 430 Unassigned
        StatusCode::STATUS_REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
        // 432-450 Unassigned
        StatusCode::STATUS_UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
        // 452-499 Unassigned
        /*
         * =================
         * Server Errors 5xx
         * =================
         */
        StatusCode::STATUS_INTERNAL_SERVER_ERROR => 'Internal Server Error',
        StatusCode::STATUS_NOT_IMPLEMENTED => 'Not Implemented',
        StatusCode::STATUS_BAD_GATEWAY => 'Bad Gateway',
        StatusCode::STATUS_SERVICE_UNAVAILABLE => 'Service Unavailable',
        StatusCode::STATUS_GATEWAY_TIMEOUT => 'Gateway Timeout',
        StatusCode::STATUS_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
        StatusCode::STATUS_VARIANT_ALSO_NEGOTIATES => 'Variant Also Negotiates',
        StatusCode::STATUS_INSUFFICIENT_STORAGE => 'Insufficient Storage',
        StatusCode::STATUS_LOOP_DETECTED => 'Loop Detected',
        // 509 Unassigned
        StatusCode::STATUS_NOT_EXTENDED => 'Not Extended',
        StatusCode::STATUS_NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
        // 512-599 Unassigned
    ];

    /**
     * A status code.
     * 
     * @link https://tools.ietf.org/html/rfc7231#section-6 Response Status Codes
     *
     * @var int
     */
    private int $statusCode;

    /**
     * A reason phrase.
     * 
     * @link https://tools.ietf.org/html/rfc7231#section-6 Response Status Codes
     * 
     * @var string
     */
    private string $reasonPhrase;

    /**
     *
     * @param int $statusCode (optional) A status code.
     * @param string $reasonPhrase (optional) A reason phrase. If none is provided, 
     * the reason phrase corresponding to the given status code is set.
     */
    public function __construct(
        StreamInterface $body,
        int $statusCode = StatusCode::STATUS_OK,
        string $reasonPhrase = '',
        array $headers = [],
        string $protocolVersion = '1.1'
    ) {
        parent::__construct($body, $headers, $protocolVersion);

        $this->statusCode = $this->buildStatusCode($statusCode);
        $this->reasonPhrase = $this->buildReasonPhrase($statusCode, $reasonPhrase);
    }

    /**
     * @inheritDoc
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @inheritDoc
     */
    public function withStatus($code, $reasonPhrase = '') {
        $builtStatusCode = $this->buildStatusCode($code);
        $builtReasonPhrase = $this->buildReasonPhrase($builtStatusCode, $reasonPhrase);

        $clone = clone $this;
        $clone->statusCode = $builtStatusCode;
        $clone->reasonPhrase = $builtReasonPhrase;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    /**
     * Build the status code.
     *
     * @param int $statusCode A status code.
     * @return int The status code.
     * @throws \InvalidArgumentException The status code is not supported.
     */
    private function buildStatusCode(int $statusCode): int {
        if (!array_key_exists($statusCode, self::STATUS_CODES)) {
            throw new \InvalidArgumentException(
                    'The status code must be one of the codes '
                    . 'listed in the Status Code Registry at '
                    . 'https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml'
            );
        }

        return $statusCode;
    }

    /**
     * Build the reason phrase.
     * 
     * If no reason phrase is provided, the reason phrase 
     * corresponding to the given status code is set.
     * 
     * @param int $statusCode A status code.
     * @param string $reasonPhrase A reason phrase.
     * @return string The reason phrase.
     */
    private function buildReasonPhrase(int $statusCode, string $reasonPhrase): string {
        if (empty($reasonPhrase)) {
            return self::STATUS_CODES[$statusCode] ?? '';
        }

        return $reasonPhrase;
    }

}
