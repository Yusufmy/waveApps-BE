<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = [
        'type',
        'created_by',
        'last_message',
        'last_message_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime'
    ];

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function participants()
    {
        return $this->belongsToMany(
            User::class,
            'conversation_participants'
        );
    }

    public function messages()
    {
        return $this->hasMany(
            Message::class
        );
    }
}
