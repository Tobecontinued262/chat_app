<?php

namespace App\Http\Controllers;

use App\Models\Chat_member;
use App\Models\Chat_message;
use App\Models\Chat_room;
use App\Models\Member_account;
use App\Models\System_account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class SocketController extends Controller implements MessageComponentInterface
{
    protected $clients;

    protected $allow_ips;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->allow_ips = config('app.allow_ips');
    }

    public function onOpen(ConnectionInterface $conn)
    {
        if (!$this->isValidIp($conn->remoteAddress)) {
            return false;
        }
        $this->clients->attach($conn);

        $querystring = $conn->httpRequest->getUri()->getQuery();

        parse_str($querystring, $queryarray);

        if (isset($queryarray['id']) && isset($queryarray['member_type'])) {
            if ($queryarray['member_type'] == '1') {
                System_account::where('cognito_map_id', $queryarray['id'])->update(['connection_id' => $conn->resourceId, 'user_status' => 'Online']);
                $data['member_type'] = 1;
            }

            if ($queryarray['member_type'] == '0') {
                Member_account::where('cognito_map_id', $queryarray['id'])->update(['connection_id' => $conn->resourceId, 'user_status' => 'Online']);
                $data['member_type'] = 0;
            }

            $data['id'] = $queryarray['id'];

            $data['status'] = 'Online';

            foreach ($this->clients as $client) {
                if ($client->resourceId != $conn->resourceId) {
                    $client->send(json_encode($data));
                }
            }

        }
    }

    public function onMessage(ConnectionInterface $conn, $msg)
    {
        if (preg_match('~[^\x20-\x7E\t\r\n]~', $msg) > 0) {
            //receiver image in binary string message

            $image_name = time() . '.jpg';

            file_put_contents(public_path('images/') . $image_name, $msg);

            $send_data['image_link'] = $image_name;

            foreach ($this->clients as $client) {
                if ($client->resourceId == $conn->resourceId) {
                    $client->send(json_encode($send_data));
                }
            }
        }


        $data = json_decode($msg);
        if (isset($data->type)) {
            switch ($data->type) {
                case 'request_connected_chat_user':
                    $this->responseChatRooms($data, $conn);
                    break;
                case 'request_send_message':
                    $this->sendMessage($data);
                    break;
                case 'request_chat_history':
                    $this->getChatRoomHistory($data);
                    break;
                case 'request_make_chat_room':
                    $this->createChatRoom($data, $conn);
                    break;
                case 'request_attachment_file':
                    $this->getAttachmentFiles($data);
                    break;
                case 'update_chat_status':
                    $this->updateChatStatus($data,$conn);
                    break;
                case 'check_unread_message':
                    $this->checkUnReadMessages($data);
                    break;
            }
//            if($data->type == 'request_search_user')
//            {
//                $user_data = User::select('id', 'name', 'user_status', 'user_image')
//                    ->where('id', '!=', $data->member_id)
//                    ->where('name', 'like', '%'.$data->search_query.'%')
//                    ->orderBy('name', 'ASC')
//                    ->get();
//
//                $sub_data = array();
//
//                foreach($user_data as $row)
//                {
//
//                    $chat_request = Chat_request::select('id')
//                        ->where(function($query) use ($data, $row){
//                            $query->where('member_id', $data->member_id)->where('chat_room_id', $row->id);
//                        })
//                        ->orWhere(function($query) use ($data, $row){
//                            $query->where('member_id', $row->id)->where('chat_room_id', $data->member_id);
//                        })->get();
//
//                    /*
//                    SELECT id FROM chat_request
//                    WHERE (member_id = $data->member_id AND chat_room_id = $row->id)
//                    OR (member_id = $row->id AND chat_room_id = $data->member_id)
//                    */
//
//                    if($chat_request->count() == 0)
//                    {
//                        $sub_data[] = array(
//                            'name'  =>  $row['name'],
//                            'id'    =>  $row['id'],
//                            'status'=>  $row['user_status'],
//                            'user_image' => $row['user_image']
//                        );
//                    }
//
//
//                }
//
//                $sender_connection_id = User::select('connection_id')->where('id', $data->member_id)->get();
//
//                $send_data['data'] = $sub_data;
//
//                $send_data['response_search_user'] = true;
//
//                foreach($this->clients as $client)
//                {
//                    if($client->resourceId == $sender_connection_id[0]->connection_id)
//                    {
//                        $client->send(json_encode($send_data));
//                    }
//                }
//            }
//
//            if($data->type == 'request_load_unread_notification')
//            {
//                $notification_data = Chat_request::select('id', 'member_id', 'chat_room_id', 'status')
//                    ->where('status', '!=', 'Approve')
//                    ->where(function($query) use ($data){
//                        $query->where('member_id', $data->user_id)->orWhere('chat_room_id', $data->user_id);
//                    })->orderBy('id', 'ASC')->get();
//
//                /*
//                SELECT id, member_id, chat_room_id, status FROM chat_requests
//                WHERE status != 'Approve'
//                AND (member_id = $data->user_id OR chat_room_id = $data->user_id)
//                ORDER BY id ASC
//                */
//
//                $sub_data = array();
//
//                foreach($notification_data as $row)
//                {
//                    $user_id = '';
//
//                    $notification_type = '';
//
//                    if($row->member_id == $data->user_id)
//                    {
//                        $user_id = $row->chat_room_id;
//
//                        $notification_type = 'Send Request';
//                    }
//                    else
//                    {
//                        $user_id = $row->member_id;
//
//                        $notification_type = 'Receive Request';
//                    }
//
//                    $user_data = User::select('name', 'user_image')->where('id', $user_id)->first();
//
//                    $sub_data[] = array(
//                        'id'            =>  $row->id,
//                        'member_id'  =>  $row->member_id,
//                        'chat_room_id'    =>  $row->chat_room_id,
//                        'name'          =>  $user_data->name,
//                        'notification_type' =>  $notification_type,
//                        'status'           =>   $row->status,
//                        'user_image'    =>  $user_data->user_image
//                    );
//                }
//
//                $sender_connection_id = User::select('connection_id')->where('id', $data->user_id)->get();
//
//                foreach($this->clients as $client)
//                {
//                    if($client->resourceId == $sender_connection_id[0]->connection_id)
//                    {
//                        $send_data['response_load_notification'] = true;
//
//                        $send_data['data'] = $sub_data;
//
//                        $client->send(json_encode($send_data));
//                    }
//                }
//            }
//
//            if($data->type == 'request_send_message')
//            {
//                //save chat message in mysql
//
//                $chat = new Chat;
//
//                $chat->member_id = $data->member_id;
//
//                $chat->chat_room_id = $data->chat_room_id;
//
//                $chat->chat_message = $data->message;
//
//                $chat->message_status = 'NotSend';
//
//                $chat->save();
//
//                $chat_message_id = $chat->id;
//
//                $receiver_connection_id = User::select('connection_id')->where('id', $data->chat_room_id)->get();
//
//                $sender_connection_id = User::select('connection_id')->where('id', $data->member_id)->get();
//
//                foreach($this->clients as $client)
//                {
//                    if($client->resourceId == $receiver_connection_id[0]->connection_id || $client->resourceId == $sender_connection_id[0]->connection_id)
//                    {
//                        $send_data['chat_message_id'] = $chat_message_id;
//
//                        $send_data['message'] = $data->message;
//
//                        $send_data['member_id'] = $data->member_id;
//
//                        $send_data['chat_room_id'] = $data->chat_room_id;
//
//                        if($client->resourceId == $receiver_connection_id[0]->connection_id)
//                        {
//                            Chat::where('id', $chat_message_id)->update(['message_status' =>'Send']);
//
//                            $send_data['message_status'] = 'Send';
//                        }
//                        else
//                        {
//                            $send_data['message_status'] = 'NotSend';
//                        }
//
//                        $client->send(json_encode($send_data));
//                    }
//                }
//            }

//            if($data->type == 'request_chat_history')
//            {
//                $chat_data = Chat::select('id', 'member_id', 'chat_room_id', 'chat_message', 'message_status')
//                    ->where(function($query) use ($data){
//                        $query->where('member_id', $data->member_id)->where('chat_room_id', $data->chat_room_id);
//                    })
//                    ->orWhere(function($query) use ($data){
//                        $query->where('member_id', $data->chat_room_id)->where('chat_room_id', $data->member_id);
//                    })->orderBy('id', 'ASC')->get();
//
//
//                $receiver_connection_id = User::select('connection_id')->where('id', $data->member_id)->get();


//            }

//            if($data->type == 'update_chat_status')
//            {
//                //update chat status
//
//                Chat::where('id', $data->chat_message_id)->update(['message_status' => $data->chat_message_status]);
//
//                $sender_connection_id = User::select('connection_id')->where('id', $data->member_id)->get();
//
//                foreach($this->clients as $client)
//                {
//                    if($client->resourceId == $sender_connection_id[0]->connection_id)
//                    {
//                        $send_data['update_message_status'] = $data->chat_message_status;
//
//                        $send_data['chat_message_id'] = $data->chat_message_id;
//
//                        $client->send(json_encode($send_data));
//                    }
//                }
//            }
//
//            if($data->type == 'check_unread_message')
//            {
//                $chat_data = Chat::select('id', 'member_id', 'chat_room_id')->where('message_status', '!=', 'Read')->where('member_id', $data->chat_room_id)->get();
//
//                /*
//                SELECT id, member_id, chat_room_id FROM chats
//                WHERE message_status != 'Read'
//                AND member_id = $data->chat_room_id
//                */
//
//                $sender_connection_id = User::select('connection_id')->where('id', $data->member_id)->get(); //send number of unread message
//
//                $receiver_connection_id = User::select('connection_id')->where('id', $data->chat_room_id)->get(); //send message read status
//
//                foreach($chat_data as $row)
//                {
//                    Chat::where('id', $row->id)->update(['message_status' => 'Send']);
//
//                    foreach($this->clients as $client)
//                    {
//                        if($client->resourceId == $sender_connection_id[0]->connection_id)
//                        {
//                            $send_data['count_unread_message'] = 1;
//
//                            $send_data['chat_message_id'] = $row->id;
//
//                            $send_data['member_id'] = $row->member_id;
//                        }
//
//                        if($client->resourceId == $receiver_connection_id[0]->connection_id)
//                        {
//                            $send_data['update_message_status'] = 'Send';
//
//                            $send_data['chat_message_id'] = $row->id;
//
//                            $send_data['unread_msg'] = 1;
//
//                            $send_data['member_id'] = $row->member_id;
//                        }
//
//                        $client->send(json_encode($send_data));
//                    }
//                }
//            }
        }
    }

    private function createChatRoom($data, $conn)
    {
        $check_member = Chat_member::query()->where('member_id', $data->user_id)->where('member_type', '=', 0)->first();
        if (empty($check_member)) {
            $chat_room = new Chat_room;
            $chat_room->room_name = 'support';
            $chat_room->status = 'Available';
            $chat_room->created_at = now();
            $chat_room->updated_at = now();
            $chat_room->save();

//            $connections = array();
//            $user = Member_account::find($data->user_id);
//            $connections[] = $user->connection_id;

            $chat_member = new Chat_member;
            $chat_member->chat_room_id = $chat_room->id;
            $chat_member->member_id = $data->user_id;
            $chat_member->member_type = 0;
            $chat_member->save();

            $admins = System_account::all();
            foreach ($admins as $admin) {
                $chat_member = new Chat_member;
                $chat_member->chat_room_id = $chat_room->id;
                $chat_member->member_id = $admin->user_id;
                $chat_member->member_type = 1;
                $chat_member->save();

//                $connections[] = $admin->connection_id;
            }
            $chat_room_id = $chat_room->id;
        } else {
            $chat_room_id = $check_member->chat_room_id;
        }
        $this->getChatRooms($data, $sub_data);
//        Log::info(json_encode($connections));
        foreach ($this->clients as $client) {
            if ($client->resourceId == $conn->resourceId) {
                $send_data['response_create_chat_room'] = true;
                $send_data['data'] = $sub_data;
                $send_data['chat_room_id'] = $chat_room_id;
                $client->send(json_encode($send_data));
            }
        }
    }

    private function getChatRooms($data, &$sub_data)
    {
        if ($data->member_type == 0) {
            $user_id_data = Chat_room::query()->select('id', 'room_name', 'status')
                ->whereHas('chat_members', function ($query) use ($data) {
                    $query->where('member_id', $data->member_id);
                    $query->where('member_type', $data->member_type);
                })
                ->first();

            $sub_data[] = array(
                'chat_room_id' => !empty($user_id_data) ? $user_id_data->id : null,
                'user_id' => $data->member_id,
                'room_name' => !empty($user_id_data) ? $user_id_data->room_name : 'support',
                'user_image' => null,
                'user_status' => null,
                'last_seen' => null
            );
        }

        if ($data->member_type == 1) {
            $users = Member_account::query()
                ->leftJoin('member_account_attributes', 'member_accounts.id', '=', 'member_account_attributes.member_account_id')
                ->leftJoin('chat_members', function ($query) {
                    $query->on('member_accounts.id', '=', 'chat_members.member_id');
                    $query->where('chat_members.member_type', '=', 0);
                });
//                ->leftJoin('chat_rooms', 'chat_members.chat_room_id', '=', 'chat_rooms.id')
            if (isset($data->search_query)){
                if (intval($data->search_query) == 0) {
                    $users = $users->where('member_account_attributes.member_name', 'like', '%'.$data->search_query.'%');
                } else {
                    $users = $users->where('member_account_attributes.member_name', 'like', '%'.$data->search_query.'%');
                    $users = $users->orWhere('member_accounts.id', '=', intval($data->search_query));
                }
            }
            $users = $users->select('member_accounts.id as member_id', 'member_accounts.user_status',
                    'member_account_attributes.member_name', 'chat_members.chat_room_id')
                ->get();

            foreach ($users as $user) {
                $sub_data[] = array(
                    'chat_room_id' => $user->chat_room_id,
                    'user_id' => $user->member_id,
                    'room_name' => $user->member_name,
                    'user_image' => null,
                    'user_status' => $user->user_status,
                    'last_seen' => null
                );
            }
        }
    }

    private function responseChatRooms($data, $conn)
    {
        $sub_data = array();

        $this->getChatRooms($data, $sub_data);

        foreach ($this->clients as $client) {
            if ($client->resourceId == $conn->resourceId) {
                $send_data['response_connected_chat_user'] = true;
                $send_data['data'] = $sub_data;
                $client->send(json_encode($send_data));
            }
        }
    }

    private function sendMessage($data)
    {
        //save chat message in mysql

        $chat_message = new Chat_message;
        $chat_message->chat_room_id = $data->chat_room_id;
        $chat_message->member_id = $data->member_id;
        $chat_message->member_type = $data->member_type;
        $chat_message->chat_message = $data->chat_message;
        $chat_message->message_status = 'NotSend';
        $chat_message->created_at = now();
        $chat_message->updated_at = now();
        $chat_message->save();
        $chat_message_id = $chat_message->id;


        $receiver_connection_id = [];
        $sender_connection_id = null;
        $this->getChatMembers($data, $receiver_connection_id, $sender_connection_id);
        foreach ($this->clients as $client) {
            if (in_array($client->resourceId, $receiver_connection_id)) {
                $send_data['chat_message_id'] = $chat_message_id;

                $send_data['chat_message'] = $data->chat_message;

                $send_data['member_name'] = $sender_connection_id->member_name;

                $send_data['member_id'] = $data->member_id;

                $send_data['chat_room_id'] = $data->chat_room_id;

                if (in_array($client->resourceId, $receiver_connection_id)) {
                    Chat_message::where('id', $chat_message_id)->update(['message_status' => 'Send']);

                    $send_data['message_status'] = 'Send';
                } else {
                    $send_data['message_status'] = 'NotSend';
                }

                $client->send(json_encode($send_data));
            }
        }
    }

    protected function getChatMembers($data, &$receiver_connection_id, &$sender_connection_id)
    {
        $members = Chat_member::query()->select('member_id', 'member_type')->where('chat_room_id', $data->chat_room_id)->get();
        $admins = array();
        $user = 0;
        foreach ($members as $member) {
            if ($member->member_type == 1) {
                $admins[] = $member->member_id;
            }
            if ($member->member_type == 0) {
                $user = $member->member_id;
            }
        }
        if ($data->member_type == 0) {
            $sender_connection_id = Member_account::select('member_accounts.connection_id', 'member_account_attributes.member_name as member_name')
                ->leftJoin('member_account_attributes', 'member_accounts.id', '=', 'member_account_attributes.member_account_id')
                ->where('id', $data->member_id)->first();
        }
        if ($data->member_type == 1) {
            $sender_connection_id = System_account::select('connection_id', 'name as member_name')->where('user_id', $data->member_id)->first();
        }

        $admin_connections = System_account::select('connection_id')->whereIn('user_id', $admins)->get();
        $user_connection_id = Member_account::select('connection_id')->where('id', $user)->first();

        foreach ($admin_connections as $admin_connection) {
            $receiver_connection_id[] = $admin_connection->connection_id;
        }
        $receiver_connection_id[] = $user_connection_id->connection_id;
    }

    private function getChatRoomHistory($data)
    {
        $chat_data = Chat_message::query()
            ->leftJoin('system_accounts', function ($join) {
                $join->on('chat_messages.member_id', '=', 'system_accounts.user_id');
            })
            ->leftJoin('member_accounts', function ($join) {
                $join->on('chat_messages.member_id', '=', 'member_accounts.id');
            })
            ->leftJoin('member_account_attributes', 'member_account_attributes.member_account_id', '=', 'member_accounts.id')
            ->where('chat_messages.chat_room_id', $data->chat_room_id)
            ->select('chat_messages.id as chat_message_id', 'chat_messages.chat_room_id',
                'chat_messages.member_id', 'chat_messages.member_type',
                'chat_messages.chat_message', 'chat_messages.message_status',
                DB::raw('CASE
                    WHEN chat_messages.member_type = 0 THEN member_account_attributes.member_name
                    WHEN chat_messages.member_type = 1 THEN system_accounts.name
                    ELSE null
                END as member_name'))
//            ->orderByDesc('chat_messages.created_at')
            ->get();

        $send_data['chat_history'] = $chat_data;
        $receiver_connection_id = null;
        if ($data->member_type == '0') {
            $receiver_connection_id = Member_account::select('connection_id')->where('id', $data->member_id)->first();
        }

        if ($data->member_type == '1') {
            $receiver_connection_id = System_account::select('connection_id')->where('user_id', $data->member_id)->first();
        }

        foreach ($this->clients as $client) {
            if ($client->resourceId == $receiver_connection_id->connection_id) {
                $client->send(json_encode($send_data));
            }
        }
    }

    private function getAttachmentFiles($data)
    {

    }

    private function updateChatStatus($data,$conn)
    {
        Log::info(json_encode($data));
        Chat_message::where('id', $data->chat_message_id)->update(['message_status' => $data->chat_message_status]);

        $this->getChatMembers($data, $receiver_connection_id, $sender_connection_id);

        foreach ($this->clients as $client) {
            if (in_array($client->resourceId, $receiver_connection_id) || $client->resourceId == $sender_connection_id->connection_id) {
                $send_data['update_message_status'] = $data->chat_message_status;

                $send_data['chat_message_id'] = $data->chat_message_id;

                $client->send(json_encode($send_data));
            }
        }

//        Chat_message::where('id', $data->chat_message_id)->update(['message_status' => $data->chat_message_status]);
//
////        $sender_connection_id = User::select('connection_id')->where('id', $data->member_id)->get();
//
//        foreach ($this->clients as $client) {
//            if ($client->resourceId == $conn->resourceId) {
//                $send_data['update_message_status'] = $data->chat_message_status;
//
//                $send_data['chat_message_id'] = $data->chat_message_id;
//
//                $client->send(json_encode($send_data));
//            }
//        }
    }

    private function checkUnReadMessages($data)
    {

    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        $querystring = $conn->httpRequest->getUri()->getQuery();

        parse_str($querystring, $queryarray);
        if (isset($queryarray['id']) && isset($queryarray['member_type'])) {
            if ($queryarray['member_type'] == '1') {
                System_account::where('cognito_map_id', $queryarray['id'])->update(['connection_id' => $conn->resourceId, 'user_status' => 'Offline']);
                $data['member_type'] = 1;
            }

            if ($queryarray['member_type'] == '0') {
                Member_account::where('cognito_map_id', $queryarray['id'])->update(['connection_id' => $conn->resourceId, 'user_status' => 'Offline']);
                $data['member_type'] = 0;
            }

            $data['id'] = $queryarray['id'];

            $data['status'] = 'Offline';

            foreach ($this->clients as $client) {
                if ($client->resourceId != $conn->resourceId) {
                    $client->send(json_encode($data));
                }
            }

        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()} \n";
        echo "An error has occurred: {$e->getLine()} \n";

        $conn->close();
    }

    protected function isValidIp($ip): bool
    {
        if (empty($this->allow_ips)) {
            return true;
        }
        $ips = explode(',', $this->allow_ips);
        return in_array($ip, $ips);
    }
}
