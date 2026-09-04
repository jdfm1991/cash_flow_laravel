<?php

namespace App\Services;

use App\Models\User;
use App\Models\Company;
use App\Models\AuditLog;
use App\DTOs\CompanyData;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        protected CompanyService $companyService,
        protected UserService $userService,
    ) {}

    /**
     * Registrar un nuevo usuario
     */
    public function register(array $data): array
    {
        // 1. Validar email único
        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['El email ya está registrado'],
            ]);
        }

        // 2. Crear el usuario
        $user = User::create([
            'email' => $data['email'],
            'name' => $data['full_name'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'email_verified' => false,
        ]);

        // 3. Crear empresa si se proporciona
        if (isset($data['company_name'])) {
            $companyData = new CompanyData(
                name: $data['company_name'],
                businessName: $data['company_business_name'] ?? null,
                taxId: $data['company_tax_id'] ?? null,
                email: $data['company_email'] ?? null,
                phone: $data['company_phone'] ?? null,
                address: $data['company_address'] ?? null,
            );
            $company = $this->companyService->create($companyData, $user->id);
            $user->companies()->attach($company->id, ['is_default' => true]);
            $user->update(['current_company_id' => $company->id]);
        }

        // 4. Asignar rol por defecto
        $user->assignRole('user');

        // 5. Crear preferencias por defecto
        $user->getPreferences();

        // 6. Generar token de verificación
        $token = $this->generateVerificationToken($user);

        // 7. Enviar email de verificación
        $this->sendVerificationEmail($user, $token);

        // 8. Log de auditoría
        $this->logActivity($user, AuditAction::LOGIN, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'user' => $user,
            'message' => 'Usuario registrado correctamente. Revisa tu email para verificar tu cuenta.',
            'requires_verification' => true,
        ];
    }

    /**
     * Iniciar sesión
     */
    public function login(array $credentials, string $deviceName = 'web'): array
    {
        // 1. Buscar usuario
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas'],
            ]);
        }

        // 2. Verificar si está activo
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está desactivada. Contacta al administrador.'],
            ]);
        }

        // 3. Verificar si está bloqueado
        if ($user->isLocked()) {
            $minutes = $user->locked_until->diffInMinutes(now());
            throw ValidationException::withMessages([
                'email' => [
                    "Demasiados intentos fallidos. Cuenta bloqueada por {$minutes} minutos."
                ],
            ]);
        }

        // 4. Verificar contraseña
        if (!Hash::check($credentials['password'], $user->password)) {
            $user->recordFailedLogin();
            throw ValidationException::withMessages([
                'password' => ['Credenciales inválidas'],
            ]);
        }

        // 5. Verificar email
        if (!$user->isVerified()) {
            throw ValidationException::withMessages([
                'email' => ['Debes verificar tu email antes de iniciar sesión.'],
            ]);
        }

        // 6. Registrar login
        $user->recordLogin(request()->ip(), request()->userAgent());

        // 7. Crear token
        $token = $user->createToken($deviceName)->plainTextToken;

        // 8. Obtener empresas del usuario
        $companies = $user->companies()->get();
        $currentCompany = $user->currentCompany;

        // 9. Si solo tiene una empresa, establecerla automáticamente
        if ($companies->count() === 1 && !$currentCompany) {
            $company = $companies->first();
            $user->update(['current_company_id' => $company->id]);
            $currentCompany = $company;
        }

        // 10. Log de auditoría
        $this->logActivity($user, AuditAction::LOGIN, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device_name' => $deviceName,
        ]);

        // 11. Resetear intentos fallidos
        $user->resetFailedLoginAttempts();

        // 12. Iniciar sesión en Laravel (para Filament)
        Auth::login($user);

        return [
            'token' => $token,
            'user' => $user->load(['currentCompany', 'preferences']),
            'companies' => $companies,
            'current_company' => $currentCompany,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }

    /**
     * Cerrar sesión
     */
    public function logout(User $user, ?string $token = null): bool
    {
        // 1. Log de auditoría
        $this->logActivity($user, AuditAction::LOGOUT, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // 2. Revocar token específico o todos
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $accessToken->delete();
            }
        } else {
            $user->tokens()->delete();
        }

        // 3. Cerrar sesión en Laravel
        Auth::logout();

        return true;
    }

    /**
     * Verificar email
     */
    public function verifyEmail(string $token): array
    {
        // 1. Buscar token
        $verification = DB::table('email_verifications')
            ->where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            throw ValidationException::withMessages([
                'token' => ['Token inválido o expirado'],
            ]);
        }

        // 2. Obtener usuario
        $user = User::find($verification->user_id);
        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['Usuario no encontrado'],
            ]);
        }

        // 3. Verificar email
        $user->verifyEmail();

        // 4. Marcar token como usado
        DB::table('email_verifications')
            ->where('token', $token)
            ->update(['used' => true]);

        // 5. Log de auditoría
        $this->logActivity($user, AuditAction::UPDATE, [
            'action' => 'email_verified',
        ]);

        return [
            'message' => 'Email verificado correctamente',
            'email' => $user->email,
        ];
    }

    /**
     * Solicitar recuperación de contraseña
     */
    public function requestPasswordReset(string $email): array
    {
        // 1. Buscar usuario
        $user = User::where('email', $email)->first();
        if (!$user) {
            // Por seguridad, no revelar si el email existe
            return [
                'message' => 'Si el email existe, recibirás un enlace de recuperación.',
            ];
        }

        // 2. Generar token
        $token = Str::random(64);

        // 3. Guardar token
        DB::table('password_resets')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // 4. Enviar email
        $this->sendPasswordResetEmail($user, $token);

        // 5. Log de auditoría
        $this->logActivity($user, AuditAction::PASSWORD_RESET, [
            'ip' => request()->ip(),
        ]);

        return [
            'message' => 'Se ha enviado un enlace de recuperación a tu email.',
        ];
    }

    /**
     * Resetear contraseña
     */
    public function resetPassword(string $email, string $token, string $password): array
    {
        // 1. Validar token
        $record = DB::table('password_resets')
            ->where('email', $email)
            ->first();

        if (!$record || !Hash::check($token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['Token inválido o expirado'],
            ]);
        }

        // 2. Verificar expiración (1 hora)
        if (now()->diffInHours($record->created_at) > 1) {
            throw ValidationException::withMessages([
                'token' => ['El enlace ha expirado. Solicita uno nuevo.'],
            ]);
        }

        // 3. Buscar usuario
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Usuario no encontrado'],
            ]);
        }

        // 4. Actualizar contraseña
        $user->update([
            'password' => Hash::make($password),
        ]);

        // 5. Eliminar token
        DB::table('password_resets')->where('email', $email)->delete();

        // 6. Revocar todos los tokens (seguridad)
        $user->tokens()->delete();

        // 7. Log de auditoría
        $this->logActivity($user, AuditAction::PASSWORD_RESET, [
            'action' => 'password_changed',
        ]);

        return [
            'message' => 'Contraseña actualizada correctamente',
        ];
    }

    /**
     * Cambiar contraseña (usuario logueado)
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        // 1. Verificar contraseña actual
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual es incorrecta'],
            ]);
        }

        // 2. Actualizar contraseña
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // 3. Log de auditoría
        $this->logActivity($user, AuditAction::UPDATE, [
            'action' => 'password_changed',
            'ip' => request()->ip(),
        ]);

        // 4. Opcional: revocar todos los tokens excepto el actual
        // $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return [
            'message' => 'Contraseña actualizada correctamente',
        ];
    }

    /**
     * Cambiar empresa de contexto
     */
    public function switchCompany(User $user, int $companyId): array
    {
        // 1. Verificar que el usuario pertenece a la empresa
        if (!$user->belongsToCompany($companyId)) {
            throw ValidationException::withMessages([
                'company_id' => ['No tienes acceso a esta empresa'],
            ]);
        }

        // 2. Cambiar empresa
        $user->update(['current_company_id' => $companyId]);

        // 3. Log de auditoría
        $this->logActivity($user, AuditAction::SWITCH_COMPANY, [
            'company_id' => $companyId,
            'ip' => request()->ip(),
        ]);

        // 4. Obtener la empresa
        $company = Company::find($companyId);

        return [
            'message' => 'Empresa cambiada correctamente',
            'company' => $company,
        ];
    }

    /**
     * Generar token de verificación
     */
    protected function generateVerificationToken(User $user): string
    {
        $token = Str::random(64);

        DB::table('email_verifications')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
            'used' => false,
            'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Enviar email de verificación
     */
    protected function sendVerificationEmail(User $user, string $token): void
    {
        // Por ahora, solo log
        Log::info("Email de verificación enviado", [
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token,
            'url' => url("/verify-email?token={$token}"),
        ]);

        // TODO: Implementar envío real con Mail
        // Mail::to($user->email)->send(new VerifyEmail($user, $token));
    }

    /**
     * Enviar email de recuperación de contraseña
     */
    protected function sendPasswordResetEmail(User $user, string $token): void
    {
        // Por ahora, solo log
        Log::info("Email de recuperación enviado", [
            'user_id' => $user->id,
            'email' => $user->email,
            'token' => $token,
            'url' => url("/reset-password?token={$token}&email={$user->email}"),
        ]);

        // TODO: Implementar envío real con Mail
        // Mail::to($user->email)->send(new PasswordReset($user, $token));
    }

    /**
     * Registrar actividad en auditoría
     */
    protected function logActivity(User $user, AuditAction $action, array $data = []): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'company_id' => $user->current_company_id,
            'action' => $action->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'metadata' => $data,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtener usuario autenticado
     */
    public function getAuthenticatedUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Verificar si el usuario tiene permisos para un módulo
     */
    public function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermissionTo($permission);
    }
}
