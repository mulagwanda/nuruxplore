<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    protected ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    public function pdf($projectId): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($projectId);
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $filename = $this->exportService->exportToPdf($project);
        $url = Storage::disk('public')->url($filename);

        return response()->json([
            'download_url' => $url,
            'filename' => basename($filename),
            'message' => 'PDF exported successfully',
        ]);
    }

    public function word($projectId): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($projectId);
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = request()->user();
        if ($user->credits_balance < 1) {
            return response()->json([
                'message' => 'Insufficient credits for Word export',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        $filename = $this->exportService->exportToWord($project);
        $url = Storage::disk('public')->url($filename);

        $user->deductCredits(1, 'Word export', $project->id);

        return response()->json([
            'download_url' => $url,
            'filename' => basename($filename),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => 'Word document exported successfully',
        ]);
    }
}