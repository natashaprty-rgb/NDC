<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentReminder extends Model
{
    protected $fillable = ['reminder_type', 'remindable_type', 'remindable_id', 'user_id', 'sent_on'];

    protected function casts(): array
    {
        return ['sent_on' => 'date'];
    }
}
