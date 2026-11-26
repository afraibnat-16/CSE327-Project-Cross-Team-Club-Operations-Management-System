<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One university office's approval for a sponsor.
// office: student_affairs, ops_management or security_gate_pass
// status: pending, submitted or approved
class Approval extends Model
{
    protected $fillable = [
        'sponsor_id',
        'office',
        'status',
        'submitted_on',
    ];

    protected function casts(): array
    {
        return [
            'submitted_on' => 'date',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }
}
