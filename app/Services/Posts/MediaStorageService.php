<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Exceptions\MediaStorageException;
use App\Models\PostMedia;
use App\Support\FileStorage;
use App\Support\SafeImageFetcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaStorageService
{
    public function __construct(private readonly SafeImageFetcher $fetcher) {}

    /**
     * Store an uploaded image on the configured disk and create an orphan PostMedia row.
     */
    public function store(string $workspaceId, UploadedFile $file, ?string $altText = null): PostMedia
    {
        $disk = FileStorage::diskName();
        $path = $this->storeUploadedFile($file, 'media/'.$workspaceId, $disk);

        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        return $this->createMedia([
            'workspace_id' => $workspaceId,
            'post_id' => null,
            'disk' => $disk,
            'path' => $path,
            'kind' => 'image',
            'mime' => (string) $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'alt_text' => $altText,
            'position' => 0,
        ], $disk, [$path]);
    }

    /**
     * Download an image from a public URL (SSRF-guarded) and store it as an orphan
     * PostMedia row, mirroring store().
     *
     * @throws RuntimeException if the URL is blocked or the response is not a valid image.
     */
    public function storeFromUrl(string $workspaceId, string $url, ?string $altText = null): PostMedia
    {
        $image = $this->fetcher->fetch($url);

        $extension = match ($image['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };

        $disk = FileStorage::diskName();
        $path = 'media/'.$workspaceId.'/'.Str::uuid()->toString().'.'.$extension;
        $this->putContents($disk, $path, $image['bytes']);

        $dimensions = @getimagesizefromstring($image['bytes']) ?: [null, null];

        return $this->createMedia([
            'workspace_id' => $workspaceId,
            'post_id' => null,
            'disk' => $disk,
            'path' => $path,
            'kind' => 'image',
            'mime' => $image['mime'],
            'size_bytes' => strlen($image['bytes']),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'alt_text' => $altText,
            'position' => 0,
        ], $disk, [$path]);
    }

    /**
     * Store a beautified image: the composed image becomes the post's media,
     * the original source is retained for non-destructive re-editing.
     *
     * @param  array<string, mixed>  $settings
     */
    public function storeBeautified(string $workspaceId, UploadedFile $composed, UploadedFile $source, array $settings, ?string $altText = null): PostMedia
    {
        $disk = FileStorage::diskName();
        $storedPaths = [];

        try {
            $path = $this->storeUploadedFile($composed, 'media/'.$workspaceId, $disk);
            $storedPaths[] = $path;
            $sourcePath = $this->storeUploadedFile($source, 'media/'.$workspaceId, $disk);
            $storedPaths[] = $sourcePath;
        } catch (Throwable $exception) {
            $this->deletePaths($disk, $storedPaths);

            throw $exception;
        }

        $dimensions = @getimagesize($composed->getRealPath()) ?: [null, null];

        return $this->createMedia([
            'workspace_id' => $workspaceId,
            'post_id' => null,
            'disk' => $disk,
            'path' => $path,
            'kind' => 'image',
            'source_disk' => $disk,
            'source_path' => $sourcePath,
            'edit_settings' => $settings,
            'mime' => (string) $composed->getMimeType(),
            'size_bytes' => $composed->getSize(),
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'alt_text' => $altText,
            'position' => 0,
        ], $disk, $storedPaths);
    }

    /**
     * Replace the composed file + settings of an existing beautified media, keeping its source.
     *
     * @param  array<string, mixed>  $settings
     */
    public function replaceBeautified(PostMedia $media, UploadedFile $composed, array $settings, ?string $altText = null): PostMedia
    {
        // Store the new file and commit the row before deleting the old file, so a
        // failed store never leaves the row pointing at a now-missing path.
        $oldPath = $media->path;
        $path = $this->storeUploadedFile($composed, 'media/'.$media->workspace_id, $media->disk);
        $dimensions = @getimagesize($composed->getRealPath()) ?: [null, null];

        try {
            $media->update([
                'path' => $path,
                'edit_settings' => $settings,
                'mime' => (string) $composed->getMimeType(),
                'size_bytes' => $composed->getSize(),
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'alt_text' => $altText,
            ]);
        } catch (Throwable $exception) {
            $this->deletePaths($media->disk, [$path]);

            throw $exception;
        }

        if ($oldPath !== $path) {
            FileStorage::disk($media->disk)->delete($oldPath);
        }

        return $media->refresh();
    }

    private function storeUploadedFile(UploadedFile $file, string $directory, string $disk): string
    {
        try {
            $path = $file->store($directory, $disk);
        } catch (Throwable $exception) {
            throw MediaStorageException::writeFailed($disk, 'uploaded-file', $exception);
        }

        if (! is_string($path) || $path === '') {
            throw MediaStorageException::writeFailed($disk, 'uploaded-file');
        }

        return $path;
    }

    private function putContents(string $disk, string $path, string $contents): void
    {
        try {
            $stored = FileStorage::disk($disk)->put($path, $contents);
        } catch (Throwable $exception) {
            throw MediaStorageException::writeFailed($disk, 'downloaded-image', $exception);
        }

        if (! $stored) {
            throw MediaStorageException::writeFailed($disk, 'downloaded-image');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $storedPaths
     */
    private function createMedia(array $attributes, string $disk, array $storedPaths): PostMedia
    {
        try {
            return PostMedia::create($attributes);
        } catch (Throwable $exception) {
            $this->deletePaths($disk, $storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function deletePaths(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                FileStorage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
