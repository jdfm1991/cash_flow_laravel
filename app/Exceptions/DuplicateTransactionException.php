<?php

namespace App\Exceptions;

use Exception;

class DuplicateTransactionException extends Exception
{
    protected string $hash;
    protected ?int $existingTransactionId;

    public function __construct(string $hash, ?int $existingTransactionId = null)
    {
        $this->hash = $hash;
        $this->existingTransactionId = $existingTransactionId;

        $message = "Transacción duplicada detectada.";
        
        if ($existingTransactionId) {
            $message .= " Ya existe una transacción con el ID: {$existingTransactionId}";
        }

        parent::__construct($message, 409);
    }

    /**
     * Obtener el hash de la transacción duplicada
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Obtener el ID de la transacción existente
     */
    public function getExistingTransactionId(): ?int
    {
        return $this->existingTransactionId;
    }

    /**
     * Verificar si la transacción ya fue procesada
     */
    public function isAlreadyProcessed(): bool
    {
        return $this->existingTransactionId !== null;
    }

    /**
     * Renderizar la excepción para la API
     */
    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'hash' => $this->hash,
                'existing_transaction_id' => $this->existingTransactionId,
            ],
        ], 409);
    }
}