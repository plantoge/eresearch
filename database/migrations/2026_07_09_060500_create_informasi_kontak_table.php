<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rspi.informasi_kontak', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            // Kontak
            $t->string('telepon')->nullable();
            $t->string('fax')->nullable();
            $t->string('callcenter')->nullable();
            $t->string('hotline')->nullable();
            $t->string('email')->nullable();
            $t->text('alamat')->nullable();
            $t->text('deskripsi_alamat')->nullable();
            // Sosial media
            $t->string('facebook')->nullable();
            $t->string('instagram')->nullable();
            $t->string('twitter')->nullable();
            $t->string('whatsapp')->nullable();
            // Contact person layanan
            $t->string('cp_kaji_etik')->nullable();
            $t->string('wa_kaji_etik')->nullable();
            $t->string('cp_pks')->nullable();
            $t->string('wa_pks')->nullable();
            $t->string('cp_mta')->nullable();
            $t->string('wa_mta')->nullable();
            $t->string('cp_kerahasiaan')->nullable();
            $t->string('wa_kerahasiaan')->nullable();
            // Pembayaran
            $t->string('pemilik_rekening')->nullable();
            $t->string('nomor_rekening')->nullable();
            $t->string('nama_bank')->nullable();
            $t->string('logo_bank')->nullable();
            $t->text('deskripsi_biaya')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.informasi_kontak');
    }
};
