<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FK circular com `workflow_versions` — precisa vir depois que a tabela existir
     * (01-modelo-de-dados.md §6, passo 7). Atalho para "a versão vigente"; novas instâncias
     * sempre usam esta (§2.1).
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->foreignId('current_published_version_id')
                ->nullable()
                ->after('slug')
                ->constrained('workflow_versions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_published_version_id');
        });
    }
};
