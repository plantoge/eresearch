<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berkas kerja per unit. Satu pengajuan tetap satu baris `proposal` dengan satu
     * rantai status; yang dipisah adalah DATA dan KEPUTUSAN milik masing-masing unit.
     *
     * Baris satelit dibuat saat unit pertama kali menyentuh proposal, bukan saat
     * pengajuan — supaya keberadaan baris berarti sesuatu.
     */
    public function up(): void
    {
        // ================= CRU =================

        Schema::create('rspi.cru_berkas_penelitian', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');
            $t->timestamp('tanggal_presentasi')->nullable();
            $t->string('kategori_presentasi')->nullable();
            $t->string('media_presentasi')->nullable();
            $t->text('catatan_verifikasi')->nullable();
            $t->uuid('diverifikasi_oleh')->nullable();
            $t->timestamp('diverifikasi_pada')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
        });

        DB::statement('create unique index cru_berkas_penelitian_unique on rspi.cru_berkas_penelitian (proposal_id) where deleted_at is null');

        // Dua pembayaran terpisah (CRU + KEPK). Sebelumnya pembayaran hanya hidup
        // sebagai dua nilai enum DocumentType — tanpa nominal, verifikator, atau alasan tolak.
        Schema::create('rspi.cru_pembayaran', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');
            $t->string('tujuan');                        // enum TujuanPembayaran: cru|kepk
            $t->unsignedBigInteger('nominal')->nullable(); // rupiah sebagai integer, bukan desimal
            $t->string('status')->default('menunggu');   // enum StatusPembayaran
            $t->uuid('dokumen_id')->nullable();          // baris bukti bayar di proposal_documents
            $t->uuid('diverifikasi_oleh')->nullable();
            $t->timestamp('diverifikasi_pada')->nullable();
            $t->text('catatan')->nullable();             // alasan bila ditolak
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();

            $t->index(['proposal_id', 'status']);
        });

        DB::statement('create unique index cru_pembayaran_unique on rspi.cru_pembayaran (proposal_id, tujuan) where deleted_at is null');

        Schema::create('rspi.cru_izin_penelitian', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');
            $t->string('nomor_izin')->nullable();
            $t->timestamp('tanggal_terbit_draft')->nullable();
            $t->timestamp('tanggal_terbit_final')->nullable();
            $t->date('berlaku_sampai')->nullable();
            $t->uuid('diterbitkan_oleh')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
        });

        DB::statement('create unique index cru_izin_penelitian_unique on rspi.cru_izin_penelitian (proposal_id) where deleted_at is null');

        // ================= KEPK =================

        Schema::create('rspi.kepk_protokol_etik', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');
            $t->string('nomor_protokol')->nullable();      // registrasi KEPK, terpisah dari RSPISS-YYYY-###
            $t->string('jenis_telaah')->nullable();        // enum JenisTelaah
            $t->timestamp('tanggal_sidang')->nullable();
            $t->string('keputusan')->nullable();           // enum KeputusanEtik
            $t->string('nomor_ec')->nullable();            // ethical clearance yang diterbitkan
            $t->timestamp('tanggal_terbit_ec')->nullable();
            $t->date('berlaku_sampai')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
        });

        DB::statement('create unique index kepk_protokol_etik_unique on rspi.kepk_protokol_etik (proposal_id) where deleted_at is null');
        DB::statement('create unique index kepk_protokol_etik_nomor_unique on rspi.kepk_protokol_etik (nomor_protokol) where deleted_at is null and nomor_protokol is not null');

        // File tanggapan reviewer. Berdiri sendiri supaya peneliti tidak punya satu pun
        // route yang menjangkaunya — kerahasiaan jadi sifat struktur, bukan sebuah if.
        Schema::create('rspi.kepk_dokumen_telaah', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('proposal_id');
            $t->uuid('telaah_id')->nullable();
            $t->string('path');
            $t->string('nama_asli')->nullable();
            $t->unsignedSmallInteger('versi')->default(1);
            $t->uuid('uploaded_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();

            $t->index('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.kepk_dokumen_telaah');
        Schema::dropIfExists('rspi.kepk_protokol_etik');
        Schema::dropIfExists('rspi.cru_izin_penelitian');
        Schema::dropIfExists('rspi.cru_pembayaran');
        Schema::dropIfExists('rspi.cru_berkas_penelitian');
    }
};
