<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cvs', function (Blueprint $table) {
            $table->unsignedBigInteger('revision')->default(1)->after('professional_summary');
        });

        DB::statement('ALTER TABLE cvs ADD CONSTRAINT cvs_revision_check CHECK (revision >= 1)');
    }

    public function down(): void
    {
        Schema::table('cvs', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
    }
};
