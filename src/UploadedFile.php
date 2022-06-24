<?php

namespace PajuranCodes\Http\Message;

use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_PARTIAL;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use function is_dir;
use function unlink;
use function dirname;
use function is_writable;
use function file_exists;
use function array_key_exists;
use function file_put_contents;
use Psr\Http\Message\{
    StreamInterface,
    UploadedFileInterface,
};

/**
 * @author pajurancodes
 */
class UploadedFile implements UploadedFileInterface {

    /**
     * A list of error codes, allowed to be associated with the uploaded file.
     * 
     * @link https://www.php.net/manual/en/features.file-upload.errors.php Error Messages Explained
     * 
     * @var string[]
     */
    private const ALLOWED_ERROR_CODES = [
        UPLOAD_ERR_OK => 'There is no error, the file uploaded with success.',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    /**
     * A stream representing the uploaded file.
     *
     * @var StreamInterface
     */
    private readonly StreamInterface $stream;

    /**
     * A file size in bytes, or null if unknown.
     *
     * @var int|null
     */
    private readonly ?int $size;

    /**
     * An error associated with the uploaded file.
     * 
     * The value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     * 
     * @link https://www.php.net/manual/en/features.file-upload.errors.php Error Messages Explained
     *
     * @var int
     */
    private readonly int $error;

    /**
     * A flag to indicate if the uploaded file was 
     * already moved to a new location or not.
     *
     * @var bool
     */
    private bool $uploadedFileAlreadyMoved = false;

    /**
     * 
     * @link https://www.php.net/manual/en/features.file-upload.errors.php Error Messages Explained
     * 
     * @param StreamInterface $stream A stream representing the uploaded file.
     * @param int|null $size (optional) A file size in bytes, or null if unknown.
     * @param int $error (optional) An error associated with the uploaded file.
     * @param string|null $clientFilename (optional) A filename sent by the client, or null.
     * @param string|null $clientMediaType (optional) A media type sent by the client, or null.
     */
    public function __construct(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        private readonly ?string $clientFilename = null,
        private readonly ?string $clientMediaType = null
    ) {
        $this->stream = $this->buildStream($stream);
        $this->size = $this->buildSize($size);
        $this->error = $this->buildError($error);
    }

    /**
     * @inheritDoc
     */
    public function getStream() {
        $this->checkFileAlreadyMoved();

        return $this->stream;
    }

    /**
     * @inheritDoc
     */
    public function moveTo($targetPath) {
        $this
            ->checkFileAlreadyMoved()
            ->validateError()
            ->validateNewLocation($targetPath)
        ;

        $this->copyToNewLocation($targetPath);

        // Get the URI/filename associated with the uploaded file.
        $filename = $this->stream->getMetadata('uri');

        // Close the stream and any underlying resources.
        $this->stream->close();

        // Delete the uploaded file, if exists.
        $this->delete($filename);

        $this->uploadedFileAlreadyMoved = true;
    }

    /**
     * @inheritDoc
     */
    public function getSize() {
        return $this->size;
    }

    /**
     * @inheritDoc
     */
    public function getError() {
        return $this->error;
    }

    /**
     * @inheritDoc
     */
    public function getClientFilename() {
        return $this->clientFilename;
    }

    /**
     * @inheritDoc
     */
    public function getClientMediaType() {
        return $this->clientMediaType;
    }

    /**
     * Build the stream representing the uploaded file.
     *
     * @param StreamInterface $stream A stream representing the uploaded file.
     * @return StreamInterface The stream representing the uploaded file.
     * @throws \InvalidArgumentException The stream is not readable.
     */
    private function buildStream(StreamInterface $stream): StreamInterface {
        if (!$stream->isReadable()) {
            throw new \InvalidArgumentException(
                    'The uploaded file resource is not readable.'
            );
        }

        return $stream;
    }

    /**
     * Build the size of the uploaded file.
     *
     * If a size is not provided, it will be determined 
     * by checking the size of the uploaded file.
     *
     * @param int|null $size A file size in bytes, or null if unknown.
     * @return int|null The file size in bytes, or null if unknown.
     */
    private function buildSize(?int $size): ?int {
        return $size ?? $this->stream->getSize();
    }

    /**
     * Build the error associated with the uploaded file.
     *
     * @param int $error An error associated with the uploaded file.
     * @return int The error associated with the uploaded file.
     * @throws \InvalidArgumentException The given error code is not supported.
     */
    private function buildError(int $error): int {
        if (!array_key_exists($error, self::ALLOWED_ERROR_CODES)) {
            throw new \InvalidArgumentException(
                    'The error code must be one of the UPLOAD_ERR_XXX constants at '
                    . 'https://www.php.net/manual/en/features.file-upload.errors.php'
            );
        }

        return $error;
    }

    /**
     * Check if the uploaded file was already moved to a new location.
     * 
     * @throws \RuntimeException The uploaded file was already moved to a new location.
     * @return static
     */
    private function checkFileAlreadyMoved(): static {
        if ($this->uploadedFileAlreadyMoved) {
            throw new \RuntimeException(
                    'The uploaded file "' . $this->clientFilename . '" could not be '
                    . 'found because it has been already moved to a new location.'
            );
        }

        return $this;
    }

    /**
     * Validate the error associated with the uploaded file.
     * 
     * If the file was not uploaded with success,
     * a runtime exception is thrown.
     * 
     * @throws \RuntimeException There was an error, the file was not uploaded with success.
     * @return static
     */
    private function validateError(): static {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::ALLOWED_ERROR_CODES[$this->error]);
        }

        return $this;
    }

    /**
     * Validate the new location of the uploaded file.
     * 
     * The new location is represented by a target path.
     *
     * @param string $targetPath A path to which to move the uploaded file.
     * @return static
     * @throws \InvalidArgumentException An empty target path.
     * @throws \RuntimeException The parent directory of the new location does not exist.
     * @throws \RuntimeException The parent directory of the new location is not writable.
     */
    private function validateNewLocation(string $targetPath): static {
        /*
         * Validate the target path.
         */

        if (empty($targetPath)) {
            throw new \InvalidArgumentException('A target path must be provided.');
        }

        /*
         * Validate the parent directory of the target path.
         */

        $parentDirectory = dirname($targetPath);

        if (!is_dir($parentDirectory)) {
            throw new \RuntimeException(
                    'The parent directory of the target path '
                    . '"' . $targetPath . '" does not exist.'
            );
        }

        if (!is_writable($parentDirectory)) {
            throw new \RuntimeException(
                    'The parent directory of the target path '
                    . '"' . $targetPath . '" is not writable.'
            );
        }

        return $this;
    }

    /**
     * Copy the uploaded file to a new location.
     * 
     * The new location is represented by a target path.
     *
     * @param string $targetPath A path to which to move the uploaded file.
     * @return static
     * @throws \RuntimeException On any error during the copy operation.
     */
    private function copyToNewLocation(string $targetPath): static {
        $this->stream->rewind();
        $streamContent = $this->stream->getContents();

        $numberOfBytesWritten = file_put_contents($targetPath, $streamContent);

        if ($numberOfBytesWritten === false) {
            throw new \RuntimeException(
                    'The uploaded file "' . $this->clientFilename . '" could '
                    . 'not be moved to the new location "' . $targetPath . '".'
            );
        }

        return $this;
    }

    /**
     * Delete the uploaded file if exists.
     *
     * @param string $filename The URI/filename associated with the uploaded file.
     * @return static
     * @throws \RuntimeException The uploaded file could not be deleted.
     */
    private function delete(string $filename): static {
        if (file_exists($filename) && false === unlink($filename)) {
            throw new \RuntimeException(
                    'The uploaded file "' . $this->clientFilename . '" '
                    . 'could not be removed.'
            );
        }

        return $this;
    }

}
