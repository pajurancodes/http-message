<?php

namespace PajuranCodes\Http\Message;

use function is_array;
use function array_key_exists;
use Psr\Http\Message\{
    UriInterface,
    StreamInterface,
    UploadedFileInterface,
    ServerRequestInterface,
};
use PajuranCodes\Http\Message\Request;

/**
 * A server-side HTTP request for server-side applications.
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
class ServerRequest extends Request implements ServerRequestInterface {

    /**
     * A list of deserialized body parameters.
     * 
     * @var null|array|object
     */
    private null|array|object $parsedBody;

    /**
     * An array tree of UploadedFileInterface instances.
     * 
     * @var (UploadedFileInterface|array)[]
     */
    private array $uploadedFiles;

    /**
     *
     * @param mixed[] $attributes (optional) A list of attributes.
     * @param array $serverParams (optional) A list of SAPI parameters.
     * @param null|array|object $parsedBody (optional) A list of deserialized body parameters.
     * @param array $queryParams (optional) A list of query string arguments.
     * @param (UploadedFileInterface|array)[] $uploadedFiles (optional) An array tree of 
     * UploadedFileInterface instances.
     * @param array $cookieParams (optional) A list of key/value pairs representing cookies.
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $body,
        private array $attributes = [],
        array $headers = [],
        private readonly array $serverParams = [],
        null|array|object $parsedBody = null,
        private array $queryParams = [],
        array $uploadedFiles = [],
        private array $cookieParams = [],
        string $protocolVersion = '1.1'
    ) {
        parent::__construct($method, $uri, $body, $headers, $protocolVersion);

        $this->parsedBody = $this->buildParsedBody($parsedBody);
        $this->uploadedFiles = $this->buildUploadedFiles($uploadedFiles);
    }

    /**
     * @inheritDoc
     */
    public function getServerParams() {
        return $this->serverParams;
    }

    /**
     * @inheritDoc
     */
    public function getCookieParams() {
        return $this->cookieParams;
    }

    /**
     * @inheritDoc
     */
    public function withCookieParams(array $cookies) {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getQueryParams() {
        return $this->queryParams;
    }

    /**
     * @inheritDoc
     */
    public function withQueryParams(array $query) {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getUploadedFiles() {
        return $this->uploadedFiles;
    }

    /**
     * @inheritDoc
     */
    public function withUploadedFiles(array $uploadedFiles) {
        $builtUploadedFiles = $this->buildUploadedFiles($uploadedFiles);

        $clone = clone $this;
        $clone->uploadedFiles = $builtUploadedFiles;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getParsedBody() {
        return $this->parsedBody;
    }

    /**
     * @inheritDoc
     */
    public function withParsedBody($data) {
        $builtParsedBody = $this->buildParsedBody($data);

        $clone = clone $this;
        $clone->parsedBody = $builtParsedBody;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes() {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getAttribute($name, $default = null) {
        return $this->hasAttribute($name) ? $this->attributes[$name] : $default;
    }

    /**
     * @inheritDoc
     */
    public function withAttribute($name, $value) {
        $this->validateAttributeName($name);

        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function withoutAttribute($name) {
        $this->validateAttributeName($name);

        $clone = clone $this;

        $clone->removeAttribute($name);

        return $clone;
    }

    /**
     * Build the list of deserialized body parameters.
     *
     * @param null|array|object $parsedBody A list of deserialized body parameters.
     * @return null|array|object The list of deserialized body parameters.
     */
    private function buildParsedBody(null|array|object $parsedBody): null|array|object {
        return $parsedBody;
    }

    /**
     * Build the list of uploaded files.
     *
     * @param (UploadedFileInterface|array)[] $uploadedFiles An array tree of 
     * UploadedFileInterface instances.
     * @return (UploadedFileInterface|array)[] An array tree of 
     * UploadedFileInterface instances, or an empty array.
     */
    private function buildUploadedFiles(array $uploadedFiles): array {
        return $this->validateUploadedFiles($uploadedFiles);
    }

    /**
     * Check if the given list of uploaded files is a normalized 
     * tree, with each leaf an instance of UploadedFileInterface.
     *
     * @param (UploadedFileInterface|array)[] $uploadedFiles An array tree of 
     * UploadedFileInterface instances.
     * @return (UploadedFileInterface|array)[] An array tree of 
     * UploadedFileInterface instances, or an empty array.
     * @throws \InvalidArgumentException One of the tree leafs is not an 
     * instance of UploadedFileInterface.
     */
    private function validateUploadedFiles(array $uploadedFiles): array {
        $validUploadedFiles = [];

        foreach ($uploadedFiles as $key => $item) {
            if (is_array($item)) {
                $validUploadedFiles[$key] = $this->validateUploadedFiles($item);
            } elseif ($item instanceof UploadedFileInterface) {
                $validUploadedFiles[$key] = $item;
            } else {
                throw new \InvalidArgumentException(
                        'The uploaded files list contains an invalid leaf. All leafs in the '
                        . 'list must be instances of "\Psr\Http\Message\UploadedFileInterface".'
                );
            }
        }

        return $validUploadedFiles;
    }

    /**
     * Validate the name of an attribute.
     *
     * @param string $name An attribute name.
     * @return static
     * @throws \InvalidArgumentException An empty attribute name.
     */
    private function validateAttributeName(string $name): static {
        if (empty($name)) {
            throw new \InvalidArgumentException('An attribute name must be provided.');
        }

        return $this;
    }

    /**
     * Check if an attribute exists in the list of attributes.
     *
     * @param string $name An attribute name.
     * @return bool True if the specified attribute name exists, or false otherwise.
     */
    private function hasAttribute(string $name): bool {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Remove an attribute from the list of attributes.
     *
     * @param string $name An attribute name.
     * @return static
     */
    private function removeAttribute(string $name): static {
        if ($this->hasAttribute($name)) {
            unset($this->attributes[$name]);
        }

        return $this;
    }

}
