<?php

namespace App\Services;

use App\Models\User;
use App\Models\Company;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserService
{
    public function __construct(
        protected CompanyService $companyService,
    ) {}

    /**
     * Crear un nuevo usuario
     */
    public function create(array $data, ?int $createdBy = null): User
    {
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['El email ya está registrado'],
            ]);
        }

        if (isset($data['username']) && User::where('username', $data['username'])->exists()) {
            throw ValidationException::withMessages([
                'username' => ['El nombre de usuario ya está en uso'],
            ]);
        }

        $user = User::create([
            'email' => $data['email'],
            'name' => $data['name'] ?? $data['name'] ?? 'Usuario',  // ✅ Usar 'name'
            'password' => Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
            'email_verified' => $data['email_verified'] ?? false,
            'avatar' => $data['avatar'] ?? null,
        ]);

        if (isset($data['roles'])) {
            $user->assignRole($data['roles']);
        } else {
            $user->assignRole('user');
        }

        if (isset($data['permissions'])) {
            $user->givePermissionTo($data['permissions']);
        }

        if (isset($data['companies'])) {
            foreach ($data['companies'] as $companyData) {
                $companyId = $companyData['id'] ?? $companyData;
                $isDefault = $companyData['is_default'] ?? false;

                $user->companies()->attach($companyId, [
                    'is_default' => $isDefault,
                    'joined_at' => now(),
                ]);

                if ($isDefault || $user->companies()->count() === 1) {
                    $user->update(['current_company_id' => $companyId]);
                }
            }
        }

        $user->getPreferences();

        Log::info("Usuario creado", [
            'user_id' => $user->id,
            'email' => $user->email,
            'created_by' => $createdBy,
        ]);

        return $user->load(['companies', 'preferences']);
    }

    /**
     * Actualizar un usuario
     */
    public function update(User $user, array $data): User
    {
        if (
            isset($data['email']) && User::where('email', $data['email'])
            ->where('id', '!=', $user->id)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => ['El email ya está registrado'],
            ]);
        }

        if (
            isset($data['username']) && User::where('username', $data['username'])
            ->where('id', '!=', $user->id)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'username' => ['El nombre de usuario ya está en uso'],
            ]);
        }

        $updateData = [];

        $fields = ['username', 'email', 'name', 'avatar', 'is_active'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['name']) && !empty($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        // ✅ CORREGIDO: Verificar que password existe Y tiene contenido Y NO es null
        if (array_key_exists('password', $data) && !empty($data['password']) && $data['password'] !== null) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (isset($data['email_verified'])) {
            $updateData['email_verified'] = $data['email_verified'];
            if ($data['email_verified']) {
                $updateData['email_verified_at'] = now();
            }
        }

        $user->update($updateData);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        if (isset($data['companies'])) {
            $syncData = [];
            foreach ($data['companies'] as $companyData) {
                $companyId = $companyData['id'] ?? $companyData;
                $isDefault = $companyData['is_default'] ?? false;

                $syncData[$companyId] = [
                    'is_default' => $isDefault,
                    'joined_at' => now(),
                ];
            }

            $user->companies()->sync($syncData);

            if (!empty($syncData)) {
                $firstCompanyId = array_key_first($syncData);
                $user->update(['current_company_id' => $firstCompanyId]);
            }
        }

        $this->invalidateCache($user);

        Log::info("Usuario actualizado", [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user->fresh()->load(['companies', 'preferences', 'roles', 'permissions']);
    }

    /**
     * Eliminar un usuario (soft delete)
     */
    public function delete(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'user' => ['No se puede eliminar un super administrador'],
            ]);
        }

        $companies = $user->companies;
        foreach ($companies as $company) {
            $admins = $company->users()->role('admin')->count();
            if ($admins <= 1 && $user->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'user' => ["No se puede eliminar el último administrador de la empresa '{$company->name}'"],
                ]);
            }
        }

        $result = $user->delete();
        $this->invalidateCache($user);

        Log::info("Usuario eliminado", [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $result;
    }

    /**
     * Restaurar un usuario eliminado
     */
    public function restore(int $userId): User
    {
        $user = User::withTrashed()->findOrFail($userId);
        $user->restore();

        $this->invalidateCache($user);

        Log::info("Usuario restaurado", [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    }

    /**
     * Obtener usuario por ID
     */
    public function findById(int $id): ?User
    {
        return Cache::remember("user:{$id}", 3600, function () use ($id) {
            return User::with(['companies', 'preferences', 'roles', 'permissions'])
                ->find($id);
        });
    }

    /**
     * Obtener usuario por email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Obtener usuario por username
     */
    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    /**
     * Obtener usuarios de una empresa
     */
    public function getByCompany(int $companyId, bool $onlyActive = true)
    {
        $query = User::whereHas('companies', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        });

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->with(['preferences', 'roles'])
            ->orderBy('name')  // ✅ Cambio: full_name → name
            ->get();
    }

    /**
     * Obtener usuarios con un rol específico en una empresa
     */
    public function getByRoleAndCompany(string $role, int $companyId)
    {
        return User::role($role)
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->get();
    }

    /**
     * Verificar si un usuario tiene un permiso en el contexto de una empresa
     */
    public function hasPermissionInCompany(User $user, string $permission, int $companyId): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (!$user->belongsToCompany($companyId)) {
            return false;
        }

        return $user->hasPermissionTo($permission);
    }

    /**
     * Cambiar el estado de un usuario
     */
    public function toggleActive(User $user): bool
    {
        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $this->invalidateCache($user);

        Log::info("Usuario " . ($user->is_active ? 'activado' : 'desactivado'), [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user->is_active;
    }

    /**
     * Asignar un rol a un usuario
     */
    public function assignRole(User $user, string $role): void
    {
        $user->assignRole($role);
        $this->invalidateCache($user);

        Log::info("Rol asignado a usuario", [
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /**
     * Remover un rol de un usuario
     */
    public function removeRole(User $user, string $role): void
    {
        $user->removeRole($role);
        $this->invalidateCache($user);

        Log::info("Rol removido de usuario", [
            'user_id' => $user->id,
            'role' => $role,
        ]);
    }

    /**
     * Asignar un permiso directo a un usuario
     */
    public function givePermission(User $user, string $permission): void
    {
        $user->givePermissionTo($permission);
        $this->invalidateCache($user);

        Log::info("Permiso asignado a usuario", [
            'user_id' => $user->id,
            'permission' => $permission,
        ]);
    }

    /**
     * Remover un permiso directo de un usuario
     */
    public function revokePermission(User $user, string $permission): void
    {
        $user->revokePermissionTo($permission);
        $this->invalidateCache($user);

        Log::info("Permiso removido de usuario", [
            'user_id' => $user->id,
            'permission' => $permission,
        ]);
    }

    /**
     * Asignar usuario a una empresa
     */
    public function assignToCompany(User $user, int $companyId, bool $isDefault = false): void
    {
        $user->companies()->attach($companyId, [
            'is_default' => $isDefault,
            'joined_at' => now(),
        ]);

        if ($isDefault || $user->companies()->count() === 1) {
            $user->update(['current_company_id' => $companyId]);
        }

        $this->invalidateCache($user);

        Log::info("Usuario asignado a empresa", [
            'user_id' => $user->id,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Remover usuario de una empresa
     */
    public function removeFromCompany(User $user, int $companyId): void
    {
        $companyUsers = User::whereHas('companies', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->where('is_active', true)->count();

        if ($companyUsers <= 1) {
            throw ValidationException::withMessages([
                'company' => ['No se puede remover el último usuario activo de la empresa'],
            ]);
        }

        $user->companies()->detach($companyId);

        if ($user->current_company_id === $companyId) {
            $newCompany = $user->companies()->first();
            if ($newCompany) {
                $user->update(['current_company_id' => $newCompany->id]);
            }
        }

        $this->invalidateCache($user);

        Log::info("Usuario removido de empresa", [
            'user_id' => $user->id,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Obtener preferencias del usuario
     */
    public function getPreferences(User $user): UserPreference
    {
        return $user->getPreferences();
    }

    /**
     * Actualizar preferencias del usuario
     */
    public function updatePreferences(User $user, array $data): UserPreference
    {
        return $user->updatePreferences($data);
    }

    /**
     * Invalidar caché del usuario
     */
    public function invalidateCache(User $user): void
    {
        Cache::forget("user:{$user->id}");
        Cache::forget("user:{$user->email}");
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getStats(): array
    {
        return [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'verified' => User::where('email_verified', true)->count(),
            'by_role' => Role::withCount('users')->get()->map(function ($role) {
                return [
                    'role' => $role->name,
                    'count' => $role->users_count,
                ];
            }),
        ];
    }
}
