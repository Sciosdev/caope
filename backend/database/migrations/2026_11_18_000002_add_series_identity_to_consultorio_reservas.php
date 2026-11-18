<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'consultorio_reservas';

    private const INDEX = 'consultorio_reservas_serie_fecha_inicio_idx';

    public function up(): void
    {
        // Identical schedules are not evidence that historical rows belong together.
        if (! Schema::hasColumn(self::TABLE, 'serie_uuid')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->uuid('serie_uuid')->nullable();
            });
        }
        if (! Schema::hasIndex(self::TABLE, ['serie_uuid', 'fecha', 'hora_inicio', 'id'])) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->index(['serie_uuid', 'fecha', 'hora_inicio', 'id'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
        if (Schema::hasColumn(self::TABLE, 'serie_uuid')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->dropColumn('serie_uuid');
            });
        }
    }
};
