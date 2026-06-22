<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NuruxploreSource extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'author',
        'year',
        'type',
        'metadata',
        'file_path',
        'extracted_text',
        'doi',
        'url',
        'verification_status',
        'citation_count',
    ];

    protected $casts = [
        'metadata' => 'array',
        'citation_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(NuruxploreProject::class, 'project_id');
    }

    public function role(): string
    {
        return $this->metadata['document_role'] ?? $this->type ?? 'other';
    }

    public function markExtraction(string $status, ?string $message = null, array $extra = []): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['extraction_status'] = $status;
        if ($message !== null) {
            $metadata['extraction_message'] = $message;
        }
        $metadata = array_merge($metadata, $extra);
        $this->forceFill(['metadata' => $metadata])->save();
    }
}
