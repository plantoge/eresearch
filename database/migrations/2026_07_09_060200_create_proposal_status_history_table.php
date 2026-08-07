<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit sengaja TIDAK dipecah per unit: satu proposal melintasi CRU dan
     * KEPK, dan riwayat yang terpotong justru merusak hal yang mau dilindungi.
     */
    public function up(): void
    {
        Schema::create('rspi.proposal_status_history', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');
            $t->string('from_status')->nullable();
            $t->string('to_status');
            $t->string('unit')->nullable();          // enum Unit (D3)
            $t->uuid('actor_id')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();

            $t->index('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.proposal_status_history');
    }
};
