<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 40)->default('github_actions');
            $table->string('ref', 150)->default('main');
            $table->string('status', 40)->default('requested')->index();
            $table->string('conclusion', 40)->nullable();
            $table->unsignedBigInteger('workflow_run_id')->nullable()->unique();
            $table->text('workflow_url')->nullable();
            $table->string('commit_sha', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_runs');
    }
};
