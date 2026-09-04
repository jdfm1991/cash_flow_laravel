<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use App\Models\ImportedTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ClassificationService
{
    /**
     * Obtener categorías por tipo
     */
    public function getCategoriesByType(string $type): array
    {
        return Category::where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'name')
            ->toArray();
    }

    /**
     * Obtener cuentas por categoría
     */
    public function getAccountsByCategory(string $categoryName): array
    {
        $category = Category::where('name', $categoryName)->first();
        
        if (!$category) {
            return [];
        }

        return Account::where('category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Obtener cuentas por tipo de transacción
     */
    public function getAccountsByType(string $type): array
    {
        $categories = Category::where('type', $type)
            ->where('is_active', true)
            ->pluck('id');

        return Account::whereIn('category_id', $categories)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Validar que una cuenta pertenece a una categoría
     */
    public function validateAccountCategory(int $accountId, string $categoryName): bool
    {
        $account = Account::with('category')->find($accountId);
        
        if (!$account || !$account->category) {
            return false;
        }

        return $account->category->name === $categoryName;
    }

    /**
     * Sugerir categoría basada en la descripción
     */
    public function suggestCategory(string $description, string $type): ?string
    {
        $keywords = $this->getKeywords();

        foreach ($keywords as $categoryName => $words) {
            foreach ($words as $word) {
                if (stripos($description, $word) !== false) {
                    // Verificar que la categoría existe y coincide con el tipo
                    $category = Category::where('name', $categoryName)
                        ->where('type', $type)
                        ->where('is_active', true)
                        ->first();
                    
                    if ($category) {
                        return $categoryName;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Obtener palabras clave para sugerencias
     */
    protected function getKeywords(): array
    {
        return [
            // Ingresos
            'Ventas' => ['venta', 'factura', 'cliente', 'pago', 'cancelación', 'depósito'],
            'Cobro de seguros' => ['seguro', 'médico', 'hospital', 'póliza', 'aseguradora'],
            'Alquileres' => ['alquiler', 'renta', 'arrendamiento'],
            'Servicios' => ['servicio', 'consultoría', 'honorario', 'profesional'],
            'Intereses' => ['interés', 'banco', 'financiamiento'],
            
            // Egresos
            'Contribuciones' => ['seniat', 'iva', 'islr', 'alcaldía', 'inces', 'ivss', 'banavih', 'impuesto'],
            'Nómina' => ['nómina', 'sueldo', 'salario', 'cesta', 'ticket', 'prestación', 'utilidad', 'vacación', 'bono', 'liquidación', 'guardia'],
            'Honorarios Médicos' => ['honorario', 'médico', 'especialista', 'consulta', 'cirugía'],
            'Proveedores' => ['proveedor', 'compra', 'suministro', 'material'],
            'Servicios' => ['corpoelec', 'electricidad', 'hidrobolívar', 'agua', 'digitel', 'internet', 'movistar', 'teléfono', 'inter'],
            'Alquileres' => ['alquiler', 'renta', 'local', 'oficina'],
            'Mantenimiento' => ['reparación', 'mantenimiento', 'repuesto', 'técnico'],
            'Transporte' => ['taxi', 'transporte', 'mensajería', 'camino', 'yulmi', 'yurima', 'amilcar'],
            'Suministros' => ['farmacia', 'medicamento', 'insumo', 'hospital', 'laboratorio', 'nitrox', 'tomógrafo', 'desecho'],
        ];
    }

    /**
     * Invalidar caché de clasificación
     */
    public function clearCache(): void
    {
        Cache::forget('classification_categories');
        Cache::forget('classification_accounts');
    }
}