<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'conversation_id',
        'caller_id',
        'receiver_id',
        'type',
        'status',
        'room_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function caller()
    {
        return $this->belongsTo(
            User::class,
            'caller_id'
        );
    }

    public function receiver()
    {
        return $this->belongsTo(
            User::class,
            'receiver_id'
        );
    }

    public function conversation()
    {
        return $this->belongsTo(
            Conversation::class
        );
    }
}
