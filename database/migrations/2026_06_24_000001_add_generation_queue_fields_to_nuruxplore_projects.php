<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('nuruxplore_projects', 'original_prompt')) {
                $table->text('original_prompt')->nullable()->after('description');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'title_ai_generated')) {
                $table->boolean('title_ai_generated')->default(false)->after('title');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'research_profile')) {
                $table->json('research_profile')->nullable()->after('research_question');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'research_profile_status')) {
                $table->string('research_profile_status')->default('missing')->after('research_profile');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'research_profile_approved_at')) {
                $table->timestamp('research_profile_approved_at')->nullable()->after('research_profile_status');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_settings')) {
                $table->json('generation_settings')->nullable()->after('research_profile_approved_at');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'project_memory')) {
                $table->json('project_memory')->nullable()->after('generation_settings');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_status')) {
                $table->string('generation_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_progress')) {
                $table->unsignedTinyInteger('generation_progress')->default(0)->after('generation_status');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_current_step')) {
                $table->string('generation_current_step')->nullable()->after('generation_progress');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_steps')) {
                $table->json('generation_steps')->nullable()->after('generation_current_step');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_error')) {
                $table->longText('generation_error')->nullable()->after('generation_steps');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_job_uuid')) {
                $table->uuid('generation_job_uuid')->nullable()->after('generation_error');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_started_at')) {
                $table->timestamp('generation_started_at')->nullable()->after('generation_job_uuid');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'generation_finished_at')) {
                $table->timestamp('generation_finished_at')->nullable()->after('generation_started_at');
            }
            if (!Schema::hasColumn('nuruxplore_projects', 'credits_reserved')) {
                $table->integer('credits_reserved')->default(0)->after('generation_finished_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
            foreach ([
                'original_prompt', 'title_ai_generated', 'research_profile', 'research_profile_status', 'research_profile_approved_at',
                'generation_settings', 'project_memory', 'generation_status', 'generation_progress', 'generation_current_step',
                'generation_steps', 'generation_error', 'generation_job_uuid', 'generation_started_at', 'generation_finished_at', 'credits_reserved',
            ] as $column) {
                if (Schema::hasColumn('nuruxplore_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
