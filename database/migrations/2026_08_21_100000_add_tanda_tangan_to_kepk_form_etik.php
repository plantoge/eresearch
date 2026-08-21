<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanda tangan peneliti utama pada Formulir Pengajuan Etik.
     *
     * `text`, bukan berkas di disk: isinya data URL PNG dari kanvas tanda tangan
     * (lihat App\Support\TandaTangan) yang merupakan bagian dari isian formulir,
     * bukan lampiran yang punya versi sendiri.
     *
     * Nullable karena formulir yang sudah terlanjur dikirim sebelum ada tanda
     * tangan tidak punya jawabannya; kewajiban ditegakkan di form, bukan skema,
     * supaya berkas lama tidak terkunci.
     */
    public function up(): void
    {
        Schema::table('rspi.kepk_form_etik', function (Blueprint $t) {
            $t->text('tanda_tangan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rspi.kepk_form_etik', function (Blueprint $t) {
            $t->dropColumn('tanda_tangan');
        });
    }
};
