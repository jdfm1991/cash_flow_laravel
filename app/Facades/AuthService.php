<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array login(string $email, string $password, ?string $deviceName = null)
 * @method static void logout()
 * @method static string refreshToken()
 * @method static array changePassword(string $currentPassword, string $newPassword)
 * @method static array switchCompany(int $companyId)
 * @method static array|null getCurrentUser()
 * @method static int|null getCurrentUserId()
 * @method static bool isAuthenticated()
 * @method static array register(array $data)
 * @method static array forgotPassword(string $email)
 * @method static array resetPassword(string $email, string $token, string $password)
 * @method static array verifyEmail(string $token)
 * @method static array getAvailableCompanies()
 * @method static int|null getCurrentCompanyId()
 * @method static string|null getToken()
 * @method static bool hasPermission(string $permission)
 * @method static bool hasRole(string $role)
 * 
 * @see \App\Services\Api\AuthService
 */
class AuthService extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\Api\AuthService::class;
    }
}