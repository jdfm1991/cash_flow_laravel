<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    protected float $amount;
    protected float $balance;
    protected string $accountNumber;

    public function __construct(float $amount, float $balance, string $accountNumber = '')
    {
        $this->amount = $amount;
        $this->balance = $balance;
        $this->accountNumber = $accountNumber;

        $message = "Saldo insuficiente. ";
        $message .= "Monto requerido: {$amount}, ";
        $message .= "Saldo disponible: {$balance}";
        
        if ($accountNumber) {
            $message .= " (Cuenta: {$accountNumber})";
        }

        parent::__construct($message, 422);
    }

    /**
     * Obtener el monto requerido
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Obtener el saldo disponible
     */
    public function getBalance(): float
    {
        return $this->balance;
    }

    /**
     * Obtener el número de cuenta
     */
    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    /**
     * Obtener la diferencia faltante
     */
    public function getMissingAmount(): float
    {
        return $this->amount - $this->balance;
    }

    /**
     * Renderizar la excepción para la API
     */
    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [
                'amount' => $this->amount,
                'balance' => $this->balance,
                'missing' => $this->getMissingAmount(),
            ],
        ], 422);
    }
}