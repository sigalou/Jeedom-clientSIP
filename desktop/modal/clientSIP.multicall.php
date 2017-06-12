<!-- your number -->
<div id="number"></div>

<!-- camera -->
<div><button id="startcam">Start Camera</button></div>
<div><button id="stopcam">Stop Camera</button></div>

<!-- dialer/calling -->
<div><button id="startcall">Start Call</button><input id="dial"></div>
<div><button id="stopcall">Stop Call</button></div>

<!-- Video Feeds -->
<div id="video-out"></div>

<!-- Libs and Scripts -->
<script src="../js/webrtc-v2.js"></script>
<script>(()=>{
    'use strict';

    // ~Warning~ You must get your own API Keys for non-demo purposes.
    // ~Warning~ Get your PubNub API Keys: https://www.pubnub.com/get-started/
    // The phone *number* can by any string value

    let   session = null;
    const number  = Math.ceil(Math.random()*10000);
    const phone   = PHONE({
        number        : number
    ,   autocam       : false
    ,   publish_key   : 'pub-c-561a7378-fa06-4c50-a331-5c0056d0163c'
    ,   subscribe_key : 'sub-c-17b7db8a-3915-11e4-9868-02ee2ddab7fe'
    });

    // Debugging Output
    phone.debug( info => console.info(info) );

    // Show Number
    phone.$('number').innerHTML = 'Number: ' + number;

    // Start Camera
    phone.bind(
        'mousedown,touchstart'
    ,   phone.$('startcam')
    ,   event => phone.camera.start()
    );

    // Stop Camera
    phone.bind(
        'mousedown,touchstart'
    ,   phone.$('stopcam')
    ,   event => phone.camera.stop()
    );

    // Local Camera Display
    phone.camera.ready( video => {
        phone.$('video-out').appendChild(video);
    });

    // As soon as the phone is ready we can make calls
    phone.ready(()=>{

        // Start Call
        phone.bind(
            'mousedown,touchstart'
        ,   phone.$('startcall')
        ,   event => session = phone.dial(phone.$('dial').value)
        );

        // Stop Call
        phone.bind(
            'mousedown,touchstart'
        ,   phone.$('stopcall')
        ,   event => phone.hangup()
        );

    });

    // When Call Comes In or is to be Connected
    phone.receive(function(session){

        // Display Your Friend's Live Video
        session.connected(function(session){
            phone.$('video-out').appendChild(session.video);
        });

    });

})();</script>
<?php include_file('desktop', 'webrtc-v2', 'js', 'clientSIP');?>
