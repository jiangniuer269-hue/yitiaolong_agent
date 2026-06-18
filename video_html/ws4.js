$(function(){
    var canvas = document.getElementById("videoCanvas");
    var width = document.documentElement.clientWidth;
    var height = width * 108 / 192;
    canvas.style.width = width + "px";
    canvas.style.height = height + "px";
    initNodeplayer();
});

var modevideo=2;
var player1=null;
var startFunc = function (url) {
    player1.start(url);
}
var stopFunc = function () {
    player1.stop();
}

//获取url请求参数
function getQueryVariable(variable){
       var query = window.location.search.substring(1);
       var vars = query.split("&");
       for (var i=0;i<vars.length;i++) {
               var pair = vars[i].split("=");
               if(pair[0] == variable){return pair[1];}
       }
       return(false);
}


function initNodeplayer(){
    var roomNumber = getQueryVariable('app');
     var number = getQueryVariable('number');
    try {
        player1 = new NodePlayer();
        player1.setView('videoCanvas');
        if (roomNumber == 1941) {
             player1.start("ws://45.125.45.56:8008/SL/194100.flv");
        }else if(roomNumber == 1947){
             player1.start("ws://43.248.133.10:8008/SS/1947.flv");
        }else if(roomNumber == 'blm'){
             player1.start("http://blm-ws-auth-desk.jlrygwy.com/live/ylc"+number+".flv?webhost=MTYuMTYzLjE0OS4yMTQ=&Authblm=ZGVza3Rlc3RfMTcxNTM5NjUxMj191952");
        }else if(roomNumber == 'kkw'){
             player1.start("http://27.124.46.84:5300/bb23/kkvideocg88.flv?sign=1740412765-7738c7ff891299af451f14edc4f6d83d");
        }
       
        //ws://45.125.45.56:8008/SS/1947.flv
        //ws://45.125.45.56:8008/SSS/1941.flv
        //http://27.124.46.84:5200/live/ylc102_480.flv?webhost=YmxtLTg4OC5jb20=&Authblm=b2sxMzI3MDAzXzE3MzI0NjYxMTAxNjM251325
       // http://blm-ws-auth-desk.jlrygwy.com/live/ylc102.flv?webhost=MTYuMTYzLjE0OS4yMTQ=&Authblm=ZGVza3Rlc3RfMTcxNTM5NjUxMj966346
       // player1.start("http://27.124.46.91:3046/live?port=8762&app="+roomNumber);
        player1.setScaleMode(0);
        player1.setBufferTime(1000);
        player1.skipLoopFilter(32);
        player1.on('start', () => {
        });
        player1.on('error', (e) => {
        });
        player1.on('stop', () => {
        });
    }
    catch (e) {
        console.log('catch');
    }
}


function getQueryVariable(variable) {
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i = 0; i < vars.length; i++) {
        var pair = vars[i].split("=");
        if (pair[0] == variable) {
            return pair[1];
        }
    }
}




