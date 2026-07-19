<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // `TEXT` (64KB) ainda estourava: para algumas contas o Microsoft Graph não devolve uma
            // URL de foto, devolve a foto embutida em base64 (`data:image/jpeg;base64,...`), que
            // passa fácil de 64KB. `LONGTEXT` remove esse teto na prática.
            $table->longText('avatar_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('avatar_url')->nullable()->change();
        });
    }
};
