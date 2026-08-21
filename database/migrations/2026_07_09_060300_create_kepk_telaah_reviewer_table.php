<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telaah per ronde — ditulis reviewer, dan KEPK saat menolak etik.
     * Isinya rahasia dari peneliti; karena itu berdiri sendiri di kelompok kepk_.
     *
     * Kolom `tahap` dibuang: telaah hanya pernah terjadi di Tahap 2, nilainya
     * selalu 2 dan tidak pernah dibaca.
     */
    public function up(): void
    {
        Schema::create('rspi.kepk_telaah_reviewer', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('proposal_id');
            $t->string('unit');                      // enum Unit — pembeda reviewer vs KEPK
            $t->uuid('reviewer_id')->nullable();
            $t->string('keputusan');                 // approve|revise|reject
            $t->text('komentar')->nullable();
            $t->unsignedSmallInteger('ronde')->default(1);

            $t->index(['proposal_id', 'ronde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.kepk_telaah_reviewer');
    }
};
