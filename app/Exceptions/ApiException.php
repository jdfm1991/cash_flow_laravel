<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    private array $errors;
    private ?array $data;

    public function __construct(
        string $message,
        array $errors = [],
        int $code = 422,
        ?array $data = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
        $this->data = $data;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->getErrors(),
            'code' => $this->getCode(),
            'data' => $this->getData(),
        ];
    }

    /**
     * Renderizar la excepción para HTTP
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json(
                $this->toArray(),
                $this->getCode()
            );
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}