<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'position',
    ];

    /**
     * @return BelongsTo<SkillGroup, $this>
     */
    public function skillGroup(): BelongsTo
    {
        return $this->belongsTo(SkillGroup::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
