<?php

namespace App\Console\Commands;

use App\Services\PdfExportService;
use Illuminate\Console\Command;

class CleanTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:temp-files 
                            {--hours=1 : Eliminar archivos más antiguos que X horas}
                            {--dry-run : Simular limpieza sin eliminar archivos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpiar archivos temporales antiguos de PDFs y gráficos';

    /**
     * Execute the console command.
     */
    public function handle(PdfExportService $pdfService): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        
        $this->info('🔍 Iniciando limpieza de archivos temporales...');
        $this->info("📋 Configuración: {$hours} horas de antigüedad");
        
        if ($dryRun) {
            $this->warn('⚠️ Modo SIMULACIÓN activado. No se eliminarán archivos.');
        }

        // Obtener directorios a limpiar
        $directories = [
            storage_path('app/temp/pdf'),
            storage_path('app/temp/charts'),
        ];

        $totalDeleted = 0;
        $totalSize = 0;
        $expirationTime = time() - ($hours * 3600);

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $this->warn("⚠️ Directorio no encontrado: {$directory}");
                continue;
            }

            $files = glob($directory . '/*.*');
            $count = 0;
            $size = 0;

            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $fileTime = filemtime($file);
                
                if ($fileTime < $expirationTime) {
                    $size += filesize($file);
                    $count++;
                    
                    if (!$dryRun) {
                        if (@unlink($file)) {
                            $this->line("🗑️ Eliminado: " . basename($file));
                        } else {
                            $this->error("❌ Error eliminando: " . basename($file));
                        }
                    } else {
                        $this->line("📄 Simulación: " . basename($file) . " (" . $this->formatSize(filesize($file)) . ")");
                    }
                }
            }

            if ($count > 0) {
                $this->info("📂 {$directory}: {$count} archivos eliminados ({$this->formatSize($size)})");
                $totalDeleted += $count;
                $totalSize += $size;
            } else {
                $this->info("✅ {$directory}: No hay archivos antiguos");
            }
        }

        // Resumen final
        $this->newLine();
        $this->line('═══════════════════════════════════════════════');
        
        if ($dryRun) {
            $this->warn("⚠️ SIMULACIÓN COMPLETADA");
            $this->warn("📊 Archivos a eliminar: {$totalDeleted}");
            $this->warn("📊 Espacio a liberar: {$this->formatSize($totalSize)}");
            $this->warn("💡 Ejecuta sin --dry-run para eliminar realmente");
        } else {
            $this->info("✅ LIMPIEZA COMPLETADA");
            $this->info("📊 Archivos eliminados: {$totalDeleted}");
            $this->info("📊 Espacio liberado: {$this->formatSize($totalSize)}");
        }
        
        $this->line('═══════════════════════════════════════════════');

        return Command::SUCCESS;
    }

    /**
     * Formatear tamaño de archivo
     */
    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}