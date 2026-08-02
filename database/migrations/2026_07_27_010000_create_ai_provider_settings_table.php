<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->string('model')->nullable();
            $table->timestamps();
        });

        // Ordem inicial reproduz o fallback fixo que existia antes desta tabela (Ollama ->
        // Azure OpenAI -> Gemini -> OpenRouter), com Anthropic entrando como segunda opção —
        // ajustável depois em /admin (AiProviderSettingResource). "enabled"/"priority" só
        // decidem a ordem de tentativa; a credencial de fato continua vindo do .env
        // (config/services.php) — um provedor habilitado aqui mas sem credencial configurada
        // é ignorado em runtime (AiWorkflowDraftGenerator::isProviderConfigured()).
        DB::table('ai_provider_settings')->insert([
            ['provider' => 'ollama', 'enabled' => true, 'priority' => 10, 'model' => null, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'anthropic', 'enabled' => true, 'priority' => 20, 'model' => null, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'azure', 'enabled' => true, 'priority' => 30, 'model' => null, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'gemini', 'enabled' => true, 'priority' => 40, 'model' => null, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'openrouter', 'enabled' => true, 'priority' => 50, 'model' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};
