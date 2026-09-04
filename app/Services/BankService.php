<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class BankService
{
    public function getAll(
        bool $onlyActive = true,
        ?string $countryCode = null,
        ?string $search = null,
        int $perPage = 25
    ): LengthAwarePaginator {
        return Bank::query()
            ->when($onlyActive, fn($q) => $q->where('is_active', true))
            ->when($countryCode, fn($q, $code) => $q->where('country_code', $code))
            ->when($search, function ($q, $term) {
                return $q->where(function ($query) use ($term) {
                    $query->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('code', 'LIKE', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(int $id): ?Bank
    {
        return Bank::find($id);
    }

    public function create(array $data): Bank
    {
        $bank = Bank::create($data);
        
        Log::info("Banco creado", [
            'bank_id' => $bank->id,
            'name' => $bank->name,
            'code' => $bank->code,
            'user_id' => auth()->id(),
        ]);

        return $bank;
    }

    public function update(Bank $bank, array $data): Bank
    {
        $bank->update($data);
        
        Log::info("Banco actualizado", [
            'bank_id' => $bank->id,
            'name' => $bank->name,
            'user_id' => auth()->id(),
        ]);

        return $bank->fresh();
    }

    public function delete(Bank $bank): void
    {
        Log::info("Banco eliminado", [
            'bank_id' => $bank->id,
            'name' => $bank->name,
            'user_id' => auth()->id(),
        ]);

        $bank->delete();
    }

    public function toggle(Bank $bank): Bank
    {
        $bank->update(['is_active' => !$bank->is_active]);
        
        Log::info("Banco toggled", [
            'bank_id' => $bank->id,
            'is_active' => $bank->is_active,
            'user_id' => auth()->id(),
        ]);

        return $bank->fresh();
    }

    public function search(string $term, int $limit = 10): Collection
    {
        return Bank::where('name', 'LIKE', "%{$term}%")
            ->orWhere('code', 'LIKE', "%{$term}%")
            ->where('is_active', true)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'code', 'country_code']);
    }

    public function getCountries(bool $onlyActive = true): array
    {
        $countries = Bank::select('country_code')
            ->distinct()
            ->when($onlyActive, fn($q) => $q->where('is_active', true))
            ->whereNotNull('country_code')
            ->pluck('country_code')
            ->toArray();

        $result = [];
        foreach ($countries as $code) {
            $result[] = [
                'code' => $code,
                'name' => $this->getCountryName($code),
            ];
        }

        return $result;
    }

    public function getOptions(bool $onlyActive = true): Collection
    {
        return Bank::when($onlyActive, fn($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * Obtener nombre del país por código
     */
    private function getCountryName(string $code): string
    {
        $countries = [
            'VE' => 'Venezuela',
            'CO' => 'Colombia',
            'US' => 'Estados Unidos',
            'MX' => 'México',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'PE' => 'Perú',
            'ES' => 'España',
            'BR' => 'Brasil',
            'EC' => 'Ecuador',
            'BO' => 'Bolivia',
            'PY' => 'Paraguay',
            'UY' => 'Uruguay',
            'PA' => 'Panamá',
            'CR' => 'Costa Rica',
            'GT' => 'Guatemala',
            'HN' => 'Honduras',
            'SV' => 'El Salvador',
            'NI' => 'Nicaragua',
            'DO' => 'República Dominicana',
            'PR' => 'Puerto Rico',
            'CU' => 'Cuba',
        ];

        return $countries[$code] ?? $code;
    }
}