<?php

namespace PajuranCodes\Http\Message;

use function implode;
use function is_string;
use function strtolower;
use Psr\Http\Message\{
    UriInterface,
    StreamInterface,
    RequestInterface,
};
use PajuranCodes\Http\Message\Message;
use Fig\Http\Message\RequestMethodInterface as RequestMethod;

/**
 * A client-side HTTP request for client-side applications (HTTP clients).
 * 
 * A HTTP/1.1 request consists of a request-line followed by a 
 * sequence of zero or more header fields (the "header section"), 
 * an empty line indicating the end of the header section, and 
 * an optional message body.
 * 
 * An example of a request:
 * 
 *  POST /path HTTP/1.1
 *  Host: example.com
 * 
 *  foo=bar&baz=bat
 * 
 * @link https://tools.ietf.org/html/rfc7230 Hypertext Transfer Protocol (HTTP/1.1): Message Syntax and Routing
 * @link https://tools.ietf.org/html/rfc7231 Hypertext Transfer Protocol (HTTP/1.1): Semantics and Content
 * 
 * @author pajurancodes
 */
class Request extends Message implements RequestInterface {

    /**
     * A list of allowed HTTP methods.
     *
     * @link https://tools.ietf.org/html/rfc7231#section-4 4. Request Methods
     * @link https://tools.ietf.org/html/rfc5789 PATCH Method for HTTP
     * @link https://www.iana.org/assignments/http-methods/http-methods.xhtml Hypertext Transfer Protocol (HTTP) Method Registry
     * 
     * @var string[]
     */
    private const ALLOWED_METHODS = [
        RequestMethod::METHOD_GET,
        RequestMethod::METHOD_HEAD,
        RequestMethod::METHOD_POST,
        RequestMethod::METHOD_PUT,
        RequestMethod::METHOD_DELETE,
        RequestMethod::METHOD_CONNECT,
        RequestMethod::METHOD_OPTIONS,
        RequestMethod::METHOD_TRACE,
        RequestMethod::METHOD_PATCH,
    ];

    /**
     * A HTTP method.
     *
     * @link https://tools.ietf.org/html/rfc7231#section-4 4. Request Methods
     * @link https://tools.ietf.org/html/rfc5789 PATCH Method for HTTP
     * @link https://www.iana.org/assignments/http-methods/http-methods.xhtml Hypertext Transfer Protocol (HTTP) Method Registry
     * 
     * @var string
     */
    private string $method;

    /**
     * A request target.
     *
     * @link https://tools.ietf.org/html/rfc7230#section-5.3 5.3. Request Target
     *
     * @var string|null
     */
    private ?string $requestTarget = null;

    /**
     * 
     * @param string $method A HTTP method.
     * @param UriInterface $uri A URI.
     */
    public function __construct(
        string $method,
        private UriInterface $uri,
        StreamInterface $body,
        array $headers = [],
        string $protocolVersion = '1.1'
    ) {
        parent::__construct($body, $headers, $protocolVersion);

        $this->method = $this->buildMethod($method);

        /*
         * Update the "Host" header, if the "Host" header is
         * missing or empty and the new URI contains a host component.
         *
         * @see withUri()
         */
        $uriHost = $this->uri->getHost();
        $this->updateHostHeader($uriHost);
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget() {
        /*
         * If a request target already exists, e.g. was 
         * specifically provided by withRequestTarget(), 
         * then return it.
         */
        if (isset($this->requestTarget)) {
            return $this->requestTarget;
        }

        if (!isset($this->uri)) {
            return '/';
        }

        /*
         * By default, if no request-target is specifically composed in the 
         * instance, getRequestTarget() will return the origin-form of the 
         * composed URI (or “/” if no URI is composed).
         * 
         * @link http://www.php-fig.org/psr/psr-7/#14-request-targets-and-uris PSR-7: 1.4 Request Targets and URIs
         * @link https://tools.ietf.org/html/rfc7230#section-5.3.1 5.3.1. origin-form
         */

        // Get the URI path.
        $uriPath = $this->uri->getPath();

        if (empty($uriPath)) {
            return '/';
        }

        // Get the URI query.
        $uriQuery = $this->uri->getQuery();

        // Build the request target in the origin-form.
        $this->requestTarget = $uriPath;

        // Append the query to the request target.
        if (!empty($uriQuery)) {
            $this->requestTarget .= '?' . $uriQuery;
        }

        return $this->requestTarget;
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget($requestTarget) {
        if ($requestTarget !== null && !is_string($requestTarget)) {
            throw new \InvalidArgumentException(
                    'The request target must be null or a string.'
            );
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @inheritDoc
     */
    public function withMethod($method) {
        $builtMethod = $this->buildMethod($method);

        $clone = clone $this;
        $clone->method = $builtMethod;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getUri() {
        return $this->uri;
    }

    /**
     * @inheritDoc
     */
    public function withUri(UriInterface $uri, $preserveHost = false) {
        $clone = clone $this;
        $clone->uri = $uri;

        $uriHost = $clone->uri->getHost();

        if (!$preserveHost) { /* Default behavior. */
            if (!empty($uriHost)) {
                $clone->replaceHeader('Host', $uriHost);
            }
        } else {
            $clone->updateHostHeader($uriHost);
        }

        return $clone;
    }

    /**
     * Build the HTTP method.
     *
     * @param string $method A HTTP method.
     * @return string The HTTP method, uppercased.
     * @throws \InvalidArgumentException An empty HTTP method.
     * @throws \InvalidArgumentException The HTTP method is not supported.
     */
    private function buildMethod(string $method): string {
        if (empty($method)) {
            throw new \InvalidArgumentException('A HTTP method must be provided.');
        }

        if (!$this->isMethodAllowed($method)) {
            throw new \InvalidArgumentException(
                    'The HTTP method "' . $method . '" is not supported. Valid values '
                    . '(case-insensitive): ' . implode(', ', self::ALLOWED_METHODS) . '.'
            );
        }

        return $method;
    }

    /**
     * Check if the given HTTP method is supported.
     *
     * @param string $method A case-insensitive HTTP method.
     * @return bool True if the HTTP method is supported, or false otherwise.
     */
    private function isMethodAllowed(string $method): bool {
        $allowed = false;

        foreach (self::ALLOWED_METHODS as $allowedMethod) {
            if (strtolower($method) === strtolower($allowedMethod)) {
                $allowed = true;
                break;
            }
        }

        return $allowed;
    }

    /**
     * Update the header "Host" with the given value.
     * 
     * If the Host header is missing or empty, and the 
     * new value is not empty, this method MUST update 
     * the Host header in the headers list.
     * 
     * If the Host header is missing or empty, and the 
     * new value is empty, this method MUST NOT update 
     * the Host header in the headers list.
     * 
     * If a Host header is present and non-empty, this 
     * method MUST NOT update the Host header in the 
     * headers list.
     *
     * @see withUri()
     * 
     * @param string $value A value to set.
     * @return static
     */
    private function updateHostHeader(string $value): static {
        if (!empty($value)) {
            if (!$this->hasHeader('Host')) {
                $this->setHeader('Host', $value);
            } elseif (!$this->getHeader('Host')) {
                $this->replaceHeader('Host', $value);
            }
        }

        return $this;
    }

}
