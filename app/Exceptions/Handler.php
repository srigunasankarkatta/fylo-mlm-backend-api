<?php

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use ApiResponse;

    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // For API routes, return JSON responses
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions and return standardized JSON responses.
     */
    protected function handleApiException(Request $request, Throwable $e)
    {
        if ($e instanceof ValidationException) {
            return $this->validationError($e->errors(), 'Validation failed');
        }

        if ($e instanceof AuthenticationException) {
            return $this->unauthorized($e->getMessage() ?: 'Unauthenticated');
        }

        if ($e instanceof AuthorizationException) {
            return $this->forbidden($e->getMessage() ?: 'Forbidden');
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->notFound('Resource not found');
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->notFound('Endpoint not found');
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->error('Method not allowed', 405);
        }

        if ($e instanceof HttpException) {
            return $this->error($e->getMessage() ?: 'HTTP Error', $e->getStatusCode());
        }

        // For JWT exceptions
        if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
            return $this->unauthorized('Token has expired');
        }

        if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
            return $this->unauthorized('Token is invalid');
        }

        if ($e instanceof \Tymon\JWTAuth\Exceptions\JWTException) {
            return $this->unauthorized('Token is required');
        }

        // For other exceptions, include debug info if in debug mode
        if (config('app.debug')) {
            return $this->error($e->getMessage(), 500, $this->debugExceptionPayload($e));
        }

        return $this->error('Internal server error', 500);
    }
}
