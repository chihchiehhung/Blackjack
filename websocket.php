<?php

$ip = '';
$serverAddressCrypt = '';
?>
<html>

<head>
    <style>
        input,
        button {
            padding: 10px;
        }

        pre {
            white-space: pre-wrap;
            /* css-3 */
            white-space: -moz-pre-wrap;
            /* Mozilla, since 1999 */
            white-space: -pre-wrap;
            /* Opera 4-6 */
            white-space: -o-pre-wrap;
            /* Opera 7 */
            word-wrap: break-word;
            /* Internet Explorer 5.5+ */
        }
    </style>
</head>
<link type="text/css" rel="stylesheet" href="//g.mwsrv.com/css/bootstrap/3.3.6/bootstrap.min.css" />
<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.min.css" />

<body>
    <div id="app">
        <!-- 註冊畫面 -->
        <div v-if="!islogin">
            <div class="container">
                <form class="form-signin">
                    <h2 class="form-signin-heading">Please sign in</h2>
                    <label for="inputEmail" class="sr-only">Email address</label>
                    <input type="email" v-model="email" id="inputEmail" placeholder="Email address" required="required" autofocus="autofocus" class="form-control">
                    <label for="inputPassword" class="sr-only">Password</label>
                    <input type="password" v-model="pwd" id="inputPassword" placeholder="Password" required="required" class="form-control">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" value="remember-me"> Remember me
                        </label>
                    </div>
                    <button type="submit" class="btn btn-lg btn-primary btn-block" @click="signIn">Sign in</button>
                </form>
            </div>
        </div>
        <!--操控按鈕-->
        <div v-if="islogin">
        <!-- <div> -->
            <button v-if="!isReady" @click="send('ready')">Ready</button>
            <span v-if="choosingGame">
            <!-- <span > -->
                <button @click="send('Blackjack')">Blackjack</button>
                <button @click="send('compareCard')">比牌型</button>
            </span>

            <button  @click="clearCmd()">Clear</button>
            <br>
            <!---->
            <span v-if="gameType == 'blackjack'">
            <!-- <span > -->
                <span v-if="blackjackControl">
                    <button @click="send('Hit')">Hit</button>
                    <button @click="send('Stand')">Stand</button>
                </span>
                <button v-if="finishGame" @click="send('Restart')">Restart</button>
                <br>
                <h3>You are player{{parseInt(Number(userNum)+Number(1))}}</h3>
                <!-- Broadcast -->
                <div>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th class="col-xs-1">time</th>
                                <th>BroadCasting</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(value,index) in broadCastHistory">
                                <tr >
                                    <td>{{value.time}}</td>
                                    <td>
                                        <pre>{{value.cmd}}</pre>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <!-- 排名 -->
                <h3>Rank</h3>
                <div>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th class="col-xs-3">Player1</th>
                                <th class="col-xs-3">Player2</th>
                                <th class="col-xs-3">Player3</th>
                                <th class="col-xs-3">Server</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template>
                                <tr >
                                    <td v-for="rank in ranks">{{rank}}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <!-- 牌表 -->
                <div>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th class="col-xs-3">Player1</th>
                                <th class="col-xs-3">Player2</th>
                                <th class="col-xs-3">Player3</th>
                                <th class="col-xs-3">Server</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template>
                                <tr >
                                    <td v-for="cards in cardsList">{{cards}}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </span>

        </div>
        <!--流向圖-->
        <div>
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th class="col-xs-1">time</th>
                        <th class="col-xs-1">流向</th>
                        <th>content</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(value,index) in cmdHistory">
                        <tr :class="value.source==='send'? 'warning':'info'">
                            <td>{{value.time}}</td>
                            <td>{{value.source==='send'? 'C->S': 'S->C'}}</td>
                            <td>
                                <pre>{{value.cmd}}</pre>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>

<!-- Load polyfills to support older browsers -->
<script src="//polyfill.io/v3/polyfill.min.js?features=es2015%2CIntersectionObserver" crossorigin="anonymous"></script>
<!-- Load Vue followed by BootstrapVue -->

<!-- Load the following for BootstrapVueIcons support -->


<script>
    const app = new Vue({
        el: '#app',
        data: function() {
            return {
                cmdToServer: '',
                server: {
                    serverAddressCrypt: "<?= $serverAddressCrypt ?>",
                    url: '',
                },
                connection: {},
                cmdHistory: [],
                broadCastHistory:[],
                islogin: false,
                isReady: false,
                email:"",
                pwd:"",
                choosingGame: false,
                gameType: "",
                blackjackControl: false,
                cardsList:["", "", "", ""],
                hiddenCardsList:[],
                userNum: -1,
                ranks:[],
                finishGame:false,
            }
        },
        methods: {
            transmitCmd: function(cmd) {
                cmd = cmd || this.cmdToServer
                this.send(`${cmd}\n`)
            },
            clearCmd: function() {
                this.cmdHistory = []
            },
            send: function(cmd) {
                this.connection.send(cmd)
                if (!/_ping/.test(cmd)) {
                    let today = new Date();
                    let time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();
                    this.cmdHistory.unshift({
                        'source': 'send',
                        'cmd': cmd,
                        'time': time
                    })
                }
                switch (cmd) {
                    case 'Blackjack':
                        this.choosingGame = false;
                        this.gameType = "blackjack";
                        break;
                    case 'compareCard':
                        this.choosingGame = false;
                        this.gameType = 'compareCard';
                        break;
                    case 'Restart':
                        this.finishGame = false;
                        this.isReady = false;
                        this.gameType = "";
                        this.broadCastHistory = [];
                        this.ranks = [];
                        this.cardsList = [];
                }
            },
            signIn:function(){
                let msg = "signIn " + this.email + " " + this.pwd +"\n";
                this.send(msg);
            },
            onmessage: function(input) {
                let self = this
                let chunk = input.split(' ')
                let [cmd, data] = [chunk.shift(), chunk.join(' ')]
                if (cmd !== '' && !/_pong/.test(cmd)) {
                    let today = new Date();
                    let time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();
                    this.cmdHistory.unshift({
                        'source': 'receive',
                        'cmd': input,
                        'time': time
                    })
                }
                switch (cmd) {
                    case 'connected':
                        self.send('helo\n')
                        break;
                    case 'helo':
                        d = JSON.stringify({
                            client_wid: -945
                        })
                        self.send(`session_status ${d}\n`)
                        break;
                    case 'ready':
                        this.isReady = true;
                        this.choosingGame = true;
                        break;
                    case 'signIn':
                        if(data == '1'){
                            this.islogin = true;
                        }
                        else{
                            alert("Username or password is not correct.");
                        }
                        break;
                    case 'youTurn':
                        if(data == 'on'){
                            this.blackjackControl = true
                        }
                        else{
                            this.blackjackControl = false
                        }
                        break;
                    case 'Blackjack_cardsList':
                        if(!this.finishGame){
                            let cards = data.split(',');
                            this.userNum = cards.shift();
                            let i = 0;
                            this.cardsList = [];
                            this.hiddenCardsList = [];
                            cards.forEach(e => {
                                    console.log(this.userNum);
                                    if(i == this.userNum){
                                        this.cardsList.push(e)
                                    }
                                    else{
                                        this.cardsList.push('unkown, '.concat(e.slice(4)))
                                    }
                                    this.hiddenCardsList.push(e)
                                    i += 1;
                                }
                            );
                            console.log(this.cardsList);
                        }
                        break;
                    case 'blackjackBroadCast':
                        let today = new Date();
                        let time = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();
                        this.broadCastHistory.unshift({
                        'cmd': data,
                        'time': time
                    })
                        break;
                    case 'blackjackRank':
                        this.ranks = data.split(',');
                        this.cardsList = []
                        this.hiddenCardsList.forEach(e=> this.cardsList.push(e))
                        console.log(this.ranks);
                        this.finishGame = true;
                        break;

                }
            },
            socketInit() {
                self = this
                this.connection = new WebSocket(self.server.url)
                this.connection.onopen = (e) => {
                    if (WebSocket.OPEN == self.connection.readyState) {
                        self.send('!i-delimiter\n')
                        let host = window.location.hostname.split('.')[0];
                        self.send(`!proxy ${self.server.serverAddressCrypt}\n`)
                    }
                    self.connection.onmessage = function(e) {
                        self.onmessage(e.data)
                    }
                }
            }
        },
        created: function() {
            this.socketInit()
        }
    });
</script>