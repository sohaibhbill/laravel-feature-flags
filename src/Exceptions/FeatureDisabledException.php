<?php

namespace MugiWara\FeatureFlags\Exceptions;

use Exception;

class FeatureDisabledException extends Exception
{
    public function __construct(string $message = 'This feature is currently disabled.', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render()
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => $this->getMessage(),
            ], 403);
        }

        abort(403, $this->getMessage());
    }
}