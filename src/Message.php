<?php

namespace PajuranCodes\Http\Message;

use function implode;
use function is_string;
use function strtolower;
use function array_merge;
use function array_values;
use Psr\Http\Message\{
    StreamInterface,
    MessageInterface,
};

/**
 * @link https://tools.ietf.org/html/rfc7230 Hypertext Transfer Protocol (HTTP/1.1): Message Syntax and Routing
 * @link https://tools.ietf.org/html/rfc7231 Hypertext Transfer Protocol (HTTP/1.1): Semantics and Content
 * 
 * @author pajurancodes
 */
class Message implements MessageInterface {

    /**
     * A list of allowed HTTP protocol versions.
     * 
     * @var string[]
     */
    private const ALLOWED_PROTOCOL_VERSIONS = [
        '1.0',
        '1.1',
        '2'
    ];

    /**
     * A list of headers with case-insensitive header 
     * names, as originally given by the user.
     *
     *  [
     *      'header-name 1' => [
     *          'header-value 1',
     *          'header-value 2',
     *      ],
     *      'header-name 2' => [
     *          'header-value 1',
     *          'header-value 2',
     *      ],
     *  ]
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.2 Header Fields
     * @link https://tools.ietf.org/html/rfc7231#section-5 Request Header Fields
     * @link https://tools.ietf.org/html/rfc7231#section-7 Response Header Fields
     *
     * @var string[][]
     */
    private array $headers = [];

    /**
     * A version of the HTTP protocol.
     * 
     * @var string
     */
    private string $protocolVersion;

    /**
     *
     * @param StreamInterface $body The body of a HTTP message.
     * @param (string|string[])[] $headers (optional) A list of headers with case-insensitive header names.
     * @param string $protocolVersion (optional) A version of the HTTP protocol.
     */
    public function __construct(
        private StreamInterface $body,
        array $headers = [],
        string $protocolVersion = '1.1'
    ) {
        $this->buildHeaders($headers);
        $this->protocolVersion = $this->buildProtocolVersion($protocolVersion);
    }

    /**
     * @inheritDoc
     */
    public function getProtocolVersion() {
        return $this->protocolVersion;
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion($version) {
        $builtProtocolVersion = $this->buildProtocolVersion($version);

        $clone = clone $this;
        $clone->protocolVersion = $builtProtocolVersion;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders() {
        return $this->headers;
    }

    /**
     * @inheritDoc
     */
    public function hasHeader($name) {
        $found = false;

        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($name) === strtolower($headerName)) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name) {
        $value = [];

        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($name) === strtolower($headerName)) {
                $value = $headerValue;
                break;
            }
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine($name) {
        if (!$this->hasHeader($name)) {
            return '';
        }

        $value = $this->getHeader($name);

        return $value ? implode(',', $value) : '';
    }

    /**
     * @inheritDoc
     */
    public function withHeader($name, $value) {
        $this
            ->validateHeaderName($name)
            ->validateHeaderValue($value)
        ;

        $clone = clone $this;

        $clone->replaceHeader($name, $value);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader($name, $value) {
        $this
            ->validateHeaderName($name)
            ->validateHeaderValue($value)
        ;

        $clone = clone $this;

        $clone->extendHeader($name, $value);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader($name) {
        $this->validateHeaderName($name);

        $clone = clone $this;

        $clone->removeHeader($name);

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body) {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    /**
     * Save each header from a list of headers.
     *
     * The header list contains case-insensitive header names.
     * 
     * Each header value can be a string, or an array of strings.
     *
     *  [
     *      'header-name 1' => 'header-value',
     *      'header-name 2' => [
     *          'header-value 1',
     *          'header-value 2',
     *      ],
     *  ]
     *
     * Each header is saved with the case-insensitive
     * name, as originally given by the user.
     * 
     * After saving, the headers list has the following structure:
     *
     *  [
     *      'header-name 1' => [
     *          'header-value 1',
     *          'header-value 2',
     *      ],
     *      'header-name 2' => [
     *          'header-value 1',
     *          'header-value 2',
     *      ],
     *  ]
     *
     * @link https://tools.ietf.org/html/rfc7230#section-3.2 Header Fields
     * @link https://tools.ietf.org/html/rfc7231#section-5 Request Header Fields
     * @link https://tools.ietf.org/html/rfc7231#section-7 Response Header Fields
     *
     * @param (string|string[])[] $headers A list of headers with case-insensitive header names.
     * @return static
     */
    private function buildHeaders(array $headers): static {
        foreach ($headers as $name => $value) {
            $this
                ->validateHeaderName($name)
                ->validateHeaderValue($value)
                ->replaceHeader($name, $value)
            ;
        }

        return $this;
    }

    /**
     * Validate the name of a header.
     *
     * @param string $name The case-insensitive name of a header.
     * @return static
     * @throws \InvalidArgumentException An empty header name.
     */
    private function validateHeaderName(string $name): static {
        if (empty($name)) {
            throw new \InvalidArgumentException('A header name must be provided.');
        }

        return $this;
    }

    /**
     * Validate the value of a header.
     *
     * @param string|string[] $value The value of a header.
     * @return static
     */
    private function validateHeaderValue(string|array $value): static {
        return $this;
    }

    /**
     * Replace the value of a header with a new one.
     *
     * @param string $name The case-insensitive name of a header whose value should be replaced.
     * @param string|string[] $value A header value to set.
     * @return static
     */
    protected function replaceHeader(string $name, string|array $value): static {
        $this
            ->removeHeader($name)
            ->setHeader($name, $value)
        ;

        return $this;
    }

    /**
     * Remove a header.
     *
     * @param string $name The case-insensitive name of a header.
     * @return static
     */
    private function removeHeader(string $name): static {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($name) === strtolower($headerName)) {
                unset($this->headers[$headerName]);
                break;
            }
        }

        return $this;
    }

    /**
     * Set the value of a header.
     *
     * Since the Host field-value is critical information for 
     * handling a request, a user agent SHOULD generate Host 
     * as the first header field following the request-line.
     * 
     * @link https://www.rfc-editor.org/rfc/rfc7230.html#section-5.4 5.4. Host
     * 
     * @param string $name The case-insensitive name of a header.
     * @param string|string[] $value A header value to set.
     * @return static
     */
    protected function setHeader(string $name, string|array $value): static {
        $newValue = $this->convertHeaderValueToArray($value);

        if (strtolower($name) === 'host') {
            $this->headers = [$name => $newValue] + $this->headers;
        } else {
            $this->headers[$name] = $newValue;
        }

        return $this;
    }

    /**
     * Convert the value of a header to an array, if not already.
     * 
     * All empty values are removed from the header value.
     *
     * @param string|string[] $value A header value.
     * @return string[] The header value as array.
     * @throws \UnexpectedValueException The value of one of the array elements is not a string.
     */
    private function convertHeaderValueToArray(string|array $value): array {
        if (is_string($value)) {
            $value = [$value];
        }

        foreach ($value as $itemKey => $itemValue) {
            if (empty($itemValue)) {
                unset($value[$itemKey]);
            }

            if (!is_string($itemValue)) {
                throw new \UnexpectedValueException(
                        'A header value can only be a string.'
                );
            }
        }

        // Reindex the array after all elements with empty values were removed.
        $reindexedValues = array_values($value);

        return $reindexedValues;
    }

    /**
     * Extend the value of a header.
     * 
     * The given value is either merged over the value of an 
     * existing header, or set as the value of a newly created header.
     *
     * @param string $name The case-insensitive name of a header.
     * @param string|string[] $value A header value to merge or set.
     * @return static
     */
    private function extendHeader(string $name, string|array $value): static {
        $newValue = $this->convertHeaderValueToArray($value);

        if ($this->hasHeader($name)) {
            if ($newValue) {
                $oldValue = $this->getHeader($name);
                $newValue = array_merge($oldValue, $newValue);

                $this->replaceHeader($name, $newValue);
            }
        } else {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    /**
     * Build the version of the HTTP protocol.
     *
     * @param string $version A version of the HTTP protocol.
     * @return string The version of the HTTP protocol.
     * @throws \InvalidArgumentException The version of the HTTP protocol is empty.
     * @throws \InvalidArgumentException The version of the HTTP protocol is not supported.
     */
    private function buildProtocolVersion(string $version): string {
        if (empty($version)) {
            throw new \InvalidArgumentException(
                    'A HTTP protocol version must be provided.'
            );
        }

        foreach (self::ALLOWED_PROTOCOL_VERSIONS as $allowedVersion) {
            if ($version === $allowedVersion) {
                return $version;
            }
        }

        throw new \InvalidArgumentException(
                'The HTTP protocol version "' . $version . '" is not supported. '
                . 'Valid values: ' . implode(', ', self::ALLOWED_PROTOCOL_VERSIONS) . '.'
        );
    }

}
