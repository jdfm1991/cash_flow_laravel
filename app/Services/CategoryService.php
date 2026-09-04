<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CategoryService
{
    /**
     * Obtener todas las categorías
     */
    public function getAll(?string $type = null, ?string $search = null)
    {
        return Category::with(['parent', 'children'])
            ->when($type, fn($q, $t) => $q->where('type', $t))
            ->when($search, fn($q, $term) => 
                $q->where('name', 'LIKE', "%{$term}%")
            )
            ->ordered()
            ->get();
    }

    /**
     * Obtener categorías activas
     */
    public function getAllActive(?string $type = null, ?string $search = null)
    {
        return Category::active()
            ->when($type, fn($q, $t) => $q->where('type', $t))
            ->when($search, fn($q, $term) => 
                $q->where('name', 'LIKE', "%{$term}%")
            )
            ->ordered()
            ->get();
    }

    /**
     * Obtener categorías en formato árbol
     */
    public function getTree(?string $type = null, bool $onlyActive = true)
    {
        $query = Category::with(['children' => function ($q) use ($onlyActive) {
            if ($onlyActive) {
                $q->active();
            }
            $q->ordered();
        }])
        ->whereNull('parent_id')
        ->when($type, fn($q, $t) => $q->where('type', $t))
        ->when($onlyActive, fn($q) => $q->active())
        ->ordered();

        return $query->get();
    }

    /**
     * Obtener categorías por tipo
     */
    public function getByType(string $type)
    {
        return Category::byType($type)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Obtener categoría por ID
     */
    public function findById(int $id): ?Category
    {
        return Category::with(['parent', 'children'])
            ->find($id);
    }

    /**
     * Crear una categoría
     */
    public function create(array $data): Category
    {
        // Validar que el padre sea del mismo tipo
        if (isset($data['parent_id'])) {
            $parent = Category::find($data['parent_id']);
            if ($parent && $parent->type !== $data['type']) {
                throw new \InvalidArgumentException(
                    'La categoría padre debe ser del mismo tipo'
                );
            }
        }

        $category = Category::create($data);

        Log::info("Categoría creada", [
            'category_id' => $category->id,
            'name' => $category->name,
            'type' => $category->type,
            'user_id' => auth()->id(),
        ]);

        return $category;
    }

    /**
     * Actualizar una categoría
     */
    public function update(Category $category, array $data): Category
    {
        // Validar que el padre sea del mismo tipo
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = Category::find($data['parent_id']);
            $type = $data['type'] ?? $category->type;
            if ($parent && $parent->type !== $type) {
                throw new \InvalidArgumentException(
                    'La categoría padre debe ser del mismo tipo'
                );
            }
        }

        $category->update($data);

        Log::info("Categoría actualizada", [
            'category_id' => $category->id,
            'name' => $category->name,
            'user_id' => auth()->id(),
        ]);

        return $category->fresh();
    }

    /**
     * Eliminar una categoría
     */
    public function delete(Category $category): bool
    {
        // Verificar si tiene cuentas asociadas
        if ($category->accounts()->count() > 0) {
            throw new \Exception('No se puede eliminar la categoría porque tiene cuentas asociadas');
        }

        // Verificar si tiene transacciones
        if ($category->transactions()->count() > 0) {
            throw new \Exception('No se puede eliminar la categoría porque tiene transacciones asociadas');
        }

        Log::info("Categoría eliminada", [
            'category_id' => $category->id,
            'name' => $category->name,
            'user_id' => auth()->id(),
        ]);

        return $category->delete();
    }

    /**
     * Mover categoría a otro padre
     */
    public function move(Category $category, ?int $newParentId): Category
    {
        // Validar que no sea su propio descendiente
        if ($newParentId) {
            $descendants = $this->getDescendants($category->id);
            if ($descendants->pluck('id')->contains($newParentId)) {
                throw new \InvalidArgumentException(
                    'No se puede mover una categoría a su propio descendiente'
                );
            }
        }

        // Validar que el padre sea del mismo tipo
        if ($newParentId) {
            $parent = Category::find($newParentId);
            if ($parent && $parent->type !== $category->type) {
                throw new \InvalidArgumentException(
                    'La categoría padre debe ser del mismo tipo'
                );
            }
        }

        $category->update(['parent_id' => $newParentId]);

        Log::info("Categoría movida", [
            'category_id' => $category->id,
            'new_parent_id' => $newParentId,
            'user_id' => auth()->id(),
        ]);

        return $category->fresh();
    }

    /**
     * Activar/desactivar categoría
     */
    public function toggle(Category $category): Category
    {
        $category->update(['is_active' => !$category->is_active]);

        Log::info("Categoría toggled", [
            'category_id' => $category->id,
            'is_active' => $category->is_active,
            'user_id' => auth()->id(),
        ]);

        return $category->fresh();
    }

    /**
     * Obtener descendientes de una categoría
     */
    public function getDescendants(int $categoryId, bool $onlyActive = true)
    {
        $category = Category::find($categoryId);
        
        if (!$category) {
            return collect();
        }

        return $category->descendants()
            ->when($onlyActive, fn($q) => $q->active())
            ->get();
    }

    /**
     * Buscar categorías
     */
    public function search(string $term, ?string $type = null, int $limit = 10)
    {
        return Category::where('name', 'LIKE', "%{$term}%")
            ->when($type, fn($q, $t) => $q->where('type', $t))
            ->active()
            ->ordered()
            ->limit($limit)
            ->get(['id', 'name', 'type', 'icon', 'color']);
    }

    /**
     * Obtener categorías con colores (para gráficos)
     */
    public function getAllWithColors(?string $type = null)
    {
        return Category::active()
            ->when($type, fn($q, $t) => $q->where('type', $t))
            ->select('id', 'name', 'type', 'color', 'icon', 'is_active')
            ->ordered()
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'type_label' => $category->type_label,
                    'color' => $category->color ?? '#6c757d',
                    'icon' => $category->icon ?? 'bi-tag',
                    'is_active' => $category->is_active,
                ];
            });
    }
}