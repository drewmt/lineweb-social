<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedCollection extends Model
{
    protected $fillable = ['name', 'user_id'];
}
