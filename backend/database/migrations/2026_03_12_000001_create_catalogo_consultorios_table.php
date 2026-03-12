<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo_consultorios')) {
            Schema::create('catalogo_consultorios', function (Blueprint $table): void {
                $table->id();
                $table->string('nombre')->unique();
                $table->unsignedTinyInteger('numero')->unique();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_consultorios');
    }
};
