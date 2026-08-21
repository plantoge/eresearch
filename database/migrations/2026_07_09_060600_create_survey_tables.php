<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rspi.master_aspek', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->string('nama_aspek');
            $t->string('deskripsi')->nullable();
            $t->unsignedInteger('urutan')->default(0);
            $t->boolean('status_aktif')->default(true);
        });

        Schema::create('rspi.master_pertanyaan', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('master_aspek_id');
            $t->text('pertanyaan');
            $t->boolean('is_required')->default(true);
            $t->unsignedInteger('urutan')->default(0);
            $t->boolean('status_aktif')->default(true);

            $t->index('master_aspek_id');
        });

        Schema::create('rspi.master_skala', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->string('nama_skala');
            $t->integer('nilai');
            $t->unsignedInteger('urutan')->default(0);
        });

        Schema::create('rspi.respon', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('proposal_id');                 // D5: gate survey per proposal
            $t->uuid('responden_id');
            $t->string('responden')->nullable();     // snapshot nama
            $t->string('jenis_responden')->nullable();
            $t->text('saran')->nullable();

            $t->index('responden_id');
        });

        // Satu survey aktif per proposal — partial unique agar baris soft-deleted tak menghalangi
        DB::statement('create unique index respon_proposal_id_unique on rspi.respon (proposal_id) where deleted_at is null');

        Schema::create('rspi.jawaban', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('respon_id');
            $t->uuid('master_pertanyaan_id');
            $t->uuid('master_skala_id');
            $t->text('pertanyaan')->nullable();      // snapshot teks
            $t->text('jawaban')->nullable();         // snapshot nilai

            $t->index('respon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.jawaban');
        Schema::dropIfExists('rspi.respon');
        Schema::dropIfExists('rspi.master_skala');
        Schema::dropIfExists('rspi.master_pertanyaan');
        Schema::dropIfExists('rspi.master_aspek');
    }
};
