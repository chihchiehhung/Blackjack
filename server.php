<?php

function formatLog($note, $message) {
    echo '['.date('Y-m-d H:i:s', time())."][$note] ".$message.PHP_EOL;
}
include_once "BlackjackLobby.php";
include_once "Basic.php";
$server = new Swoole\Server('127.0.0.1', 9501);
$server->BJLobby = new BlackjackLobby($server);
echo gettype($server->BJLobby).PHP_EOL;
$server->set(
    [
        'worker_num' => 1,
        'open_eof_check' => true,
        'package_eof' => "\n",  //  關鍵字:  tcp sticky package / boundary problem
    ]
);
$server->clientConnect = [];
$server->clientUid = [];
$server->on('Start', function ($server) {
    echo 'master start'.PHP_EOL;

});

// 玩家連上server 時的 callback, 可在此建立 fd 與 userObject 的對應, 通知client已連上
$server->on('Connect', function ($server, $fd) {
    formatLog("system", "fd = $fd connected");
    $userObj = new stdclass();
    $server->clientConnect[$fd] = $userObj;

    $output = "connected";
    formatLog("output", $output);
    $server->send($fd, $output."\n");
});

// 玩家送socket 時的 callback, 這邊會收到socket cmd 透過fd 從mapping 表取得 對應的 userObject
$server->on('Receive', function ($server, $fd, $reactor_id, $data) {
    $userObj = $server->clientConnect[$fd];
    $BJLobby = $server->BJLobby;
    $inputArr = explode("\n", $data); //因為可能一次接到很多條socket, 分隔符切割  ( 關鍵字:  tcp sticky package / boundary problem )
    $server->send($fd, "Server got message.\n");
    foreach($inputArr as $input){
        if (!$input) {
            continue;
        }
        formatLog("input", $input);
        [$cmd, $data] = explode(' ', $input, 2); // 格式為空格分隔, 前面為cmd 後面是data
        $cmd = trim($cmd);

        switch($cmd){
            case 'helo':
                $output = "helo []";
                formatLog("output", $output);
                $server->send($fd, $output."\n");
                break;
            case 'session_status':
                $d = json_decode($data, true);
                $userObj->client_wid = $d['client_wid']; // 可將一些有狀態的東西掛在 userObject身上, ex登入過的帳號密碼或名字

                $output = "session_status [123]";
                formatLog("output", $output);
                $server->send($fd, $output."\n");
                break;
            case 'ready':
                $server->after(2000, function () use ($server, $fd){
                    $output = "Server is ready";
                    formatLog("output", $output);
                    $server->send($fd, $output."\n");
                    $server->send($fd, "ready \n");
                });
                break;
            case 'signIn':
                if($userObj->login){
                    $output = "signIn 0";
                    formatLog("output", $output);
                    $server->send($fd, $output."\n");
                    $server->send($fd, "You have log in. "."\n");
                }
                [$username, $pwd] = explode(" ", $data);
                $money = signIn($username, $pwd, $fd, $server);
                $userObj->money = $money;
                if($money){
                    $userObj->login = true;
                    $output = "signIn 1";
                    formatLog("output", $output);
                    $server->send($fd, $output."\n");
                }
                else{
                    $output = "signIn 0";
                    formatLog("output", $output);
                    $server->send($fd, $output."\n");
                }

                break;
            case 'compareCard':
                //比牌型
                $server->after(2000, function () use ($server, $fd) {
                    startGame($server, $fd);
                });
                break;
            case 'Blackjack':
                //21點
                $BJLobby->newPlayers($fd);
                break;
            case 'Hit':
                $userObj->table->hit();
                break;
            case 'Stand':
                $userObj->table->stand();
                break;
            case 'Restart':

        }
    }

});

$server->on('Close', function ($server, $fd) {
    unset($server->clientConnect[$fd]);
    formatLog("output", "Client: Close.");
});

$server->start();

function startGame($server, $fd){
    $server->send($fd, "Start Game!\n");
    $cards = give_card();
    $server->send($fd, "Your cards: $cards[0]\nServer's cards: $cards[1]\n");
    $userCards = new Basic($cards[0]);
    $serverCards = new Basic($cards[1]);
    $server->after(2000, function () use ($server, $fd, $userCards, $serverCards) {
        if($userCards->cmpTwoCard($serverCards) == 1){
            $server->send($fd, "You win\n");
        }
        elseif($userCards->cmpTwoCard($serverCards) == -1){
            $server->send($fd, "You lose\n");
        }
        else{
            $server->send($fd, "You tie\n");
        }
        $server->send($fd, "Try again?\n");
    });
    unset($userCards);
    unset($serverCards);
}

function signIn($username, $pwd, $fd, $server){
    initDBHmulti('slot');
    $pwd = crypt($pwd, $username);
    $res = slselectall("select * from FH_HW_simonhung_user where username = '$username' AND pwd = '$pwd'");
    if(count($res) == 1 && intval($res[0]['money'])>0){
        $server->clientConnect[$fd]->uid = $res[0]['uid'];
        $uid =$res[0]['uid'];
        if(isset($server->clientUid[$uid])){
            return 0;
        }
        $server->clientUid[$uid] = true;
        echo "uid: $uid" . PHP_EOL;
        return intval($res[0]['money']);
    }
    return 0;

}

function signUp($username, $pwd){

}