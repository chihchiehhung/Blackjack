<?php

/*
Todos:
1.
2.
*/

use LDAP\Result;

DEFINE("POKER_SUIT", [
    1 => "♣️",
    2 => "♦️",
    3 => "❤️",
    4 => "♠️"
]);


class Blackjack
{
    public $cardtonum = ["T"=>10, "J"=>11, "Q"=>12, "K"=>13];
    public $numtocard = [10 => "T", 11=>"J", 12=>"Q", 13=>"K", 1=> "A"];
    public $tableNum = 0;
    public $users = [];
    public $playerNum = 0;
    public $server;
    public $deckNum = 1;
    public $cardPool;
    public $cardsList = [];//user1, user2,..., usern,  server
    public $pointList = array(0, 0, 0);
    public $gameStage = 0; // 0:Give all players cards, 1:user1 play, 2: user2 play,..., n: usern play, n+1: server play
    public $result = []; // 0: undeterminate, 1:user wins -1:user lose
    public $rank = [];


    public function __construct($users, $server, $tableNum)
    {
        $this->cardPool = range(0, 51 + (($this->deckNum-1) * 52));
        $this->tableNum = $tableNum;
        $this->users = $users;
        $this->playerNum = count($users) + 1;
        $this->server = $server;
        $this->cardsList[] = [];
        $this->result[] = 0;
        foreach($users as $player){
            $this->startGameNotify($player);
            $this->cardsList[] = [];
            $this->result[] = 0;

        }
        $this->firstRoundGiveCards();
         //user1 start
        $output = "youTurn on";
        formatLog("output", $output);
        $this->server->send($this->users[0], $output."\n");

    }

    // public function __construct($users $server)
    // {
    //     $this->cardPool = range(0, 51 + (($this->deckNum-1) * 52));
    //     $this->user1 = $user1;
    //     $this->user2 = $user2;
    //     $this->server = $server;
    //     $players = [$user1, $user2];
    //     foreach($players as $p){
    //         $this->startGameNotify($p);
    //     }
    //     $this->firstRoundGiveCards();
    //     //user1 start
    //     $output = "youTurn on";
    //     formatLog("output", $output);
    //     $this->server->send($user1, $output."\n");
    //     print_r($this->cardsList);
    //     print_r($this->pointList);
    // }

    public function startGameNotify($fd){
        $output = "[fd]:{$fd} user is ready to start game".PHP_EOL;
        formatLog("output", $output);
        $this->server->send($fd, $output."\n");
    }

    public function firstRoundGiveCards(){
        for($i=0;$i<($this->playerNum*2);$i++){
            $this->giveACard(intval(floor($i/2)));
        }
        for($j=0;$j< $this->playerNum;$j++){
            $this->calculatePt($j);
        }
        $this->sendState();
        $this->gameStage = 1;
    }

    public function hit(){
        if($this->gameStage <= $this->playerNum-1){
            $ind = $this->gameStage - 1;
            $this->giveACard($ind);
            $this->calculatePt($ind);
            if($this->pointList[$ind] > 21){
                echo "fd: " . $this->users[$ind] . "lose" . PHP_EOL;
                $this->server->send($this->users[$ind], "youTurn off\n");
                $this->server->send($this->users[$ind], "BlackjackResult You lose\n");
                $this->result[$ind] = -1;
                $this->gameStage += 1;
                if($this->gameStage <= $this->playerNum-1){
                    $output = "youTurn on";
                    formatLog("output", $output);
                    $this->server->send($this->users[$ind+1], $output."\n");
                }
            }
        }
        if($this->gameStage == $this->playerNum){
            $this->finishGame();
        }
        // if($this->gameStage == 1){
        //     $this->giveACard(0);
        //     $this->calculatePt(0);
        //     if($this->pointList[0]>21){
        //         $this->server->send($this->user1, "youTurn off\n");
        //         $this->server->send($this->user1, "BlackjackResult You lose\n");
        //         $this->result[0] = -1;
        //         $this->gameStage = 2;
        //         $output = "youTurn on";
        //         formatLog("output", $output);
        //         $this->server->send($this->user2, $output."\n");
        //     }
        // }
        // elseif($this->gameStage == 2){
        //     $this->giveACard(1);
        //     $this->calculatePt(1);
        //     if($this->pointList[1]>21){
        //         $this->server->send($this->user2, "youTurn off\n");
        //         $this->server->send($this->user2, "BlackjackResult You lose\n");
        //         $this->result[1] = -1;
        //         $this->gameStage = 3;
        //         $this->finishGame();
        //     }
        // }
        $this->sendState();
    }

    public function stand(){
        $ind = $this->gameStage -1;
        $this->server->send($this->users[$ind], "youTurn off\n");
        $this->gameStage += 1;
        if($this->gameStage <= $this->playerNum-1){
            $output = "youTurn on";
            formatLog("output", $output);
            $this->server->send($this->users[$ind+1], $output."\n");
        }
        elseif($this->gameStage == $this->playerNum){
            $this->finishGame();
        }
    }

    public function finishGame(){
        if($this->gameStage != $this->playerNum){
            return false;
        }
        $serverInd = $this->playerNum-1;
        while($this->pointList[$serverInd] < 17){
            $this->giveACard($serverInd);
            $this->calculatePt($serverInd);
            $this->specialCard($serverInd);
            $this->sendState();
            if($this->pointList[$serverInd] > 21){
                $this->result[$serverInd] = -1;
                for($i=0;$i<$this->playerNum-1;$i+=1){
                    if($this->result[$i] == 0){
                        $this->result[$i] = 1;
                    }
                }
                $this->result[$serverInd] = -1;
            }
        }
        echo "finish first" . PHP_EOL;
        print_r($this->result);
        for($i=0;$i<$this->playerNum-1;$i+=1){
            if($this->result[$i]< $this->result[$serverInd] and $this->result[$serverInd] != 0){
                $this->result[$i] = (-1 * $this->result[$serverInd]);
            }
            if($this->result[$i] == 0){
                if($this->pointList[$i] > $this->pointList[$serverInd]){
                    $this->result[$i] = 1;
                }
                elseif($this->pointList[$i] < $this->pointList[$serverInd]){
                    $this->result[$i] = -1;
                }
                else{
                    $this->server->send($this->users[$i], "BlackjackResult You tie!\n");
                }
            }
            if($this->result[$i] >= 1){
                $this->server->send($this->users[$i], "BlackjackResult You win!\n");
            }
            elseif($this->result[$i] <= -1){
                $this->server->send($this->users[$i], "BlackjackResult You lose!\n");
            }
            $this->server->send($this->users[$i], "finish\n");
            $moneyDiff = $this->result[$i]*100;
            $this->server->clientConnect[$this->users[$i]]->money += ($moneyDiff);
            $this->result[$i] = $moneyDiff;
            echo "user$i money diff $moneyDiff" . PHP_EOL;
        }
        echo "finish second" . PHP_EOL;
        print_r($this->result);
        $this->record();
        $this->getRank();
    }

    public function giveACard($ind){
        $n = count($this->cardPool);
        $tmp = $this->cardPool[rand(0, $n-1)];
        $tmp %= 52;
        $cardlist[$tmp] = $this->cardPool[$n-1];
        array_pop($this->cardPool);
        $suit = POKER_SUIT[$tmp%4+1];
        $num = intval(floor($tmp/4)+1);
        if(array_key_exists($num, $this->numtocard)){
            $num = $this->numtocard[floor($tmp/4)+1];
        }
        $this->cardsList[$ind][] = $suit.$num;
    }

    public function sendState(){
        $fdList = $this->users;
        for($i=0;$i<count($fdList);$i+=1){
            $output = "";
            $cards = "";
            for($j=0;$j<count($fdList);$j+=1){
                $cards .= implode(" ", $this->cardsList[$j]) . ",";
            }
            $cards .= implode(" ", $this->cardsList[count($fdList)]);
            $this->server->send($fdList[$i], "Blackjack_cardsList $i,$cards\n");
        }
        // foreach($fdList as $fd){
        //     $output = "";
        //     if($fd == $this->user1){
        //         $cards = implode(",", $this->cardsList[0]) . " XX".implode(",", array_slice($this->cardsList[1], 1));
        //         $output .= "You are user1.\n User1:". implode(",", $this->cardsList[0]) ."  User2: XX, " . implode(",", array_slice($this->cardsList[1], 1));
        //     }
        //     elseif($fd == $this->user2){
        //         $cards = "XX". implode(",", array_slice($this->cardsList[0], 1)). " ".implode(",", $this->cardsList[1]);
        //         $output .= "You are user2.\n User2: XX, ". implode(",", array_slice($this->cardsList[0], 1))." User2:" .implode(",", $this->cardsList[1]);
        //     }
        //     $output .= "Server: XX,". implode(",", array_slice($this->cardsList[2], 1)) ."\n";
        //     formatLog("output", $output);
        //     $this->server->send($fd, $output);
        //     $cards .= " XX".$this->cardsList[2][1];
        //     $this->server->send($fd, "Blackjack_cardsList $cards\n");
        //     print_r($this->cardsList);
        // }

    }
    // user1 => 0, user2 => 1, server => 2
    public function calculatePt($ind){
        $pts = 0;
        $As = 0;
        foreach($this->cardsList[$ind] as $card){
            if($card[6] == "A"){
                $As +=1;
            }
            elseif(array_key_exists($card[6], $this->cardtonum)){
                $pts += 10;
            }
            else{
                $pts += intval($card[6]);
            }
        }
        while($As>0){
            if($pts + 10 <= 21){
                $pts += 11;
            }
            else{
                $pts +=1;
            }
            $As -= 1;
        }
        $this->pointList[$ind] = $pts;
        $this->specialCard($ind);
    }

    public function specialCard($ind){
        $isSpecial = false;
        $ind += 1;
        $msg = "User$ind ";
        if($ind == $this->playerNum){
            $msg = "Server ";
        }
        $ind -=1;
        if($this->pointList[$ind] == 21){
            //bj
            if(count($this->cardsList[$ind]) == 2){
                $msg .= "got a BJ. Congratulation!!";
                $this->result[$ind] = 1.5;
                $isSpecial = true;
            }
            elseif(count($this->cardsList[$ind]) == 3 && $this->cardsList[$ind][0][6] == 7 && $this->cardsList[$ind][1][6] == 7){
                $msg .= "got a 777. Congratulation!!";
                $this->result[$ind] = 10;
                $isSpecial = true;
            }
        }
        if(count($this->cardsList[$ind]) == 5 and $this->pointList[$ind] <= 21){
            $msg .= "got a 5 Card Charlie. Congratulation!!";
            $this->result[$ind] = 3;
            $isSpecial = true;
        }
        if($isSpecial){
            $this->broadCast($msg);
        }
    }

    public function broadCast($msg){
        $msg = 'blackjackBroadCast ' . $msg;
        foreach($this->users as $fd){
            $this->server->send($fd, $msg . "\n");
        }
    }

    public function record(){
        initDBHmulti('slot');
        $time = time();
        $tableNum = $this->tableNum;
        $serverDiff = 0;
        for($i=0;$i<$this->playerNum-1;$i+=1){
            $uid = $this->server->clientConnect[$this->users[$i]]->uid;
            $moneyDiff = $this->result[$i];
            $serverDiff -= $moneyDiff;
            $money = $this->server->clientConnect[$this->users[$i]]->money;
            $money += $moneyDiff;
            $cards = implode(" ", $this->cardsList[$i]);
            $query = "INSERT INTO FH_HW_simonhung_log (uid, cards, money_diff, ctime, tableNum) VALUES ($uid, '$cards', $moneyDiff, $time, $tableNum)";
            echo $query . PHP_EOL;

            // dbhdo('slot', $query);
            $sql = "UPDATE FH_HW_simonhung_user SET money = $money, ctime = $time where uid = $uid";
            // dbhdo('slot', $sql);

        }
        $uid = 0;
        $this->result[$this->playerNum-1] = $serverDiff;
        $moneyDiff = $serverDiff;
        $cards = implode(" ", $this->cardsList[$this->playerNum-1]);
        $query = "INSERT INTO FH_HW_simonhung_log (uid, cards, money_diff, ctime, tableNum) VALUES ($uid, '$cards', $moneyDiff, $time, $tableNum)";
        // dbhdo('slot', $query);
    }

    public function getRank(){
        $x = $this->result;
        print_r($this->result);
        arsort($x);
        $rank = 0;
        $hiddenRank = 0;
        $hold = null;
        foreach($x as $key=>$value){
            $hiddenRank += 1;
            if(is_null($hold) || $value < $hold){
                $rank = $hiddenRank; $hold = $value;
            }
            $this->rank[$key] = $rank;
            echo "$key, $rank\n";
        }
        $res = "";
        $res .= strval($this->rank[0]);
        for($i=1;$i<$this->playerNum;$i+=1){
            $res .= "," . strval($this->rank[$i]);
        }
        print_r($res);
        foreach($this->users as $fd){
            $this->server->send($fd, "blackjackRank $res\n");
        }
    }
}