<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rspi.menus', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('nama');
            $t->string('slug')->unique();            // → permission {slug}.read|create|update|delete
            $t->string('route')->nullable();
            $t->string('icon')->nullable();
            $t->uuid('parent_id')->nullable();
            $t->unsignedInteger('urutan')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->auditColumns();

            $t->index(['parent_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rspi.menus');
    }
};
