<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_administrativos', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo', 200);
            $table->string('ruta');
            $table->string('disk', 50)->default('private');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_administrativos');
    }
};
