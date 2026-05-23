<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\NuruxploreProject;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add the column without unique constraint first
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Step 2: Generate UUIDs for all existing projects
        NuruxploreProject::whereNull('uuid')->orWhere('uuid', '')->each(function ($project) {
            $project->uuid = (string) Str::uuid();
            $project->save();
        });

        // Step 3: Now add the unique constraint
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};