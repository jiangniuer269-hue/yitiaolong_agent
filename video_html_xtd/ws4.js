$(function(){
    var canvas = document.getElementById("videoCanvas");
    var width = document.documentElement.clientWidth;
    var height = width * 108 / 192;
    canvas.style.width = width + "px";
    canvas.style.height = height + "px";
    initNodeplayer();
});

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






