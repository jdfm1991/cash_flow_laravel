<?php

namespace App\Services\Context;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class CompanyContext
{
    /**
     * Clave para almacenar el ID de empresa en sesión
     */
    private const SESSION_KEY = 'current_company_id';

    /**
     * Clave para almacenar las empresas disponibles en sesión
     */
    private const COMPANIES_KEY = 'available_companies';

    /**
     * ID de la empresa activa
     */
    private ?int $currentCompanyId = null;

    /**
     * Empresas disponibles para el usuario
     */
    private array $availableCompanies = [];

    /**
     * Datos de la empresa activa (cache)
     */
    private ?array $currentCompanyData = null;

    public function __construct()
    {
        $this->loadFromSession();
    }

    /**
     * Obtener el ID de la empresa activa
     */
    public function getCurrentCompanyId(): ?int
    {
        return $this->currentCompanyId;
    }

    /**
     * Establecer empresa activa
     */
    public function setCurrentCompany(int $companyId): self
    {
        // Validar que la empresa está disponible para el usuario
        if (!$this->isCompanyAvailable($companyId)) {
            Log::warning('Intento de cambiar a empresa no disponible', [
                'company_id' => $companyId,
                'available_companies' => $this->availableCompanies,
            ]);
            
            // Si es super_admin, permitir aunque no esté en la lista
            // (esto debe ser validado en el backend)
            // En frontend, simplemente registramos pero permitimos
        }

        $this->currentCompanyId = $companyId;
        $this->currentCompanyData = null; // Limpiar cache
        
        // Persistir en sesión
        Session::put(self::SESSION_KEY, $companyId);

        Log::info('Contexto de empresa actualizado', [
            'company_id' => $companyId,
        ]);

        return $this;
    }

    /**
     * Establecer empresas disponibles para el usuario
     */
    public function setAvailableCompanies(array $companies): self
    {
        $this->availableCompanies = $companies;
        
        // Persistir en sesión
        Session::put(self::COMPANIES_KEY, $companies);

        return $this;
    }

    /**
     * Obtener empresas disponibles para el usuario
     */
    public function getAvailableCompanies(): array
    {
        return $this->availableCompanies;
    }

    /**
     * Verificar si hay una empresa activa
     */
    public function hasCompany(): bool
    {
        return $this->currentCompanyId !== null;
    }

    /**
     * Verificar si una empresa está disponible para el usuario
     */
    public function isCompanyAvailable(int $companyId): bool
    {
        foreach ($this->availableCompanies as $company) {
            if (($company['id'] ?? $company) === $companyId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtener la empresa por defecto
     */
    public function getDefaultCompany(): ?int
    {
        if (empty($this->availableCompanies)) {
            return null;
        }

        // Buscar empresa marcada como default
        foreach ($this->availableCompanies as $company) {
            if ($company['is_default'] ?? false) {
                return $company['id'] ?? null;
            }
        }

        // Si no hay default, usar la primera
        $first = $this->availableCompanies[0] ?? null;
        return $first['id'] ?? null;
    }

    /**
     * Obtener datos de la empresa activa
     */
    public function getCurrentCompanyData(): ?array
    {
        if ($this->currentCompanyData !== null) {
            return $this->currentCompanyData;
        }

        if (!$this->currentCompanyId) {
            return null;
        }

        // Buscar en empresas disponibles
        foreach ($this->availableCompanies as $company) {
            if (($company['id'] ?? null) === $this->currentCompanyId) {
                $this->currentCompanyData = $company;
                return $company;
            }
        }

        // Si no se encuentra en la lista, intentar cargar desde cache
        $cached = Cache::get("company_data_{$this->currentCompanyId}");
        if ($cached) {
            $this->currentCompanyData = $cached;
            return $cached;
        }

        return null;
    }

    /**
     * Obtener el nombre de la empresa activa
     */
    public function getCurrentCompanyName(): ?string
    {
        $data = $this->getCurrentCompanyData();
        return $data['name'] ?? null;
    }

    /**
     * Obtener el logo de la empresa activa
     */
    public function getCurrentCompanyLogo(): ?string
    {
        $data = $this->getCurrentCompanyData();
        return $data['logo'] ?? $data['logo_path'] ?? null;
    }

    /**
     * Obtener la moneda de la empresa activa
     */
    public function getCurrentCompanyCurrency(): ?string
    {
        $data = $this->getCurrentCompanyData();
        return $data['currency'] ?? $data['default_currency'] ?? null;
    }

    /**
     * Verificar si el usuario es super_admin
     * Nota: Esto es una verificación local, la autorización real está en el backend
     */
    public function isSuperAdmin(): bool
    {
        $user = Session::get('api_user');
        if (!$user) {
            return false;
        }

        return in_array('super_admin', $user['roles'] ?? []);
    }

    /**
     * Verificar si el usuario tiene acceso a una empresa específica
     * Nota: El backend validará realmente, esto es solo para UI
     */
    public function hasAccessToCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->isCompanyAvailable($companyId);
    }

    /**
     * Establecer la primera empresa disponible como activa
     */
    public function setFirstAvailableCompany(): bool
    {
        if (empty($this->availableCompanies)) {
            return false;
        }

        $first = $this->availableCompanies[0] ?? null;
        if (!$first) {
            return false;
        }

        $companyId = $first['id'] ?? null;
        if (!$companyId) {
            return false;
        }

        $this->setCurrentCompany($companyId);
        return true;
    }

    /**
     * Establecer la empresa por defecto como activa
     */
    public function setDefaultCompany(): bool
    {
        $defaultId = $this->getDefaultCompany();
        if (!$defaultId) {
            return false;
        }

        $this->setCurrentCompany($defaultId);
        return true;
    }

    /**
     * Limpiar todo el contexto de empresa
     */
    public function clear(): void
    {
        $this->currentCompanyId = null;
        $this->currentCompanyData = null;
        $this->availableCompanies = [];
        
        Session::forget([self::SESSION_KEY, self::COMPANIES_KEY]);
        
        Log::info('Contexto de empresa limpiado');
    }

    /**
     * Cargar datos desde sesión
     */
    private function loadFromSession(): void
    {
        // Cargar empresa activa
        $this->currentCompanyId = Session::get(self::SESSION_KEY);
        
        // Cargar empresas disponibles
        $this->availableCompanies = Session::get(self::COMPANIES_KEY, []);
        
        // Validar que la empresa activa esté en la lista de disponibles
        if ($this->currentCompanyId !== null && !$this->isCompanyAvailable($this->currentCompanyId)) {
            // Si no está disponible, intentar usar la primera o default
            if ($this->isSuperAdmin()) {
                // Super_admin puede usar cualquier empresa
                // No hacemos nada, el backend validará
            } else {
                // Para usuarios normales, intentar establecer una válida
                if (!$this->setDefaultCompany() && !$this->setFirstAvailableCompany()) {
                    // Si no hay empresas disponibles, limpiar
                    $this->clear();
                }
            }
        }
    }

    /**
     * Sincronizar empresas disponibles con datos del usuario
     */
    public function syncWithUser(array $userData): void
    {
        $companies = $userData['companies'] ?? [];
        $currentCompanyId = $userData['current_company_id'] ?? null;
        
        // Actualizar lista de empresas disponibles
        $this->setAvailableCompanies($companies);
        
        // Si hay empresa activa en los datos del usuario, usarla
        if ($currentCompanyId !== null && $this->isCompanyAvailable($currentCompanyId)) {
            $this->setCurrentCompany($currentCompanyId);
            return;
        }
        
        // Si no tiene empresa activa, establecer default o primera
        if ($this->currentCompanyId === null) {
            if (!$this->setDefaultCompany() && !$this->setFirstAvailableCompany()) {
                Log::warning('No se pudo establecer empresa automáticamente', [
                    'user_id' => $userData['id'] ?? null,
                    'companies' => $companies,
                ]);
            }
        }
    }

    /**
     * Verificar si el contexto está completamente configurado
     */
    public function isReady(): bool
    {
        return $this->hasCompany() && !empty($this->availableCompanies);
    }

    /**
     * Obtener estadísticas del contexto
     */
    public function getStats(): array
    {
        return [
            'has_company' => $this->hasCompany(),
            'company_id' => $this->currentCompanyId,
            'company_name' => $this->getCurrentCompanyName(),
            'available_companies_count' => count($this->availableCompanies),
            'is_ready' => $this->isReady(),
            'is_super_admin' => $this->isSuperAdmin(),
        ];
    }
}