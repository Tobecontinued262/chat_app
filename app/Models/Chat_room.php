<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat_room extends Model
{
    use HasFactory;
    protected $fillable = ['room_name', 'status', 'created_at', 'updated_at'];
    public function chat_members()
    {
        return $this->hasMany(Chat_member::class, 'chat_room_id');
    }
}
