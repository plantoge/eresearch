<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiga keterangan Poin A Formulir Pengajuan Etik yang belum pernah ditanyakan.
     *
     * Ditaruh di `proposal`, bukan di berkas kerja KEPK: ketiganya adalah sifat
     * penelitian yang sudah pasti sejak pengajuan dan tidak berubah antar tahap.
     * Menaruhnya di berkas KEPK berarti peneliti baru ditanya di tahap 2, padahal
     * CRU sudah butuh tahu lokasi & jenisnya saat memverifikasi berkas awal.
     *
     * Nullable karena proposal yang sudah terlanjur diajukan tidak punya jawabannya;
     * kewajiban mengisi ditegakkan di form pengajuan, bukan di skema.
     */
    public function up(): void
    {
        Schema::table('rspi.proposal', function (Blueprint $t) {
            $t->string('sponsor')->nullable();            // A.6 sponsor / pemberi grant
            $t->string('jenis_penelitian')->nullable();   // A.7 enum JenisPenelitian
            $t->string('lokasi_penelitian')->nullable();  // A.8
        });
    }

    public function down(): void
    {
        Schema::table('rspi.proposal', function (Blueprint $t) {
            $t->dropColumn(['sponsor', 'jenis_penelitian', 'lokasi_penelitian']);
        });
    }
};
