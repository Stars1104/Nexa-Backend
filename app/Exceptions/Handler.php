<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Throwable;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
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

        // Custom handling for validation exceptions
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                Log::warning('Validation failed', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'errors' => $e->errors(),
                    'user_id' => auth()->id(),
                    'user_agent' => $request->userAgent()
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Os dados fornecidos são inválidos.',
                    'errors' => $e->errors()
                ], 422);
            }
        });
    }
}
