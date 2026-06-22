<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
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
                $table->json('generation_settings')->nullable()->after('structure');
            }
        });

        if (Schema::hasColumn('nuruxplore_sources', 'extracted_text')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE nuruxplore_sources MODIFY extracted_text LONGTEXT NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE nuruxplore_sources ALTER COLUMN extracted_text TYPE TEXT');
            }
        }
    }

    public function down(): void
    {
        Schema::table('nuruxplore_projects', function (Blueprint $table) {
            foreach (['generation_settings', 'research_profile_approved_at', 'research_profile_status', 'research_profile'] as $column) {
                if (Schema::hasColumn('nuruxplore_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
