<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Penugasan reviewer oleh KEPK (bisa >1 reviewer per proposal). */
    public function up(): void
    {
        Schema::create('rspi.kepk_penugasan_reviewer', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();
            $t->uuid('proposal_id');
            $t->uuid('reviewer_id');
            $t->string('status')->default('menunggu'); // menunggu|acc|revisi

            $t->index(['reviewer_id', 'status']);
        });

        DB::statement('create unique index kepk_penugasan_reviewer_unique on rspi.kepk_penugasan_reviewer (proposal_id, reviewer_id) where deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.kepk_penugasan_reviewer');
    }
};
