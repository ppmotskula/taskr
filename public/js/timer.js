function timer(prev, start) {
    var time = new Date();
    if (start == 0) {
        start = parseInt(time.getTime()/1000);
    }
    var seconds = parseInt(time.getTime()/1000 - start + prev);
    var hours = parseInt(seconds/3600);
    seconds = seconds - 3600 * hours;
    var minutes = parseInt(seconds/60);
    seconds = seconds - 60 * minutes;
    if (hours < 10) {
        hours = "0" + hours;
    }
    if (minutes < 10) {
        minutes = "0" + minutes;
    }
    if (seconds < 10) {
        seconds = "0" + seconds;
    }
    var timerbox = document.getElementById('timer');
    timerbox.innerHTML = ' (' + hours + ":" + minutes + ":" + seconds + ')';
    setTimeout('timer(' + prev + ', ' + start + ')', 1000);
}
