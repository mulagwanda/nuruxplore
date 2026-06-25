<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NuruxploreProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'uuid', 'title', 'title_ai_generated', 'type', 'citation_style',
        'description', 'original_prompt', 'research_question', 'research_profile',
        'research_profile_status', 'research_profile_approved_at', 'generation_settings',
        'project_memory', 'structure', 'content', 'word_count', 'status',
        'generation_status', 'generation_progress', 'generation_current_step',
        'generation_steps', 'generation_error', 'generation_job_uuid',
        'generation_started_at', 'generation_finished_at', 'credits_reserved',
        'last_edited_at',
    ];

    protected $casts = [
        'title_ai_generated' => 'boolean',
        'research_profile' => 'array',
        'generation_settings' => 'array',
        'project_memory' => 'array',
        'structure' => 'array',
        'generation_steps' => 'array',
        'generation_started_at' => 'datetime',
        'generation_finished_at' => 'datetime',
        'research_profile_approved_at' => 'datetime',
        'last_edited_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($project) {
            if (blank($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(NuruxploreSection::class, 'project_id')->orderBy('order');
    }

    public function topLevelSections(): HasMany
    {
        return $this->sections()->whereNull('parent_id')->orderBy('order');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NuruxploreSource::class, 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NuruxploreMessage::class, 'project_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NuruxploreVersion::class, 'project_id');
    }

    public function updateWordCount(): void
    {
        $this->word_count = str_word_count(strip_tags((string) $this->content));
        $this->save();
    }
}
