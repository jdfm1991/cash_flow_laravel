<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Requests\Category\MoveCategoryRequest;
use App\Services\CategoryService;
use App\Models\Category;
use App\Enums\CategoryType;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected CategoryService $categoryService,
    ) {}

    /**
     * GET /api/categories
     * Listar todas las categorías
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $type = $request->input('type');
        $search = $request->input('search');

        if ($user->hasRole('super_admin')) {
            $categories = $this->categoryService->getAll($type, $search);
        } else {
            $categories = $this->categoryService->getAllActive($type, $search);
        }

        return $this->successResponse($categories);
    }

    /**
     * GET /api/categories/tree
     * Obtener categorías en formato jerárquico (árbol)
     */
    public function tree(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $onlyActive = $request->boolean('active', true);

        $tree = $this->categoryService->getTree($type, $onlyActive);

        return $this->successResponse($tree);
    }

    /**
     * GET /api/categories/income
     * Obtener categorías de ingresos
     */
    public function getIncomeCategories(): JsonResponse
    {
        $categories = $this->categoryService->getByType(CategoryType::INCOME->value);

        return $this->successResponse($categories);
    }

    /**
     * GET /api/categories/expense
     * Obtener categorías de egresos
     */
    public function getExpenseCategories(): JsonResponse
    {
        $categories = $this->categoryService->getByType(CategoryType::EXPENSE->value);

        return $this->successResponse($categories);
    }

    /**
     * GET /api/categories/with-colors
     * Obtener categorías con colores (para gráficos)
     */
    public function withColors(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $categories = $this->categoryService->getAllWithColors($type);

        return $this->successResponse($categories);
    }

    /**
     * GET /api/categories/search
     * Buscar categorías por nombre
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $categories = $this->categoryService->search(
            $request->input('q'),
            $request->input('type'),
            $request->input('limit', 10)
        );

        return $this->successResponse($categories);
    }

    /**
     * GET /api/categories/{id}
     * Obtener categoría específica
     */
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Categoría no encontrada');
        }

        return $this->successResponse($category->load(['parent', 'children']));
    }

    /**
     * GET /api/categories/{id}/descendants
     * Obtener todas las subcategorías de una categoría
     */
    public function descendants(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryService->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Categoría no encontrada');
        }

        $descendants = $this->categoryService->getDescendants(
            $id,
            $request->boolean('active', true)
        );

        return $this->successResponse($descendants);
    }

    /**
     * POST /api/categories
     * Crear nueva categoría (solo admin/super_admin)
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCategories()) {
            return $this->forbiddenResponse('No tienes permisos para crear categorías');
        }

        $category = $this->categoryService->create($request->validated());

        // ✅ Auditoría
        $this->logCreated('Category', $category->id, $category->toArray());

        return $this->createdResponse($category, 'Categoría creada exitosamente');
    }

    /**
     * PUT /api/categories/{id}
     * Actualizar categoría
     */
    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCategories()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar categorías');
        }

        $category = $this->categoryService->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Categoría no encontrada');
        }

        // ✅ Si es categoría del sistema, no se puede modificar
        if ($category->is_system) {
            return $this->errorResponse('No se puede modificar una categoría del sistema', 422);
        }

        $oldData = $category->toArray();
        $updated = $this->categoryService->update($category, $request->validated());

        // ✅ Auditoría
        $this->logUpdated('Category', $id, $oldData, $updated->toArray());

        return $this->successResponse($updated, 'Categoría actualizada exitosamente');
    }

    /**
     * DELETE /api/categories/{id}
     * Eliminar categoría
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCategories()) {
            return $this->forbiddenResponse('No tienes permisos para eliminar categorías');
        }

        $category = $this->categoryService->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Categoría no encontrada');
        }

        // ✅ Si es categoría del sistema, no se puede eliminar
        if ($category->is_system) {
            return $this->errorResponse('No se puede eliminar una categoría del sistema', 422);
        }

        // ✅ Verificar que no tenga hijos
        if ($category->children()->count() > 0) {
            return $this->errorResponse(
                'No se puede eliminar una categoría que tiene subcategorías',
                422
            );
        }

        $oldData = $category->toArray();
        $this->categoryService->delete($category);

        // ✅ Auditoría
        $this->logDeleted('Category', $id, $oldData);

        return $this->deletedResponse('Categoría eliminada exitosamente');
    }

    /**
     * POST /api/categories/{id}/move
     * Mover categoría a otra categoría padre
     */
    public function move(MoveCategoryRequest $request, int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCategories()) {
            return $this->forbiddenResponse('No tienes permisos para mover categorías');
        }

        $category = $this->categoryService->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Categoría no encontrada');
        }

        // ✅ Si es categoría del sistema, no se puede mover
        if ($category->is_system) {
            return $this->errorResponse('No se puede mover una categoría del sistema', 422);
        }

        $oldData = $category->toArray();
        $updated = $this->categoryService->move($category, $request->input('parent_id'));

        // ✅ Auditoría
        $this->logUpdated('Category', $id, $oldData, $updated->toArray());

        return $this->successResponse($updated, 'Categoría movida exitosamente');
    }

    /**
     * POST /api/categories/{id}/toggle
     * Activar/desactivar categoría
     */
    public function toggle(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCategories()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar categorías');
        }

        $category = $this->categoryService->findById($id);

        if (!$category) {
            return $this->notFoundResponse('Categoría no encontrada');
        }

        // ✅ Si es categoría del sistema, no se puede desactivar
        if ($category->is_system && $category->is_active) {
            return $this->errorResponse('No se puede desactivar una categoría del sistema', 422);
        }

        $oldData = $category->toArray();
        $updated = $this->categoryService->toggle($category);

        // ✅ Auditoría
        $this->logUpdated('Category', $id, $oldData, $updated->toArray());

        $status = $updated->is_active ? 'activada' : 'desactivada';

        return $this->successResponse($updated, "Categoría {$status} exitosamente");
    }

    /**
     * Verificar permisos para gestionar categorías
     */
    private function canManageCategories(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin') || 
               $user->hasRole('admin');
    }
}