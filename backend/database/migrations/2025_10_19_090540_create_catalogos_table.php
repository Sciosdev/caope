<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo_carreras')) {
            Schema::create('catalogo_carreras', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalogo_turnos')) {
            Schema::create('catalogo_turnos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalogo_padecimientos')) {
            Schema::create('catalogo_padecimientos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('catalogo_tratamientos')) {
            Schema::create('catalogo_tratamientos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_tratamientos');
        Schema::dropIfExists('catalogo_padecimientos');
        Schema::dropIfExists('catalogo_turnos');
        Schema::dropIfExists('catalogo_carreras');
    }
};
