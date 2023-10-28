<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>NodeJS WebSocket Server</title>
</head>
<body>
<h1>Hello world</h1>
<script>
    const ws = new WebSocket("wss://fx9ehrous2.execute-api.ap-northeast-1.amazonaws.com/production/");
    ws.addEventListener("open", () =>{
        console.log("We are connected");

        ws.send(JSON.stringify({
            action: 'setAttributes',
            memberId : '8b58f037-b9eb-4826-ac8c-20e17b369829',
            memberType: 0,
        }));

        ws.send(JSON.stringify({
            action: 'sendMessage',
            memberId : 1,
            memberType : 0,
            memberName : 'test',
            chatRoomId : 1,
            chatMessageId : 1,
            chatMessage : 'ngu',
            fileUrl : '',
            sendAt : 'date'
        }));

        ws.send(JSON.stringify({
            action: 'updateMessageStatus',
            chatRoomId : 1,
            chatMessageId : 1,
            messageStatus : 'Send',
            to,
        }));
    });

    ws.addEventListener('message', function (event) {
        console.log(event.data);
    });
</script>
</body>
</html>
