<?php
include 'Blackjack.php';
/*
Todos:
。
。
*/
class BlackjackLobby
{
    public $users = array();
    public $tables = array();// (fd1, fd2)=>Blackjack
    public $unpairTables = array();
    public $waitingUsers;
    public $playersNum = 3;
    public $server;
    public $tableNum = 0;

    public function __construct($server)
    {
        $this->server = $server;
        $this->waitingUsers = new SplDoublyLinkedList();
    }

    // public function newPlayer($fd){
    //     $this->users[$fd] = True;
    //     if(count($this->waitingUsers)!=0){
    //         $fd1 = $this->waitingUsers->shift();
    //         $fd2 = $fd;
    //         $table = new Blackjack($fd1, $fd2, $this->server);
    //         $this->tables[] = $table;
    //         foreach([$fd1, $fd2] as $f){
    //             $this->server->clientConnect[$f]->game = 'Blackjack';
    //             $this->server->clientConnect[$f]->table = $table;
    //         }
    //         $this->server->clientConnect[$fd1]->opponent = $fd2;
    //         $this->server->clientConnect[$fd2]->opponent = $fd1;
    //     }
    //     else{
    //         $this->waitingUsers->push($fd);
    //         $output = "[fd]:{$fd} user has been added to game".PHP_EOL;
    //         formatLog("output", $output);
    //         $this->server->send($fd, $output."\n");
    //         $output = "Waiting another player.";
    //         formatLog("output", $output);
    //         $this->server->send($fd, $output."\n");
    //     }
    //     return "[fd]:{$fd} user has been added to game".PHP_EOL;
    // }

    public function newPlayers($fd){
        $this->users[$fd] = True;
        $this->waitingUsers->push($fd);
        $output = "[fd]:{$fd} user has been added to game".PHP_EOL;
        formatLog("output", $output);
        $this->server->send($fd, $output."\n");
        if(count($this->waitingUsers) == $this->playersNum){;
            $this->tableNum +=1;
            $table = new Blackjack($this->waitingUsers, $this->server, $this->tableNum);

            $this->tables[] = $table;
            foreach($this->waitingUsers as $f){
                $this->server->clientConnect[$f]->table = $table;
            }
            unset($this->waitingUsers);
            $this->waitingUsers = new SplDoublyLinkedList();;
        }
        else{
            $output = "Waiting another player.";
            formatLog("output", $output);
            $this->server->send($fd, $output."\n");
        }
    }

}