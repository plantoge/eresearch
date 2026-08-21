<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formulir Informed Consent yang diisi peneliti — 1:1 dengan proposal.
     *
     * Hanya **Lembar Informasi** yang ada di sini. Lembar Persetujuan tidak
     * punya kolom sama sekali: ia ditandatangani subjek penelitian di lapangan —
     * orang yang tidak punya akun di aplikasi ini — jadi aplikasi mencetaknya
     * kosong sebagai templat. Yang ditelaah KEPK memang templatnya, bukan
     * persetujuan yang sudah terkumpul; menyimpan identitas subjek di sini
     * berarti menampung data pribadi yang tidak dibutuhkan siapa pun di alur ini.
     */
    public function up(): void
    {
        Schema::create('rspi.kepk_informed_consent', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('proposal_id');

            // Penelitian yang tidak pernah menghubungi subjek (mis. rekam medis
            // retrospektif) tidak memerlukan informed consent. Alasannya tetap
            // dicatat supaya KEPK menilai keputusannya, bukan menebak.
            $t->boolean('merekrut_partisipan')->nullable();
            $t->text('alasan_tanpa_consent')->nullable();

            // Kalimat pembuka: "Saya adalah ...peran... yang berasal dari
            // ...instansi... yang sedang melakukan penelitian untuk ...maksud..."
            // Instansi tidak disimpan di sini — sudah ada di `proposal`.
            $t->string('peran_peneliti')->nullable();
            $t->text('maksud_penelitian')->nullable();

            // 14 bagian naratif sebagai satu objek {bagian: teks} — lihat
            // App\Enums\BagianLembarInformasi.
            $t->json('lembar_informasi')->nullable();

            $t->text('tanda_tangan')->nullable();
            $t->timestamp('dikirim_pada')->nullable();
        });

        DB::statement('create unique index kepk_informed_consent_unique on rspi.kepk_informed_consent (proposal_id) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.kepk_informed_consent');
    }
};
