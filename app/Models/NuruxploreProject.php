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
        'user_id',
        'uuid',
        'title',
        'type',
        'citation_style',
        'description',
        'research_question',
        'structure',
        'word_count',
        'status',
        'last_edited_at',
    ];

    protected $casts = [
        'structure' => 'array',
        'last_edited_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($project) {
            $project->uuid = (string) Str::uuid();
        });
    }

    // Route binding by UUID
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
        $this->word_count = $this->sections()->sum('word_count');
        $this->save();
    }
}