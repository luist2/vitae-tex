<?php

namespace App\Models;

use Database\Factories\CertificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certification extends Model
{
    /** @use HasFactory<CertificationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'issuer',
        'issued_on',
        'expires_on',
        'credential_id',
        'credential_url',
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
            'issued_on' => 'date',
            'expires_on' => 'date',
            'position' => 'integer',
        ];
    }
}
