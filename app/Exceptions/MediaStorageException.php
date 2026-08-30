<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class MediaStorageException extends RuntimeException
{
    private function __construct(
        private readonly string $disk,
        private readonly string $operation,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Media storage write failed on disk [{$disk}] during [{$operation}].", previous: $previous);
    }

    public static function writeFailed(string $disk, string $operation, ?Throwable $previous = null): self
    {
        return new self($disk, $operation, $previous);
    }

    /**
     * @return array{disk: string, operation: string}
     */
    public function context(): array
    {
        return [
            'disk' => $this->disk,
            'operation' => $this->operation,
        ];
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'The image storage is temporarily unavailable. Please try again.',
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
