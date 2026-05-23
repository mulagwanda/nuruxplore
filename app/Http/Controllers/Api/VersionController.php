<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    public function index($projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $versions = $project->versions()
            ->orderBy('version_number', 'desc')
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'changes_description' => $v->changes_description,
                'change_type' => $v->change_type,
                'snapshot' => $v->snapshot,
                'created_at' => $v->created_at->diffForHumans(),
            ]);

        return response()->json(['versions' => $versions]);
    }

    public function restore($projectUuid, $versionId): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $version = NuruxploreVersion::findOrFail($versionId);
        
        if ($version->snapshot && isset($version->snapshot['content'])) {
            $project->update([
                'content' => $version->snapshot['content'],
                'word_count' => str_word_count(strip_tags($version->snapshot['content'])),
                'last_edited_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Version v' . $version->version_number . ' restored',
            'content' => $project->fresh()->content,
        ]);
    }
}