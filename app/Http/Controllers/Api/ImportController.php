<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\StoreConnectionRequest;
use App\Http\Requests\Import\UpdateConnectionRequest;
use App\Http\Requests\Import\PreviewRequest;
use App\Http\Requests\Import\ExecuteRequest;
use App\Http\Requests\Import\UploadRequest;
use App\Http\Requests\Import\MapRequest;
use App\Services\ImportService;
use App\Services\ExternalConnectionService;
use App\Services\BankStatementParserService;
use App\Models\ExternalConnection;
use App\Models\ImportedTransaction;
use App\Models\MigrationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        protected ImportService $importService,
        protected ExternalConnectionService $connectionService,
        protected BankStatementParserService $parserService,
    ) {}

    // ================================================================
    // CONEXIONES EXTERNAS
    // ================================================================

    /**
     * GET /api/import/connections
     * Listar conexiones externas
     */
    public function connections(): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user->current_company_id;

        if ($user->hasRole('super_admin')) {
            $connections = ExternalConnection::with('company')->get();
        } else {
            $connections = ExternalConnection::where('company_id', $companyId)
                ->where('is_active', true)
                ->get();
        }

        // Ocultar contraseñas
        $connections->each(function ($conn) {
            unset($conn->password);
        });

        return response()->json($connections);
    }

    /**
     * POST /api/import/connections
     * Crear conexión externa
     */
    public function storeConnection(StoreConnectionRequest $request): JsonResponse
    {
        $this->authorize('create', ExternalConnection::class);

        $data = $request->validated();
        $data['company_id'] = auth()->user()->current_company_id;

        // Probar conexión antes de guardar
        $testResult = $this->connectionService->testConnection($data);
        if (!$testResult['success']) {
            return response()->json([
                'message' => 'Error de conexión',
                'error' => $testResult['message'],
            ], 422);
        }

        $connection = $this->connectionService->create($data);

        return response()->json([
            'message' => 'Conexión creada exitosamente',
            'data' => $connection,
        ], 201);
    }

    /**
     * PUT /api/import/connections/{id}
     * Actualizar conexión externa
     */
    public function updateConnection(UpdateConnectionRequest $request, int $id): JsonResponse
    {
        $connection = ExternalConnection::findOrFail($id);
        $this->authorize('update', $connection);

        $data = $request->validated();

        // Si se actualizan credenciales, probar conexión
        if (isset($data['password']) || isset($data['host'])) {
            $testData = array_merge($connection->toArray(), $data);
            $testResult = $this->connectionService->testConnection($testData);
            if (!$testResult['success']) {
                return response()->json([
                    'message' => 'Error de conexión',
                    'error' => $testResult['message'],
                ], 422);
            }
        }

        $updated = $this->connectionService->update($connection, $data);

        return response()->json([
            'message' => 'Conexión actualizada exitosamente',
            'data' => $updated,
        ]);
    }

    /**
     * DELETE /api/import/connections/{id}
     * Eliminar conexión externa
     */
    public function destroyConnection(int $id): JsonResponse
    {
        $connection = ExternalConnection::findOrFail($id);
        $this->authorize('delete', $connection);

        // Verificar si tiene logs asociados
        $hasLogs = MigrationLog::where('connection_id', $id)->exists();
        if ($hasLogs) {
            return response()->json([
                'message' => 'No se puede eliminar la conexión porque tiene migraciones asociadas',
            ], 422);
        }

        $this->connectionService->delete($connection);

        return response()->json([
            'message' => 'Conexión eliminada exitosamente',
        ]);
    }

    /**
     * POST /api/import/connections/test
     * Probar conexión externa
     */
    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|exists:external_connections,id',
        ]);

        $connection = ExternalConnection::findOrFail($request->connection_id);
        $this->authorize('view', $connection);

        $result = $this->connectionService->testConnection($connection->toArray());

        return response()->json($result);
    }

    // ================================================================
    // MIGRACIÓN DESDE BD EXTERNA
    // ================================================================

    /**
     * GET /api/import/migration/years
     * Obtener años disponibles
     */
    public function years(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|exists:external_connections,id',
        ]);

        $connection = ExternalConnection::findOrFail($request->connection_id);
        $this->authorize('view', $connection);

        $years = $this->connectionService->getAvailableYears($connection);

        return response()->json(['years' => $years]);
    }

    /**
     * GET /api/import/migration/months
     * Obtener meses disponibles
     */
    public function months(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|exists:external_connections,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
        ]);

        $connection = ExternalConnection::findOrFail($request->connection_id);
        $this->authorize('view', $connection);

        $months = $this->connectionService->getAvailableMonths($connection, $request->year);

        return response()->json(['months' => $months]);
    }

    /**
     * GET /api/import/migration/banks
     * Obtener bancos disponibles en el sistema externo
     */
    public function migrationBanks(Request $request): JsonResponse
    {
        $request->validate([
            'connection_id' => 'required|exists:external_connections,id',
            'year' => 'required|integer',
            'month' => 'required|integer|between:1,12',
        ]);

        $connection = ExternalConnection::findOrFail($request->connection_id);
        $this->authorize('view', $connection);

        $banks = $this->connectionService->getAvailableBanks(
            $connection,
            $request->year,
            $request->month
        );

        return response()->json(['banks' => $banks]);
    }

    /**
     * POST /api/import/migration/preview
     * Previsualizar datos a migrar
     */
    public function preview(PreviewRequest $request): JsonResponse
    {
        $connection = ExternalConnection::findOrFail($request->connection_id);
        $this->authorize('view', $connection);

        $result = $this->importService->previewMigration(
            connection: $connection,
            year: $request->year,
            month: $request->month,
            bankId: $request->bank_id,
            companyId: auth()->user()->current_company_id,
            userId: auth()->id(),
        );

        return response()->json($result);
    }

    /**
     * POST /api/import/migration/execute
     * Ejecutar migración
     */
    public function execute(ExecuteRequest $request): JsonResponse
    {
        $connection = ExternalConnection::findOrFail($request->connection_id);
        $this->authorize('view', $connection);

        $result = $this->importService->executeMigration(
            connection: $connection,
            sessionId: $request->session_id,
            year: $request->year,
            month: $request->month,
            mappings: $request->mappings ?? [],
            companyId: auth()->user()->current_company_id,
            userId: auth()->id(),
        );

        return response()->json($result);
    }

    /**
     * GET /api/import/migration/logs
     * Historial de migraciones
     */
    public function logs(Request $request): JsonResponse
    {
        $user = auth()->user();
        $companyId = $user->current_company_id;
        $limit = $request->limit ?? 50;

        if ($user->hasRole('super_admin')) {
            $logs = MigrationLog::with(['company', 'creator', 'connection'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } else {
            $logs = MigrationLog::where('company_id', $companyId)
                ->with(['creator', 'connection'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        }

        return response()->json($logs);
    }

    // ================================================================
    // IMPORTACIÓN DESDE EXCEL
    // ================================================================

    /**
     * GET /api/import/excel/banks
     * Obtener bancos para importación de Excel
     */
    public function excelBanks(): JsonResponse
    {
        $banks = \App\Models\Bank::active()->ordered()->get();

        // Agregar información de parser
        $banks = $banks->map(function ($bank) {
            $config = $this->parserService->getBankConfig($bank->id);
            return [
                'id' => $bank->id,
                'name' => $bank->name,
                'code' => $bank->code,
                'has_parser' => $config !== null,
            ];
        });

        return response()->json($banks);
    }

    /**
     * GET /api/import/excel/bank-accounts
     * Obtener cuentas bancarias de la empresa
     */
    public function excelBankAccounts(): JsonResponse
    {
        $companyId = auth()->user()->current_company_id;

        $accounts = \App\Models\BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['bank', 'currency'])
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'alias' => $account->alias,
                    'account_number' => $account->account_number,
                    'bank_name' => $account->bank?->name,
                    'currency' => $account->currency?->code,
                ];
            });

        return response()->json($accounts);
    }

    /**
     * POST /api/import/excel/upload
     * Subir y procesar archivo Excel
     */
    public function upload(UploadRequest $request): JsonResponse
    {
        $companyId = auth()->user()->current_company_id;
        $userId = auth()->id();

        $result = $this->importService->startExcelImport(
            companyId: $companyId,
            userId: $userId,
            filePath: $request->file('file')->store('imports', 'public'),
            bankCode: $request->bank_code,
            bankAccountId: $request->bank_account_id,
        );

        return response()->json([
            'message' => 'Archivo procesado correctamente',
            'data' => $result,
        ]);
    }

    /**
     * POST /api/import/excel/map
     * Mapear transacciones importadas
     */
    public function map(MapRequest $request): JsonResponse
    {
        $companyId = auth()->user()->current_company_id;

        $result = $this->importService->processImportedTransactions(
            companyId: $companyId,
            sessionId: $request->session_id,
            mappings: $request->mappings,
            userId: auth()->id(),
        );

        return response()->json($result);
    }

    /**
     * GET /api/import/sessions
     * Obtener sesiones de importación
     */
    public function sessions(Request $request): JsonResponse
    {
        $companyId = auth()->user()->current_company_id;

        $sessions = \App\Models\ImportSession::where('company_id', $companyId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($request->limit ?? 50)
            ->get();

        return response()->json($sessions);
    }

    /**
     * GET /api/import/sessions/{id}
     * Obtener sesión de importación específica
     */
    public function showSession(int $id): JsonResponse
    {
        $companyId = auth()->user()->current_company_id;

        $session = \App\Models\ImportSession::where('company_id', $companyId)
            ->with(['user'])
            ->findOrFail($id);

        // Obtener transacciones importadas
        $transactions = ImportedTransaction::where('company_id', $companyId)
            ->where('import_session_id', $session->id)
            ->get();

        return response()->json([
            'session' => $session,
            'transactions' => $transactions,
        ]);
    }
}