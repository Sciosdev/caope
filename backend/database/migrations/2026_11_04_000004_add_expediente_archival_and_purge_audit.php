<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expedientes', 'archived_by')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->unsignedBigInteger('archived_by')->nullable()->after('cubiculo_id');
            });
        }

        if (! Schema::hasColumn('expedientes', 'retention_until')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                // DATETIME evita el límite 2038 de TIMESTAMP: una retención de
                // diez años creada desde 2028 ya puede superar ese rango.
                $table->dateTime('retention_until')->nullable()->after('archived_by');
            });
        }

        if (! Schema::hasColumn('expedientes', 'deleted_at')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->softDeletes()->after('retention_until');
            });
        }

        $this->ensureIndex('expedientes', ['archived_by']);
        $this->ensureIndex('expedientes', ['retention_until']);
        $this->ensureIndex('expedientes', ['deleted_at']);

        $hasArchivedByForeignKey = collect(Schema::getForeignKeys('expedientes'))
            ->contains(fn (array $key): bool => $key['columns'] === ['archived_by']);

        if (! $hasArchivedByForeignKey) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('expediente_lifecycle_audits')) {
            Schema::create('expediente_lifecycle_audits', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('expediente_id');
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('folio', 50);
                $table->string('action', 40);
                $table->text('motivo');
                $table->timestamp('created_at')->useCurrent();
                $table->index('expediente_id');
                $table->index('actor_id');
                $table->index(['action', 'created_at']);
            });
        }

        $this->ensureIndex('expediente_lifecycle_audits', ['expediente_id']);
        $this->ensureIndex('expediente_lifecycle_audits', ['actor_id']);
        $this->ensureIndex('expediente_lifecycle_audits', ['action', 'created_at']);

        if (! Schema::hasTable('expediente_file_deletions')) {
            Schema::create('expediente_file_deletions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('audit_id');
                $table->unsignedBigInteger('expediente_id');
                $table->string('disk', 100);
                $table->text('path');
                $table->char('deduplication_key', 64)->unique();
                $table->string('status', 30)->default('pending');
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index('audit_id');
                $table->index('expediente_id');
                $table->index(['status', 'created_at']);
            });
        }

        $this->ensureIndex('expediente_file_deletions', ['audit_id']);
        $this->ensureIndex('expediente_file_deletions', ['expediente_id']);
        $this->ensureIndex('expediente_file_deletions', ['status', 'created_at']);
        $this->ensureIndex('expediente_file_deletions', ['deduplication_key'], unique: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('expediente_file_deletions');
        Schema::dropIfExists('expediente_lifecycle_audits');

        if (Schema::hasColumn('expedientes', 'archived_by')) {
            $foreignKeys = collect(Schema::getForeignKeys('expedientes'))
                ->filter(fn (array $key): bool => $key['columns'] === ['archived_by']);

            foreach ($foreignKeys as $foreignKey) {
                Schema::table('expedientes', function (Blueprint $table) use ($foreignKey): void {
                    $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
                });
            }
        }

        foreach ([['deleted_at'], ['retention_until'], ['archived_by']] as $columns) {
            if (! Schema::hasIndex('expedientes', $columns)) {
                continue;
            }

            $indexes = collect(Schema::getIndexes('expedientes'))
                ->filter(fn (array $index): bool => $index['columns'] === $columns && ! $index['primary']);

            foreach ($indexes as $index) {
                Schema::table('expedientes', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index['name']);
                });
            }
        }

        $columns = collect(['deleted_at', 'retention_until', 'archived_by'])
            ->filter(fn (string $column): bool => Schema::hasColumn('expedientes', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('expedientes', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $tableName, array $columns, bool $unique = false): void
    {
        if (Schema::hasIndex($tableName, $columns, $unique ? 'unique' : null)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $unique): void {
            if ($unique) {
                $table->unique($columns);

                return;
            }

            $table->index($columns);
        });
    }
};
