<?php
$cardtonum = ["T"=>10, "J"=>11, "Q"=>12, "K"=>13];
$numtocard = [10 => "T", 11=>"J", 12=>"Q", 13=>"K"];

function give_card(){
    global $numtocard;
    $cardlist = [];
    for($i=0; $i<=51;$i++){
        array_push($cardlist, $i);
    }
    $cards1 = "";
    $cards2 = "";
    for($i=0; $i<=9; $i++){
        $tmp = $cardlist[rand(0, 51-$i)];
        $cardlist[$tmp] = $cardlist[51-$i];
        array_pop($cardlist);
        $suit = $tmp%4+1;
        $num = floor($tmp/4)+1;

        if($num > 9){
            $num = $numtocard[$num];
        }

        if($i<5){
            $cards1 .= strval($suit).$num;
        }
        else{
            $cards2 .= strval($suit).$num;
        }
    }
    echo "cards1: $cards1 \ncards2: $cards2\n";
    return [$cards1, $cards2];
}


function decideGame($input){
    if(strlen($input) == 26){
        $p = new Basic($input);
        $p->output13();
    }
    elseif(strlen($input) == 20){
        $card1 = new Basic(substr($input, 0, 10));
        $card2 = new Basic(substr($input, 10, 10));
        $card1->cmpTwoCard($card2);
    }
}

class Basic
{
    public $numberCounter = array();
    public $suits = array();
    public $initial = "";
    public $hands =  array();
    public $cardType = array();
    public $charToNum = array('T'=>'10', 'J'=>'11', 'Q'=>'12', 'K'=>'13');
    public $numToChar = array('10'=>'T', '11'=>'J', '12'=>'Q', '13'=>'K');


    public function __construct($str)
    {
        $this->initial = $str;
        $this->setSuitsAndCounter();
        $this->findAllHands();
    }

    public function setSuitsAndCounter(){
        for($i=1; $i<=13; $i+=1) $this->numberCounter[$i] = array();
        for($i=1; $i<=4; $i+=1) $this->suits[$i] = array();
        for($i=1; $i<strlen($this->initial);$i+=2){
            $num = $this->initial[$i];
            $suit = $this->initial[$i-1];
            if(!is_numeric($num)) $num = $this->charToNum[$num];
            $this->numberCounter[$num][] =  $suit;
            $this->suits[$suit][] = $num;
        }
        for($i=1; $i<=13 ; $i+=1) asort($this->numberCounter[$i]);
        for($i=1; $i<=4; $i+=1) asort($this->suits[$i]);
    }

    public function findAllHands(){
        $numOfCard = 0;
        for($i=1; $i<=4; $i+=1) $numOfCard += count($this->suits[$i]);
        while($numOfCard >=5){
            $this->findHands();
            $numOfCard = 0;
            for($i=1; $i<=4; $i+=1) $numOfCard += count($this->suits[$i]);
        }
        if($numOfCard > 0){
            $this->handToSting($this->numberCounter);
            $this->resetCounterAndSuit($this->numberCounter);
        }
    }

    public function findHands(){
        $mode = -1;
        if(strlen($this->initial) == 5) $mode = 1; // big two
        else $mode = 2; // chinese poker

        $isFinish = false;
        $numFreq = array(1=>array(), 2=>array(), 3=>array(), 4=>array());
        for($i=1; $i<=13; $i+=1){
            if(count($this->numberCounter[$i]) > 0){
                $numFreq[count($this->numberCounter[$i])][] = $i;
            }
        }
        if($mode == 1) for($i=1; $i<=4; $i+=1) usort($numFreq[$i], array('Basic', 'cmpBigTwo'));
        elseif($mode == 2) for($i=1; $i<=4; $i+=1){
            usort($numFreq[$i], array('Basic', 'cmp13'));
            usort($this->suits[$i], array('Basic', 'cmp13'));
        }
        // handArray = [num1=>[1, 2, 3], num2=>[1, 3],...]
        $handArray = array();

        // Find straight flush
        if(count(max($this->suits)) > 4){
            for($i=1; $i<=4; $i+=1){
                $p = $this->isStraight($this->suits[$i]);
                if(count($p) == 5){
                    $maxNum = $p[0];
                    if(in_array(2, $p) && !in_array(1, $p)) $num = 2;
                    while(count($p) != 0){
                        $num = array_pop($p);
                        $handArray[$num] = array($i);
                    }
                    array_unshift($this->cardType, [9, $maxNum, $this->numberCounter[$maxNum][0]]);
                    $isFinish = true;
                    break;
                }
            }
        }
        // Find four of a king.
        if(!$isFinish && count($numFreq[4]) > 0){
            $handArray[$numFreq[4][0]] = array(1, 2, 3, 4);
            for($i=1;$i<=3;$i+=1){
                if(count($numFreq[$i]) > 0){
                    $num2 = $numFreq[$i][0];
                    $num2Suit = $this->numberCounter[$num2][0];
                    $handArray[$num2] = array($num2Suit);
                    break;
                }
            }
            array_unshift($this->cardType, [8, $numFreq[4][0], -1]);
            $isFinish = true;
        }
        // Find full house.
        elseif(!$isFinish && (count($numFreq[3]) > 0 && count($numFreq[2]) > 0) || count($numFreq[3]) > 1){
            $num1 = $numFreq[3][0];
            $num1Suit = $this->numberCounter[$num1];
            $handArray[$num1] = $num1Suit;
            for($i=2;$i<=3;$i+=1){
                if(count($numFreq[$i]) > 0){
                    $num2 = $numFreq[$i][0];
                    $num2Suit = $this->numberCounter[$num2];
                    $handArray[$num2] = array($num2Suit[0], $num2Suit[1]);
                    break;
                }
            }
            array_unshift($this->cardType, [7, $num1, $this->numberCounter[$num1][0]]);
            $isFinish = true;
        }

        // flush
        elseif(!$isFinish && $mode == 2 && count(max($this->suits)) > 4){
            $key = array_search(max($this->suits), $this->suits);
            for($i=0; $i<=4;$i+=1){
                $handArray[$this->suits[$key][$i]] = array($key);
            }
            array_unshift($this->cardType, [6, $this->suits[$key][0], $key]);
            $isFinish = true;
        }
        //Find straight.
        if(!$isFinish && count($this->numberCounter) > 4){
            $nums = array();
            for($i=1; $i<=13;$i+=1){
                if(count($this->numberCounter[$i]) > 0) $nums[] = $i;
            }
            $p = $this->isStraight($nums);
            if(count($p) == 5){
                $maxNum = $p[0];
                if(in_array(2, $p) && !in_array(1, $p)) $num = 2;
                while(count($p) != 0){
                    $num = array_pop($p);
                    $handArray[$num] = array($this->numberCounter[$num][0]);
                }
                array_unshift($this->cardType, [5, $num, $this->numberCounter[$num][0]]);
                $isFinish = true;
            }

        }

        if(!$isFinish){
            if($mode == 2){
                // 3 same numbers.
                if(count($numFreq[3]) > 0){
                    $num1 = $numFreq[3][0];
                    $handArray[$num1] = $this->numberCounter[$num1];
                    $num2 = $numFreq[1][0];
                    $num3 = $numFreq[1][1];
                    echo "$num2, $num3";
                    $handArray[$num2] = [$this->numberCounter[$num2][0]];
                    $handArray[$num3] = [$this->numberCounter[$num3][0]];
                    array_unshift($this->cardType, [4]);
                    $isFinish = true;
                }

                // 2 pairs
                elseif(!$isFinish && count($numFreq[2]) > 1){
                    for($i=0; $i<=1; $i+=1){
                        $num = $numFreq[2][$i];
                        $handArray[$num] = array();
                        for($j=0; $j<=1; $j+=1){
                            $suit = $this->numberCounter[$num][$j];
                            $handArray[$num][] = $suit;
                        }
                    }
                    $handArray[$numFreq[1][0]] = [$this->numberCounter[$numFreq[1][0]][0]];
                    array_unshift($this->cardType, [3]);
                    $isFinish = true;
                }

                elseif(!$isFinish && count($numFreq[2]) > 0){
                    $num = $numFreq[2][0];
                    for($i=0;$i<=1;$i+=1) $handArray[$num] = array_slice($this->numberCounter[$num], 0, 2);
                    for($i=0; $i<=2; $i+=1){
                        $num = $numFreq[1][$i];
                        $handArray[$num] = [$this->numberCounter[$num][0]];
                    }
                    array_unshift($this->cardType, [2]);
                    $isFinish = true;
                }
            }
            if($mode == 1 || !$isFinish){
                $handArray = $this->numberCounter;
                array_unshift($this->cardType, [0]);
            }

        }
        $this->handToSting($handArray);
        $this->resetCounterAndSuit($handArray);

    }

    // nums = [1, 2, 5, 6, 7, 8, ...] return straight nums
    public function isStraight($nums){
        if(count($nums) < 5) return array();
        else{
            usort($nums, array('Basic', 'cmp13'));
            if($nums[0] == 1){
                if($nums[4] == 10) return array_slice($nums, 0, 5);
                else $nums[] = array_shift($nums);
            }
            $ind1 = 0;
            $ind2 = 4;
            while($ind2 < count($nums)){
                if($nums[$ind1] - $nums[$ind2] == 4) return array_slice($nums, $ind1, 5);
                $ind1 +=1;
                $ind2 +=1;
            }
            return array();
        }
    }

    public function handToSting($hand){
        $tmp = "";
        $k = array_keys($hand);
        while(count($k) != 0){
            $num = array_pop($k);
            $suits = $hand[$num];
            if($num > 9) $num = $this->numToChar[$num];
            while(count($suits) != 0) $tmp .= array_pop($suits) . $num;
        }
        array_unshift($this->hands, $tmp);
    }

    public function resetCounterAndSuit($hand){
        $nums = array_keys($hand);
        while(count($nums) != 0){
            $num = array_pop($nums);
            while(count($hand[$num]) != 0){
                $suit = array_pop($hand[$num]);
                $key = array_search($suit, $this->numberCounter[$num]);
                unset($this->numberCounter[$num][$key]);
                $key = array_search($num, $this->suits[$suit]);
                unset($this->suits[$suit][$key]);
            }
        }
    }

    public static function cmp13($a, $b){
        if($a == 1) $a += 13;
        if($b == 1) $b += 13;
        return $b-$a;
    }

    public static function cmpBigTwo($a, $b){
        if($a == 1 || $a == 2) $a += 13;
        if($b == 1 || $b == 2) $b += 13;
        return $b-$a;
    }

    public function output13(){
        echo "Front: " . $this->hands[0] . "\n";
        echo "Middle: " . $this->hands[1] . "\n";
        echo "Back: " . $this->hands[2] . "\n";
    }

    public function cmpTwoCard($object){
        if($this->cardType[0][0] > $object->cardType[0][0]){
            return 1;
        }
        elseif($this->cardType[0][0] < $object->cardType[0][0]){
            return -1;
        }
        elseif($this->cardType[0][0] != 0){
            $a = $this->cardType[0][1];
            $b = $object->cardType[0][1];
            if($this->cmpBigTwo($a, $b)<0){
                return 1;
            }
            elseif($this->cmpBigTwo($a, $b) > 0){
                return -1;
            }
            if($this->cardType[0][2] < $object->cardType[0][2]){
                return 1;
            }
            else{
                return -1;
            }

        }
    }
}