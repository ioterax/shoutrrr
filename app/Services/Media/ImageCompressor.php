<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Support\Facades\Image;
use Illuminate\Image\Image as PendingImage;
use Throwable;

/**
 * Re-encodes an image to fit a target byte limit AND a target platform's accepted mime
 * types, keeping as much quality as the budget allows: it picks the highest encoder
 * quality that fits before downscaling, and prefers WebP over JPEG when the target
 * platform accepts it (WebP is smaller at equal quality and preserves alpha). An image
 * already within the byte limit in an accepted mime is returned untouched; GIFs,
 * oversized-canvas images, and undecodable bytes are always returned untouched (the
 * connectors that hit those cases route GIFs through their own dedicated path instead).
 */
class ImageCompressor
{
    public const int DEFAULT_MAX_PIXELS = 16_000_000;

    private const int QUALITY_CEIL = 92;

    private const int QUALITY_FLOOR = 50;

    private const int QUALITY_STEP = 6;

    private const int DIMENSION_FLOOR = 640;

    private const float DOWNSCALE_FACTOR = 0.85;

    /**
     * @param  int  $maxPixels  Decode guard: images whose pixel count exceeds this are left
     *                          untouched rather than decoded, so a decompression-bomb (tiny
     *                          file, enormous canvas) cannot OOM the publish worker. GD peak
     *                          memory is ~2 x (W x H x 4 bytes) since scale() clones the
     *                          source canvas, so the default is calibrated to stay under a
     *                          256M worker memory_limit while still passing standard ~12MP
     *                          phone photos.
     */
    public function __construct(private readonly int $maxPixels = self::DEFAULT_MAX_PIXELS) {}

    /**
     * @param  list<string>  $allowedMimes  The target platform's accepted image mime types,
     *                                      used to choose the output format (WebP when the
     *                                      platform allows it, otherwise JPEG).
     */
    public function compressToFit(string $bytes, int $maxBytes, string $mime, array $allowedMimes): CompressionResult
    {
        if (strlen($bytes) <= $maxBytes && in_array($mime, $allowedMimes, true)) {
            return CompressionResult::untouched($bytes, $mime);
        }

        if ($mime === 'image/gif') {
            return CompressionResult::untouched($bytes, $mime);
        }

        // Read dimensions from the header only (no canvas allocation): undecodable bytes and
        // pathologically large canvases are refused before any decode, guarding the worker
        // against decompression bombs.
        $info = @getimagesizefromstring($bytes);

        if (! is_array($info) || ($info[0] * $info[1]) > $this->maxPixels) {
            return CompressionResult::untouched($bytes, $mime);
        }

        // Prefer WebP where the platform accepts it: at a given byte budget it keeps
        // noticeably more quality than JPEG (and preserves alpha). The encode attempt
        // (below) falls back to JPEG if the active image driver cannot produce WebP, so
        // this is a preference, not a hard requirement on any one driver.
        $preferWebp = in_array('image/webp', $allowedMimes, true);
        $outMime = 'image/jpeg';

        $longestEdge = max(1, $info[0], $info[1]);

        while (true) {
            $image = Image::fromBytes($bytes)->scale(max(1, $longestEdge), max(1, $longestEdge));

            $encoded = $this->highestQualityThatFits($image, $maxBytes, $preferWebp, $outMime);

            if ($encoded === false) {
                return CompressionResult::untouched($bytes, $mime);
            }

            if (is_string($encoded)) {
                return CompressionResult::compressed($encoded, $outMime);
            }

            $longestEdge = (int) floor($longestEdge * self::DOWNSCALE_FACTOR);

            if ($longestEdge < self::DIMENSION_FLOOR) {
                return CompressionResult::untouched($bytes, $mime);
            }
        }
    }

    /**
     * Try maximum quality first, then find the highest fitting quality with a bounded binary
     * search. Image byte size increases with encoder quality, so the common case still takes
     * one encode and an oversized result takes at most five instead of walking all eight levels.
     *
     * @return string|false|null Encoded bytes, false when encoding failed, or null when the
     *                           image needs another downscale pass.
     */
    private function highestQualityThatFits(PendingImage $image, int $maxBytes, bool $preferWebp, ?string &$outMime): string|false|null
    {
        $qualities = range(self::QUALITY_FLOOR, self::QUALITY_CEIL, self::QUALITY_STEP);
        array_pop($qualities);
        $maximumMime = null;
        $maximumEncoded = $this->encode($image, $preferWebp, self::QUALITY_CEIL, $maximumMime);

        if ($maximumEncoded === null) {
            return false;
        }

        if (strlen($maximumEncoded) <= $maxBytes) {
            $outMime = $maximumMime;

            return $maximumEncoded;
        }

        $lowest = 0;
        $highest = count($qualities) - 1;
        $candidate = null;

        while ($lowest <= $highest) {
            $middle = intdiv($lowest + $highest, 2);
            $candidateMime = null;
            $encoded = $this->encode($image, $preferWebp, $qualities[$middle], $candidateMime);

            if ($encoded === null) {
                return false;
            }

            if (strlen($encoded) <= $maxBytes) {
                $candidate = $encoded;
                $outMime = $candidateMime;
                $lowest = $middle + 1;
            } else {
                $highest = $middle - 1;
            }
        }

        return $candidate;
    }

    /**
     * Encode the pending image pipeline at the given quality, preferring WebP where
     * the platform accepts it and falling back to JPEG when the active driver cannot encode
     * WebP (so a GD build without WebP, or the Imagick driver, still degrades to a valid
     * accepted format rather than shipping the uncompressed original).
     *
     * @param  int<1, 100>  $quality  Encoder quality.
     * @param  string  $outMime  Set by reference to the mime of the format actually encoded.
     * @return string|null The encoded bytes, or null if neither format could be encoded.
     */
    private function encode(PendingImage $image, bool $preferWebp, int $quality, ?string &$outMime = null): ?string
    {
        if ($preferWebp) {
            try {
                $encoded = (string) $image->toWebp()->quality($quality)->toBytes();
                $outMime = 'image/webp';

                return $encoded;
            } catch (Throwable) {
                // WebP unsupported on the active driver/build — fall through to JPEG.
            }
        }

        try {
            $encoded = (string) $image->toJpg()->quality($quality)->toBytes();
            $outMime = 'image/jpeg';

            return $encoded;
        } catch (Throwable) {
            return null;
        }
    }
}
