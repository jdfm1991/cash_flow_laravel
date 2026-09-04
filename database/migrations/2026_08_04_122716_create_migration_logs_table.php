<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('connection_id')
                ->constrained('external_connections')
                ->cascadeOnDelete();

            $table->enum('migration_type', ['income', 'expense', 'all'])
                ->default('all');

            $table->integer('year');
            $table->integer('month');

            $table->integer('total_records')->default(0);
            $table->integer('imported_records')->default(0);
            $table->integer('duplicated_records')->default(0);
            $table->integer('failed_records')->default(0);

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending');

            $table->text('error_log')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('company_id');
            $table->index('connection_id');
            $table->index('status');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_logs');
    }
};