<?php

namespace App\Exceptions;

use Exception;

class BusinessRuleException extends Exception
{
    protected string $ruleName;
    protected array $context;

    public function __construct(string $message, string $ruleName = '', array $context = [])
    {
        $this->ruleName = $ruleName;
        $this->context = $context;

        parent::__construct($message, 422);
    }

    public function getRuleName(): string
    {
        return $this->ruleName;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'rule' => $this->ruleName,
                'context' => $this->context,
            ],
        ], 422);
    }
}