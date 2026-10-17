<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flag_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('flag_code', 16);
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['user_id', 'flag_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_submissions');
    }
};
