<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProgramSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_program_id',
        'title',
        'description',
        'sort_order',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(EmployeeProgram::class, 'employee_program_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeProgramItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
