<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Seed roles inline since we need them for users FK
        DB::table('roles')->insert([
            ['name' => 'operator', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'supervisor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'security_admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};