<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * A Flysystem adapter that keeps files in the `stored_files` table, so uploads survive on
 * hosts with a throwaway disk. Selected with PUBLIC_DISK_DRIVER=database; files are served
 * by MediaController at /media/{path}.
 */
class DatabaseFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $urlPrefix = '/media',
        private FinfoMimeTypeDetector $mimeTypes = new FinfoMimeTypeDetector,
    ) {}

    /** Root-relative so images load on whichever host serves the page. */
    public function getUrl(string $path): string
    {
        return rtrim($this->urlPrefix, '/').'/'.ltrim($path, '/');
    }

    public function fileExists(string $path): bool
    {
        return $this->files()->where('path', $path)->exists();
    }

    public function directoryExists(string $path): bool
    {
        return $this->files()->where('path', 'like', $this->directoryPrefix($path).'%')->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $now = Carbon::now();

        $this->files()->upsert([[
            'path' => $path,
            'contents' => base64_encode($contents),
            'mime_type' => $this->mimeTypes->detectMimeType($path, $contents),
            'size' => strlen($contents),
            'visibility' => $config->get('visibility', 'public'),
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['path'], ['contents', 'mime_type', 'size', 'visibility', 'updated_at']);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string) stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $encoded = $this->files()->where('path', $path)->value('contents');

        if ($encoded === null) {
            throw UnableToReadFile::fromLocation($path, 'File not found in stored_files.');
        }

        return (string) base64_decode($encoded, true);
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->files()->where('path', $path)->delete();
    }

    public function deleteDirectory(string $path): void
    {
        $this->files()->where('path', 'like', $this->directoryPrefix($path).'%')->delete();
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Directories are implied by file paths.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->files()->where('path', $path)->update(['visibility' => $visibility]);
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: $this->metadata($path, 'visibility')->visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, mimeType: $this->metadata($path, 'mime_type')->mime_type);
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, lastModified: Carbon::parse($this->metadata($path, 'updated_at')->updated_at)->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, fileSize: (int) $this->metadata($path, 'size')->size);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $this->directoryPrefix($path);
        $directories = [];

        foreach ($this->files()->where('path', 'like', $prefix.'%')->orderBy('path')->get(['path', 'size', 'visibility', 'mime_type', 'updated_at']) as $file) {
            $relative = substr($file->path, strlen($prefix));

            if (! $deep && str_contains($relative, '/')) {
                $directory = $prefix.strstr($relative, '/', true);
                $directories[$directory] ??= new DirectoryAttributes($directory);

                continue;
            }

            yield new FileAttributes($file->path, (int) $file->size, $file->visibility, Carbon::parse($file->updated_at)->getTimestamp(), $file->mime_type);
        }

        yield from array_values($directories);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }

    private function files(): Builder
    {
        return $this->connection->table('stored_files');
    }

    private function directoryPrefix(string $path): string
    {
        return $path === '' ? '' : rtrim($path, '/').'/';
    }

    /** Fetches one metadata column of a stored file, or fails the way Flysystem expects. */
    private function metadata(string $path, string $column): object
    {
        $row = $this->files()->where('path', $path)->first([$column]);

        if ($row === null) {
            throw UnableToRetrieveMetadata::create($path, $column, 'File not found in stored_files.');
        }

        return $row;
    }
}
