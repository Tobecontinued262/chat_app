<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat_message extends Model
{
    use HasFactory;
    protected $fillable = ['chat_room_id', 'member_id', 'member_type', 'chat_message', 'messages_status', 'created_at', 'updated_at'];
//    public function admins()
//    {
//        return $this->hasMany(System_account::class, 'member_id');
//    }
//
//    public function user_attributes()
//    {
//        return $this->hasOneThrough(Member_account::class,Member_account_attribute::class,'id', 'member_account_id');
//    }
}
