<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits each ALTER separately. Resume after an interrupted
        // migration without replacing columns or their existing audit data.
        Schema::table('deployment_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('deployment_runs', 'agent_status')) {
                $table->string('agent_status', 40)->nullable();
            }
            if (! Schema::hasColumn('deployment_runs', 'agent_stage')) {
                $table->string('agent_stage', 40)->nullable();
            }
            if (! Schema::hasColumn('deployment_runs', 'agent_started_at')) {
                $table->timestamp('agent_started_at')->nullable();
            }
            if (! Schema::hasColumn('deployment_runs', 'agent_finished_at')) {
                $table->timestamp('agent_finished_at')->nullable();
            }
            if (! Schema::hasColumn('deployment_runs', 'agent_heartbeat_at')) {
                $table->timestamp('agent_heartbeat_at')->nullable();
            }
            if (! Schema::hasColumn('deployment_runs', 'agent_result_key')) {
                $table->string('agent_result_key', 64)->nullable();
            }
            if (! Schema::hasColumn('deployment_runs', 'agent_result_at')) {
                $table->timestamp('agent_result_at')->nullable();
            }
        });

        // The process may also stop between adding the columns and the index.
        if (! Schema::hasIndex('deployment_runs', 'deployment_runs_agent_status_index')) {
            Schema::table('deployment_runs', function (Blueprint $table): void {
                $table->index('agent_status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('deployment_runs', function (Blueprint $table): void {
            $table->dropIndex(['agent_status']);
            $table->dropColumn([
                'agent_status', 'agent_stage', 'agent_started_at',
                'agent_finished_at', 'agent_heartbeat_at',
                'agent_result_key', 'agent_result_at',
            ]);
        });
    }
};
