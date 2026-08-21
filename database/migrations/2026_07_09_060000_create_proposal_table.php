<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kernel proposal: identitas pengajuan + status. Data kerja tiap unit ada di
     * tabel cru_* / kepk_* masing-masing, bukan sebagai kolom di sini.
     */
    public function up(): void
    {
        Schema::create('rspi.proposal', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->string('tipe_proposal', 2);               // enum TipeProposal: 01 internal, 02 eksternal
            $t->unsignedSmallInteger('tahun');            // D6: empat digit; deret nomor per tahun
            $t->unsignedTinyInteger('bulan');             // 1..12, bulan terbitnya nomor
            $t->unsignedBigInteger('nomor');
            $t->string('kode')->unique();                 // D6: TTYYMMNNNN, mis. 0126080001
            $t->string('peneliti_utama');
            $t->text('tim_peneliti')->nullable();
            $t->text('judul_penelitian');
            $t->string('institusi_asal')->nullable();     // snapshot pengaju
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->uuid('user_id');                          // relasi users (FK menyusul)
            $t->string('status');                         // cast enum ProposalStatus
            $t->string('unit_sekarang')->nullable();      // D2: turunan status, materialized

            $t->unique(['tahun', 'nomor']);
            $t->index('unit_sekarang');
            $t->index('status');
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.proposal');
    }
};
