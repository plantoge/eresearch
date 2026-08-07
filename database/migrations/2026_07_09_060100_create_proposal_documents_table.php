<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berkas peneliti & surat resmi — dipakai lintas unit, jadi tetap di kernel.
     * Berkas rahasia telaah reviewer TIDAK di sini; lihat rspi.kepk_dokumen_telaah.
     */
    public function up(): void
    {
        Schema::create('rspi.proposal_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');                 // relasi proposal (FK menyusul)
            $t->string('jenis');                     // enum DocumentType
            $t->string('path');                      // lokasi file di storage
            $t->string('nama_asli')->nullable();
            $t->unsignedSmallInteger('versi')->default(1);
            $t->uuid('uploaded_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();

            $t->index(['proposal_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.proposal_documents');
    }
};
