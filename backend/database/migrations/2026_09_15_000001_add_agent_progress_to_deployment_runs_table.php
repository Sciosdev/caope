<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deployment_runs', function (Blueprint $table): void {
            $table->string('agent_status', 40)->nullable()->index();
            $table->string('agent_stage', 40)->nullable();
            $table->timestamp('agent_started_at')->nullable();
            $table->timestamp('agent_finished_at')->nullable();
            $table->timestamp('agent_heartbeat_at')->nullable();
            $table->string('agent_result_key', 64)->nullable();
            $table->timestamp('agent_result_at')->nullable();
        });
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
