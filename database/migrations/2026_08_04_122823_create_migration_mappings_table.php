<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_mappings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('connection_id')
                ->constrained('external_connections')
                ->cascadeOnDelete();

            $table->string('source_table', 100);
            $table->string('source_field', 100);
            $table->string('source_value', 255);

            $table->enum('target_type', ['account', 'bank', 'category'])
                ->default('account');

            $table->integer('target_id');

            $table->timestamps();

            // Un mapeo único por fuente y valor
            $table->unique(
                ['connection_id', 'source_table', 'source_field', 'source_value'],
                'unique_mapping'
            );

            $table->index('company_id');
            $table->index('connection_id');
            $table->index('target_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_mappings');
    }
};