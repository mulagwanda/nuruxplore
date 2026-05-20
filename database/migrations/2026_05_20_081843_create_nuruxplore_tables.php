<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table enhancement
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'credits_balance')) {
                $table->integer('credits_balance')->default(10);
            }
            if (!Schema::hasColumn('users', 'subscription_plan')) {
                $table->string('subscription_plan')->default('free');
            }
            if (!Schema::hasColumn('users', 'subscription_expires_at')) {
                $table->timestamp('subscription_expires_at')->nullable();
            }
        });

        // Projects table
        Schema::create('nuruxplore_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('thesis');
            $table->string('citation_style')->default('APA7');
            $table->text('description')->nullable();
            $table->text('research_question')->nullable();
            $table->json('structure')->nullable();
            $table->integer('word_count')->default(0);
            $table->string('status')->default('draft');
            $table->timestamp('last_edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Sections table
        Schema::create('nuruxplore_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('nuruxplore_projects')->cascadeOnDelete();
            $table->string('title');
            $table->string('section_number')->nullable();
            $table->longText('content')->nullable();
            $table->json('ai_metadata')->nullable();
            $table->string('status')->default('outlined');
            $table->integer('word_count')->default(0);
            $table->integer('order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('nuruxplore_sections')->cascadeOnDelete();
            $table->timestamps();
        });

        // Sources table
        Schema::create('nuruxplore_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('nuruxplore_projects')->nullOnDelete();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('year')->nullable();
            $table->string('type')->default('journal');
            $table->json('metadata')->nullable();
            $table->string('file_path')->nullable();
            $table->text('extracted_text')->nullable();
            $table->string('doi')->nullable();
            $table->string('url')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->integer('citation_count')->default(0);
            $table->timestamps();
        });

        // Versions table
        Schema::create('nuruxplore_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('nuruxplore_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->json('snapshot');
            $table->text('changes_description')->nullable();
            $table->string('change_type')->default('manual');
            $table->json('ai_interaction_log')->nullable();
            $table->timestamps();
            
            $table->unique(['project_id', 'version_number']);
        });

        // Messages (Chat) table
        Schema::create('nuruxplore_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('nuruxplore_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->string('action_type')->nullable();
            $table->integer('credits_used')->default(0);
            $table->timestamps();
        });

        // Credit transactions table
        Schema::create('nuruxplore_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason');
            $table->foreignId('project_id')->nullable()->constrained('nuruxplore_projects')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nuruxplore_credit_transactions');
        Schema::dropIfExists('nuruxplore_messages');
        Schema::dropIfExists('nuruxplore_versions');
        Schema::dropIfExists('nuruxplore_sources');
        Schema::dropIfExists('nuruxplore_sections');
        Schema::dropIfExists('nuruxplore_projects');
    }
};