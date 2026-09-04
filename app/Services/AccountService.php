<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AccountService
{
    /**
     * Obtener todas las cuentas (catálogo global)
     */
    public function getAll(?string $type = null, ?string $search = null)
    {
        $query = Account::with(['category'])
            ->ordered();

        if ($type) {
            $query->whereHas('category', fn($q) => $q->where('type', $type));
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        return $query->get();
    }

    /**
     * Obtener cuentas activas
     */
    public function getAllActive(?string $type = null, ?string $search = null)
    {
        $query = Account::with(['category'])
            ->active()
            ->ordered();

        if ($type) {
            $query->whereHas('category', fn($q) => $q->where('type', $type));
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        return $query->get();
    }

    /**
     * Obtener cuentas globales (catálogo)
     */
    public function getGlobalAccounts(?string $type = null, ?string $search = null)
    {
        $query = Account::with(['category'])
            ->active()
            ->ordered();

        if ($type) {
            $query->whereHas('category', fn($q) => $q->where('type', $type));
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        return $query->get();
    }

    /**
     * Obtener cuentas por tipo (a través de la categoría)
     */
    public function getByType(string $type)
    {
        return Account::whereHas('category', function ($query) use ($type) {
            $query->where('type', $type);
        })
        ->active()
        ->ordered()
        ->get();
    }

    /**
     * Obtener cuentas por categoría
     */
    public function getByCategory(int $categoryId, bool $onlyActive = true)
    {
        $query = Account::where('category_id', $categoryId)
            ->ordered();

        if ($onlyActive) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Obtener cuentas por empresa
     * Nota: Las cuentas son globales, pero filtramos por tipo
     */
    public function getByCompany(int $companyId, ?string $type = null, ?string $search = null)
    {
        $query = Account::with(['category'])
            ->active()
            ->ordered();

        if ($type) {
            $query->whereHas('category', fn($q) => $q->where('type', $type));
        }

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        return $query->get();
    }

    /**
     * Obtener cuenta por ID
     */
    public function findById(int $id): ?Account
    {
        return Account::with(['category'])->find($id);
    }

    /**
     * Buscar cuentas por nombre
     */
    public function search(string $term, ?string $type = null)
    {
        $query = Account::where('name', 'LIKE', "%{$term}%")
            ->active()
            ->ordered();

        if ($type) {
            $query->whereHas('category', fn($q) => $q->where('type', $type));
        }

        return $query->get();
    }

    /**
     * Crear una cuenta
     */
    public function create(array $data): Account
    {
        // Verificar que la categoría existe
        $category = Category::findOrFail($data['category_id']);

        $account = Account::create($data);

        Log::info("Cuenta creada", [
            'account_id' => $account->id,
            'name' => $account->name,
            'category_id' => $account->category_id,
            'user_id' => auth()->id(),
        ]);

        return $account;
    }

    /**
     * Actualizar una cuenta
     */
    public function update(Account $account, array $data): Account
    {
        // Verificar que la categoría existe si se actualiza
        if (isset($data['category_id'])) {
            Category::findOrFail($data['category_id']);
        }

        $account->update($data);

        Log::info("Cuenta actualizada", [
            'account_id' => $account->id,
            'name' => $account->name,
            'user_id' => auth()->id(),
        ]);

        return $account->fresh();
    }

    /**
     * Activar/desactivar una cuenta
     */
    public function toggle(Account $account): bool
    {
        if ($account->is_system && $account->is_active) {
            throw new \Exception('No se puede desactivar una cuenta del sistema');
        }

        $account->is_active = !$account->is_active;

        Log::info("Cuenta toggled", [
            'account_id' => $account->id,
            'is_active' => $account->is_active,
            'user_id' => auth()->id(),
        ]);

        return $account->save();
    }

    /**
     * Eliminar una cuenta
     */
    public function delete(Account $account): bool
    {
        // Verificar si tiene transacciones asociadas
        if ($account->transactions()->count() > 0) {
            throw new \Exception('No se puede eliminar la cuenta porque tiene transacciones asociadas');
        }

        // No permitir eliminar cuentas del sistema
        if ($account->is_system) {
            throw new \Exception('No se puede eliminar una cuenta del sistema');
        }

        Log::info("Cuenta eliminada", [
            'account_id' => $account->id,
            'name' => $account->name,
            'user_id' => auth()->id(),
        ]);

        return $account->delete();
    }

    /**
     * Obtener estadísticas de cuentas
     */
    public function getStats(): array
    {
        return [
            'total' => Account::count(),
            'active' => Account::where('is_active', true)->count(),
            'income' => Account::whereHas('category', fn($q) => $q->where('type', 'income'))->count(),
            'expense' => Account::whereHas('category', fn($q) => $q->where('type', 'expense'))->count(),
            'system' => Account::where('is_system', true)->count(),
        ];
    }
}