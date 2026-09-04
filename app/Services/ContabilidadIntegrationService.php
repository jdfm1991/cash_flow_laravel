<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class ContabilidadIntegrationService
{
    /**
     * Generar asiento contable para una transacción
     */
    public function generateEntry(Transaction $transaction): bool
    {
        // Por ahora, solo registrar que se generó el asiento
        Log::info("Asiento contable generado para transacción", [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount,
            'type' => $transaction->type,
        ]);

        // Marcar como contabilizada
        $transaction->update([
            'contabilizada' => true,
            'asiento_contable_id' => time(), // Simulado
        ]);

        return true;
    }

    /**
     * Anular asiento contable
     */
    public function voidEntry(Transaction $transaction): bool
    {
        Log::info("Asiento contable anulado para transacción", [
            'transaction_id' => $transaction->id,
        ]);

        $transaction->update([
            'contabilizada' => false,
            'asiento_contable_id' => null,
        ]);

        return true;
    }

    /**
     * Verificar si la integración está configurada
     */
    public function isConfigured(): bool
    {
        return config('contabilidad.automatica', false);
    }

    /**
     * Probar conexión con el sistema contable
     */
    public function testConnection(): array
    {
        return [
            'success' => true,
            'message' => 'Conexión configurada (modo simulación)',
        ];
    }
}