<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Api\AuthService;
use App\Services\Context\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    /**
     * Mostrar el formulario de login
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect('/admin');
        }

        return view('auth.login');
    }

    /**
     * Procesar el login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        try {
            $authService = app(AuthService::class);
            $response = $authService->login(
                $request->email,
                $request->password
            );

            if (!$response || !isset($response['user'])) {
                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Credenciales inválidas']);
            }

            $userData = $response['user'];
            $token = $response['access_token'] ?? null;

            if ($token) {
                Session::put('api_token', $token);
            }

            Session::put('api_user', $userData);

            $companyContext = app(CompanyContext::class);
            if (isset($userData['companies']) && !empty($userData['companies'])) {
                $companyContext->setAvailableCompanies($userData['companies']);
                
                $currentCompany = $userData['current_company'] ?? $userData['companies'][0] ?? null;
                if ($currentCompany && isset($currentCompany['id'])) {
                    $companyContext->setCurrentCompany($currentCompany['id']);
                }
            }

            $user = \App\Models\User::where('email', $request->email)->first();
            if (!$user) {
                $user = \App\Models\User::create([
                    'name' => $userData['name'] ?? $userData['full_name'] ?? 'Usuario',
                    'email' => $request->email,
                    'password' => bcrypt(str()->random(32)),
                    'email_verified' => true,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]);
            }

            Auth::login($user, $request->has('remember'));

            return redirect()->intended('/admin');

        } catch (\Exception $e) {
            Log::error('Error en login', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Error al iniciar sesión: ' . $e->getMessage()]);
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        Auth::logout();
        Session::forget(['api_token', 'api_user', 'current_company_id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}