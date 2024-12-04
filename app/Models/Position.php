<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = [
        'name',
        'display_name',
    ];

       public function employees()
   {
       return $this->belongsToMany(Employee::class, 'employee_position', 'position_id', 'employee_id');
   }
} 