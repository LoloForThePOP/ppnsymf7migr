
    
// Detect if user is offline/online status in simple way.
// Thx to Didier L. https://stackoverflow.com/questions/189430/detect-the-internet-connection-is-offline

window.addEventListener('online',  updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

function updateOnlineStatus(event) {
    var condition = navigator.onLine ? "user-online" : "user-offline";
    document.body.className = condition;
}



// check if user device is a tiny screen

var tinyScreen = false;

function checkTinyScreen(){

if ($(window).width() < 590) {
    tinyScreen = true;
    }else {
    tinyScreen = false;
    } 

}

checkTinyScreen(); //check on page load


$(window).on('resize', function() { //check if resized page

    checkTinyScreen();

});
