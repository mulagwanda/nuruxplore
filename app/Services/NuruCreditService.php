<?php

namespace App\Services;

use App\Models\NuruxploreProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NuruCreditService
{
    public const CREDIT_USD_VALUE = 0.05;

    public function cost(string $action, ?string $type = null, ?NuruxploreProject $project = null): int
    {
        $type = strtolower((string) ($type ?: $project?->type ?: 'thesis'));

        return match ($action) {
            'general_chat' => 1,
            'workspace_chat' => 2,
            'generate_title', 'edit_title' => 2,
            'suggest_titles' => 3,
            'grammar_review' => 3,
            'rewrite_section' => 6,
            'expand_section', 'humanize_section', 'professionalize_section' => 8,
            'insert_table', 'insert_chart_table' => 5,
            'fix_references' => 10,
            'expand_references' => 15,
            'document_review', 'plagiarism_risk_review', 'consistency_check' => 20,
            'export' => 5,
            'generate_document' => match ($type) {
                'proposal' => 100,
                'thesis' => $this->projectHasSources($project) ? 600 : 400,
                'dissertation' => $this->projectHasSources($project) ? 700 : 500,
                default => 250,
            },
            default => 2,
        };
    }

    public function usdValue(int $credits): float
    {
        return round($credits * self::CREDIT_USD_VALUE, 2);
    }

    public function canAfford(User $user, int $credits): bool
    {
        return (int) ($user->credits_balance ?? 0) >= $credits;
    }

    public function charge(User $user, int $credits, string $reason, ?int $projectId = null): void
    {
        if ($credits <= 0) {
            return;
        }

        if (method_exists($user, 'deductCredits')) {
            $user->deductCredits($credits, $reason, $projectId);
            return;
        }

        DB::transaction(function () use ($user, $credits, $reason, $projectId) {
            $user->forceFill([
                'credits_balance' => max(0, (int) ($user->credits_balance ?? 0) - $credits),
            ])->save();

            if (Schema::hasTable('nuruxplore_credit_transactions')) {
                DB::table('nuruxplore_credit_transactions')->insert([
                    'user_id' => $user->id,
                    'amount' => -abs($credits),
                    'reason' => $reason,
                    'project_id' => $projectId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function refund(User $user, int $credits, string $reason, ?int $projectId = null): void
    {
        if ($credits <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $credits, $reason, $projectId) {
            $user->forceFill([
                'credits_balance' => (int) ($user->credits_balance ?? 0) + $credits,
            ])->save();

            if (Schema::hasTable('nuruxplore_credit_transactions')) {
                DB::table('nuruxplore_credit_transactions')->insert([
                    'user_id' => $user->id,
                    'amount' => abs($credits),
                    'reason' => $reason,
                    'project_id' => $projectId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    protected function projectHasSources(?NuruxploreProject $project): bool
    {
        if (!$project) {
            return false;
        }

        try {
            return $project->sources()->whereNotNull('extracted_text')->where('extracted_text', '!=', '')->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
