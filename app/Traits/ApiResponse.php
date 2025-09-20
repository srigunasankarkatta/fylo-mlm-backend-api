<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;

trait ApiResponse
{
    /**
     * Standard success response for single resource / message.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @param array|null $meta
     * @return \Illuminate\Http\JsonResponse
     */
    public function success($data = null, string $message = null, int $statusCode = 200, $meta = null): JsonResponse
    {
        $payload = [
            'status' => 'success',
            'message' => $message ?? 'OK',
            'data' => $data,
            'meta' => $meta,
        ];

        return response()->json($payload, $statusCode);
    }

    /**
     * Standard paginated response.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection|array $items
     * @param string|null $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function paginated($items, string $message = null, int $statusCode = 200): JsonResponse
    {
        $meta = null;
        $data = $items;

        if ($items instanceof LengthAwarePaginator) {
            $data = $items->items();
            $meta = [
                'pagination' => [
                    'total' => $items->total(),
                    'count' => count($items->items()),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'total_pages' => (int) ceil($items->total() / $items->perPage()),
                ],
            ];
        } elseif ($items instanceof Collection || is_array($items)) {
            // For non-paginated collections we can still provide counts
            $count = is_array($items) ? count($items) : $items->count();
            $meta = [
                'pagination' => [
                    'total' => $count,
                    'count' => $count,
                    'per_page' => $count,
                    'current_page' => 1,
                    'total_pages' => 1,
                ],
            ];
        }

        return $this->success($data, $message ?? 'OK', $statusCode, $meta);
    }

    /**
     * Standard error response (generic).
     *
     * @param string|null $message
     * @param int $statusCode
     * @param array|null $errors
     * @param array|null $meta
     * @return \Illuminate\Http\JsonResponse
     */
    public function error(string $message = null, int $statusCode = 500, $errors = null, $meta = null): JsonResponse
    {
        $payload = [
            'status' => 'error',
            'message' => $message ?? 'An error occurred',
            'errors' => $errors,
            'meta' => $meta,
        ];

        return response()->json($payload, $statusCode);
    }

    /**
     * Validation error response (422).
     *
     * @param array|\Illuminate\Contracts\Support\Arrayable $errors
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function validationError($errors, string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Validation failed', 422, $errors);
    }

    /**
     * Not found response (404).
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function notFound(string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Resource not found', 404);
    }

    /**
     * Unauthorized response (401).
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function unauthorized(string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Unauthenticated', 401);
    }

    /**
     * Forbidden response (403).
     *
     * @param string|null $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function forbidden(string $message = null): JsonResponse
    {
        return $this->error($message ?? 'Forbidden', 403);
    }

    /**
     * Helper to build an error payload with debug info when app.debug = true.
     *
     * @param \Throwable|null $exception
     * @return array|null
     */
    protected function debugExceptionPayload($exception)
    {
        if (!$exception || !config('app.debug')) {
            return null;
        }

        return [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => collect($exception->getTrace())->map(function ($t) {
                return Arr::except($t, ['args']);
            })->all(),
        ];
    }
}
