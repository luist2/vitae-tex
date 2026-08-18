<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'role',
        'description',
        'url',
        'start_date',
        'end_date',
        'is_current',
        'highlights',
        'technologies',
        'position',
    ];

    /**
     * @return BelongsTo<Cv, $this>
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'highlights' => 'array',
            'technologies' => 'array',
            'position' => 'integer',
        ];
    }
}
