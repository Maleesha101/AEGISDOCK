<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => 'operator', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'supervisor', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'security_admin', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
