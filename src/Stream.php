<?php

namespace PajuranCodes\Http\Message;

use const SEEK_SET;
use function feof;
use function fopen;
use function fstat;
use function fseek;
use function fread;
use function ftell;
use function fclose;
use function fwrite;
use function is_int;
use function is_string;
use function is_resource;
use function array_key_exists;
use function get_resource_type;
use function stream_get_contents;
use function stream_get_meta_data;
use Psr\Http\Message\StreamInterface;

/**
 * @link http://php.net/manual/en/intro.stream.php Streams
 * @link http://php.net/manual/en/wrappers.php Supported Protocols and Wrappers
 * 
 * @author pajurancodes
 */
class Stream implements StreamInterface {

    /**
     * A list of possible access modes.
     *
     * @link http://php.net/manual/en/function.fopen.php fopen (A list of possible modes for read/write ops)
     * 
     * @var string[][]
     */
    private const ALLOWED_ACCESS_MODES = [
        'read' => [
            'r', 'r+', 'w+', 'a+', 'x+', 'c+',
            'rb', 'r+b', 'w+b', 'x+b', 'c+b',
            'rt', 'r+t', 'w+t', 'x+t', 'c+t',
        ],
        'write' => [
            'r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+',
            'r+b', 'wb', 'w+b', 'x+b', 'c+b',
            'r+t', 'w+t', 'x+t', 'c+t',
            'rw',
        ],
    ];

    /**
     * A resource of type "stream".
     *
     * @link https://secure.php.net/manual/en/language.types.resource.php Resources
     * @link https://secure.php.net/manual/en/resource.php List of Resource Types
     *
     * @var resource|null
     */
    private $stream;

    /**
     *
     * @link https://secure.php.net/manual/en/wrappers.php Supported Protocols and Wrappers
     * @link https://secure.php.net/manual/en/function.fopen.php fopen
     *
     * @param string|resource $stream A filename, or an already opened resource of type "stream".
     * @param string $accessMode (optional) An access mode. 'r': Open for reading only.
     */
    public function __construct($stream, string $accessMode = 'r') {
        $this->stream = $this->buildStream($stream, $accessMode);
    }

    /**
     * @inheritDoc
     */
    public function __toString() {
        if (!$this->isReadable()) {
            return '';
        }

        try {
            $this->rewind();
            return $this->getContents();
        } catch (\RuntimeException $exception) {
            return '';
        }
    }

    /**
     * @inheritDoc
     */
    public function close() {
        if (isset($this->stream) && is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    /**
     * @inheritDoc
     */
    public function detach() {
        $stream = $this->stream;
        $this->stream = null;
        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function getSize() {
        if (isset($this->stream) && is_resource($this->stream)) {
            $statistics = fstat($this->stream);

            if (isset($statistics['size'])) {
                return $statistics['size'];
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function tell() {
        if (!isset($this->stream) || !is_resource($this->stream)) {
            throw new \RuntimeException('Stream not found, invalid, or closed.');
        }

        $position = ftell($this->stream);

        // See http://php.net/manual/en/function.ftell.php#refsect1-function.ftell-returnvalues
        if (false === $position || !is_int($position)) {
            throw new \RuntimeException('Invalid current position of the stream read pointer.');
        }

        return $position;
    }

    /**
     * @inheritDoc
     */
    public function eof() {
        if (!isset($this->stream) || !is_resource($this->stream)) {
            return true;
        }

        return feof($this->stream);
    }

    /**
     * @inheritDoc
     */
    public function isSeekable() {
        if (!isset($this->stream) || !is_resource($this->stream)) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);
        return $meta['seekable'];
    }

    /**
     * @inheritDoc
     */
    public function seek($offset, $whence = SEEK_SET) {
        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream not seekable.');
        }

        $seeked = fseek($this->stream, $offset, $whence);

        if (0 !== $seeked) {
            throw new \RuntimeException('Stream seeking failed.');
        }
    }

    /**
     * @inheritDoc
     */
    public function rewind() {
        $this->seek(0);
    }

    /**
     * @inheritDoc
     */
    public function isWritable() {
        if (!isset($this->stream) || !is_resource($this->stream)) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);
        $accessMode = $meta['mode'];

        return $this->isAccessModeAllowed($accessMode, 'write');
    }

    /**
     * @inheritDoc
     */
    public function write($string) {
        if (!$this->isWritable()) {
            throw new \RuntimeException('Stream not writable.');
        }

        $numberOfBytesWritten = fwrite($this->stream, $string);

        if (false === $numberOfBytesWritten) {
            throw new \RuntimeException('The given data could not be written to the stream.');
        }

        return $numberOfBytesWritten;
    }

    /**
     * @inheritDoc
     */
    public function isReadable() {
        if (!isset($this->stream) || !is_resource($this->stream)) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);
        $accessMode = $meta['mode'];

        return $this->isAccessModeAllowed($accessMode, 'read');
    }

    /**
     * @inheritDoc
     */
    public function read($length) {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream not readable.');
        }

        $string = fread($this->stream, $length);

        if (false === $string) {
            throw new \RuntimeException('Data could not be read from the stream.');
        }

        return $string;
    }

    /**
     * @inheritDoc
     */
    public function getContents() {
        if (!$this->isReadable()) {
            throw new \RuntimeException('Stream not readable.');
        }

        $string = stream_get_contents($this->stream);

        if (false === $string) {
            throw new \RuntimeException('Data could not be read from the stream.');
        }

        return $string;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata($key = null) {
        $metadata = stream_get_meta_data($this->stream);

        if (!isset($key)) {
            return $metadata;
        }

        if (array_key_exists($key, $metadata) && isset($metadata[$key])) {
            return $metadata[$key];
        }

        return null;
    }

    /**
     * Build the resource of type "stream".
     *
     * @param string|resource $stream A filename, or an already opened resource of type "stream".
     * @param string $accessMode An access mode. 'r': Open for reading only.
     * @return resource The resource of type "stream".
     * @throws \InvalidArgumentException The first argument is not set.
     * @throws \InvalidArgumentException The first argument is not a string, nor a resource.
     * @throws \InvalidArgumentException The filename is empty.
     * @throws \InvalidArgumentException The access mode is empty.
     * @throws \InvalidArgumentException The access mode is not supported.
     * @throws \RuntimeException No file could be opened from both arguments.
     * @throws \InvalidArgumentException The first argument is a resource, but not of type "stream".
     */
    private function buildStream($stream, string $accessMode) {
        if (!isset($stream)) {
            throw new \InvalidArgumentException(
                    'A filename, or an opened resource must be provided.'
            );
        }

        if (!is_string($stream) && !is_resource($stream)) {
            throw new \InvalidArgumentException(
                    'The provided argument must be a filename, or an opened resource.'
            );
        }

        if (is_string($stream)) {
            if (empty($stream)) {
                throw new \InvalidArgumentException('The provided filename can not be empty.');
            }

            if (empty($accessMode)) {
                throw new \InvalidArgumentException(
                        'An access mode (required by the stream) must be provided.'
                );
            }

            if (!$this->isAccessModeAllowed($accessMode)) {
                throw new \InvalidArgumentException(
                        'The access mode "' . $accessMode . '" '
                        . '(required by the stream) is not supported.'
                );
            }

            /*
             * Open the file specified by the given filename,
             * e.g. create a stream from the filename,
             * e.g. create a resource of type "stream" from the filename.
             */
            try {
                $stream = fopen($stream, $accessMode);
            } catch (\Exception $exception) {
                throw new \RuntimeException(
                        'No file could be opened from the filename "' . $stream . '" '
                        . 'and the access mode "' . $accessMode . '".'
                );
            }
        } elseif (is_resource($stream)) {
            if ('stream' !== get_resource_type($stream)) {
                throw new \InvalidArgumentException(
                        'The provided resource must be an opened resource of type "stream".'
                );
            }
        }

        return $stream;
    }

    /**
     * Check if the given access mode is supported.
     *
     * @param string $accessMode An access mode.
     * @param string|null $accessType (optional) An access type. Possible values: null, 'read', or 'write'.
     * @return bool True if the access mode is supported, or false otherwise.
     * @throws \InvalidArgumentException The access type is not supported.
     */
    private function isAccessModeAllowed(string $accessMode, ?string $accessType = null): bool {
        $allowed = false;

        if (!isset($accessType)) {
            foreach (self::ALLOWED_ACCESS_MODES as $accessTypeItem) {
                foreach ($accessTypeItem as $allowedAccessMode) {
                    if (strtolower($accessMode) === strtolower($allowedAccessMode)) {
                        $allowed = true;
                        break 2;
                    }
                }
            }
        } else {
            if (
                strtolower($accessType) !== 'read' &&
                strtolower($accessType) !== 'write'
            ) {
                throw new \InvalidArgumentException(
                        'The access type can only be null, "read", or "write".'
                );
            }

            foreach (self::ALLOWED_ACCESS_MODES[$accessType] as $allowedAccessMode) {
                if (strtolower($accessMode) === strtolower($allowedAccessMode)) {
                    $allowed = true;
                    break;
                }
            }
        }

        return $allowed;
    }

}
