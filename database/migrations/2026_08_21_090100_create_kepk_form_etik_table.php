<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formulir Pengajuan Etik yang diisi peneliti (Poin B & C) — 1:1 dengan proposal.
     *
     * Sengaja TERPISAH dari `kepk_protokol_etik`: tabel itu berkas kerja KEPK
     * (nomor protokol, jenis telaah, keputusan) yang hanya boleh disentuh KEPK,
     * sedangkan tabel ini isian peneliti. Menggabungkannya berarti satu baris
     * ditulis oleh dua pihak dengan wewenang berbeda.
     *
     * Poin A tidak ada di sini — jawabannya sudah ada di `proposal` sejak
     * pengajuan, dan menyalinnya ke sini akan melahirkan dua versi judul yang
     * bisa berbeda.
     */
    public function up(): void
    {
        Schema::create('rspi.kepk_form_etik', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('proposal_id');

            // Poin B — ceklist a–j sebagai satu objek {huruf: bool}. Bukan sepuluh
            // kolom boolean: butirnya adalah daftar di formulir, tidak pernah
            // dicari satu per satu lewat query, dan daftarnya bisa berubah kalau
            // KEPK merevisi formulirnya.
            $t->json('kelengkapan')->nullable();

            // Poin C
            $t->boolean('multisenter')->nullable();          // C.1
            $t->string('senter_utama')->nullable();
            $t->string('senter_satelit')->nullable();
            $t->string('kerjasama')->nullable();             // C.2 enum BentukKerjasama
            $t->string('jumlah_negara')->nullable();         // diisi bila internasional
            $t->boolean('peneliti_asing')->nullable();       // ketua peneliti asing
            $t->boolean('pernah_diajukan')->nullable();      // C.3 ke komisi etik lain
            $t->boolean('disetujui_komisi_lain')->nullable();
            $t->boolean('sampel_ke_luar_negeri')->nullable(); // C.4
            $t->string('negara_tujuan')->nullable();
            $t->text('registrasi_bpom')->nullable();         // C.5 jawaban bebas

            $t->timestamp('dikirim_pada')->nullable();
        });

        DB::statement('create unique index kepk_form_etik_unique on rspi.kepk_form_etik (proposal_id) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.kepk_form_etik');
    }
};
