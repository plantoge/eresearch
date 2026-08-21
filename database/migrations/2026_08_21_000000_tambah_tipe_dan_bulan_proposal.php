<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor proposal berganti format menjadi 10 digit: tipe(2) + tahun(2) +
     * bulan(2) + urut(4) — mis. 0126080001. Dua bagian pertama belum punya
     * kolom, jadi ditambahkan di sini.
     *
     * `tahun` yang sudah ada TETAP menyimpan tahun penuh (2026); pemendekan
     * jadi dua digit terjadi saat memformat, supaya unique(tahun, nomor) tetap
     * berarti "satu deret per tahun" dan tidak ambigu lintas abad.
     */
    public function up(): void
    {
        // Default sementara supaya migrate tidak gagal di database yang sudah
        // berisi baris; dilepas lagi di bawah agar nilainya wajib datang dari
        // pemanggil, bukan diam-diam dari database.
        Schema::table('rspi.proposal', function (Blueprint $t) {
            $t->string('tipe_proposal', 2)->default('01');   // enum TipeProposal
            $t->unsignedTinyInteger('bulan')->default(1);    // 1..12, bulan terbitnya nomor
        });

        DB::statement('update rspi.proposal set bulan = extract(month from created_at)');

        DB::statement('alter table rspi.proposal alter column tipe_proposal drop default');
        DB::statement('alter table rspi.proposal alter column bulan drop default');
    }

    public function down(): void
    {
        Schema::table('rspi.proposal', function (Blueprint $t) {
            $t->dropColumn(['tipe_proposal', 'bulan']);
        });
    }
};
