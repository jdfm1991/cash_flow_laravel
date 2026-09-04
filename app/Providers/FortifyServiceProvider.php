<?php

namespace App\Providers;

use App\Services\Api\AuthService;
use App\Services\Context\CompanyContext;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Log;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuthService::class);
        $this->app->singleton(CompanyContext::class);
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(function ($request) {
            Log::info('🔵 Fortify: Iniciando autenticación', [
                'email' => $request->email,
            ]);

            try {
                $authService = app(AuthService::class);
                
                Log::info('🔵 Fortify: Llamando a AuthService->login()');
                
                $response = $authService->login(
                    $request->email,
                    $request->password
                );

                Log::info('🔵 Fortify: Respuesta recibida', [
                    'has_token' => isset($response['access_token']),
                    'has_user' => isset($response['user']),
                ]);

                if (!$response || !isset($response['access_token']) || !isset($response['user'])) {
                    Log::error('🔴 Fortify: Respuesta inválida', [
                        'response' => $response,
                    ]);
                    return null;
                }

                $userData = $response['user'];

                Log::info('🔵 Fortify: Buscando usuario local', [
                    'email' => $request->email,
                ]);

                // Buscar o crear usuario local
                $user = User::where('email', $request->email)->first();
                
                if (!$user) {
                    Log::info('🔵 Fortify: Creando usuario local');
                    $user = User::create([
                        'name' => $userData['name'] ?? 'Usuario',
                        'email' => $request->email,
                        'password' => bcrypt(str()->random(32)),
                        'email_verified' => true,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ]);
                }

                Log::info('🔵 Fortify: Usuario local listo', [
                    'user_id' => $user->id,
                ]);

                Log::info('✅ Fortify: Autenticación exitosa', [
                    'user_id' => $user->id,
                    'email' => $request->email,
                ]);

                return $user;

            } catch (\Exception $e) {
                Log::error('🔴 Fortify: Error en autenticación', [
                    'email' => $request->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return null;
            }
        });

        Fortify::loginView(function () {
            return view('auth.login');
        });
    }
}