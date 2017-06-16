/*

            i  = '<div class="list-group-item sip-logitem clearfix '+callClass+'" data-uri="'+item.uri+'" data-sessionid="'+item.id+'" title="Double Click to Call">';
            i += '<div class="clearfix"><div class="pull-left">';
            i += '<i class="fa fa-fw '+callIcon+' fa-fw"></i> <strong>'+ctxSip.formatPhone(item.uri)+'</strong><br><small>'+moment(item.start).format('MM/DD hh:mm:ss a')+'</small>';
            i += '</div>';
            i += '<div class="pull-right text-right"><em>'+item.clid+'</em><br>' + callLength+'</div></div>';

            if (callActive) {
                i += '<div class="btn-group btn-group-xs pull-right">';
                if (item.status === 'ringing' && item.flow === 'incoming') {
                    i += '<button class="btn btn-xs btn-success btnCall" title="Call"><i class="fa fa-phone"></i></button>';
                } else {
                    i += '<button class="btn btn-xs btn-primary btnHoldResume" title="Hold"><i class="fa fa-pause"></i></button>';
                    i += '<button class="btn btn-xs btn-info btnTransfer" title="Transfer"><i class="fa fa-random"></i></button>';
                    i += '<button class="btn btn-xs btn-warning btnMute" title="Mute"><i class="fa fa-fw fa-microphone"></i></button>';
                }
                i += '<button class="btn btn-xs btn-danger btnHangUp" title="Hangup"><i class="fa fa-stop"></i></button>';
                i += '</div>';
            }
            i += '</div>';

            $('#sip-logitems').append(i);
*/
$(document).ready(function() {
    // Auto-focus number input on backspace.
    $('#sipClient').keydown(function(event) {
        if (event.which === 8) {
            $('#numDisplay').focus();
        }
    });
    $('#numDisplay').keypress(function(e) {
        // Enter pressed? so Dial.
        if (e.which === 13) {
            //ctxSip.phoneCallButtonPressed();
        }
    });
    $('.digit').click(function(event) {
        event.preventDefault();
        var num = $('#numDisplay').val(),
            dig = $(this).data('digit');

        $('#numDisplay').val(num+dig);

       // ctxSip.sipSendDTMF(dig);
        return false;
    });
    $('#phoneUI .dropdown-menu').click(function(e) {
        e.preventDefault();
    });
    $('#phoneUI').on('click', '.btnCall', function(event) {
       // ctxSip.phoneCallButtonPressed();
        // to close the dropdown
        return true;
    });
    $('.sipLogClear').click(function(event) {
        event.preventDefault();
       // ctxSip.logClear();
    });
    $('#sip-logitems').on('click', '.sip-logitem .btnCall', function(event) {
        var sessionid = $(this).closest('.sip-logitem').data('sessionid');
      //  ctxSip.phoneCallButtonPressed(sessionid);
        return false;
    });
    $('#sip-logitems').on('click', '.sip-logitem .btnHoldResume', function(event) {
        var sessionid = $(this).closest('.sip-logitem').data('sessionid');
      //  ctxSip.phoneHoldButtonPressed(sessionid);
        return false;
    });
    $('#sip-logitems').on('click', '.sip-logitem .btnHangUp', function(event) {
        var sessionid = $(this).closest('.sip-logitem').data('sessionid');
      //  ctxSip.sipHangUp(sessionid);
        return false;
    });
    $('#sip-logitems').on('click', '.sip-logitem .btnTransfer', function(event) {
        var sessionid = $(this).closest('.sip-logitem').data('sessionid');
       // ctxSip.sipTransfer(sessionid);
        return false;
    });
    $('#sip-logitems').on('click', '.sip-logitem .btnMute', function(event) {
        var sessionid = $(this).closest('.sip-logitem').data('sessionid');
       // ctxSip.phoneMuteButtonPressed(sessionid);
        return false;
    });
    $('#sip-logitems').on('dblclick', '.sip-logitem', function(event) {
        event.preventDefault();

        var uri = $(this).data('uri');
        $('#numDisplay').val(uri);
     //   ctxSip.phoneCallButtonPressed();
    });
    $('#sldVolume').on('change', function() {

        var v      = $(this).val() / 100,
            // player = $('audio').get()[0],
            btn    = $('#btnVol'),
            icon   = $('#btnVol').find('i'),
          //  active = ctxSip.callActiveID;

        // Set the object and media stream volumes
     //   if (ctxSip.Sessions[active]) {
     //       ctxSip.Sessions[active].player.volume = v;
      //      ctxSip.callVolume                     = v;
      //  }

        // Set the others
        $('audio').each(function() {
            $(this).get()[0].volume = v;
        });

        if (v < 0.1) {
            btn.removeClass(function (index, css) {
                   return (css.match (/(^|\s)btn\S+/g) || []).join(' ');
                })
                .addClass('btn btn-sm btn-danger');
            icon.removeClass().addClass('fa fa-fw fa-volume-off');
        } else if (v < 0.8) {
            btn.removeClass(function (index, css) {
                   return (css.match (/(^|\s)btn\S+/g) || []).join(' ');
               }).addClass('btn btn-sm btn-info');
            icon.removeClass().addClass('fa fa-fw fa-volume-down');
        } else {
            btn.removeClass(function (index, css) {
                   return (css.match (/(^|\s)btn\S+/g) || []).join(' ');
               }).addClass('btn btn-sm btn-primary');
            icon.removeClass().addClass('fa fa-fw fa-volume-up');
        }
        return false;
    });
    // Hide the spalsh after 3 secs.
    setTimeout(function() {
     //   ctxSip.logShow();
    }, 3000);
    var Stopwatch = function(elem, options) {

        // private functions
        function createTimer() {
            return document.createElement("span");
        }

        var timer = createTimer(),
            offset,
            clock,
            interval;

        // default options
        options           = options || {};
        options.delay     = options.delay || 1000;
        options.startTime = options.startTime || Date.now();

        // append elements
        elem.appendChild(timer);

        function start() {
            if (!interval) {
                offset   = options.startTime;
                interval = setInterval(update, options.delay);
            }
        }

        function stop() {
            if (interval) {
                clearInterval(interval);
                interval = null;
            }
        }

        function reset() {
            clock = 0;
            render();
        }

        function update() {
            clock += delta();
            render();
        }

        function render() {
            timer.innerHTML = moment(clock).format('mm:ss');
        }

        function delta() {
            var now = Date.now(),
                d   = now - offset;

            offset = now;
            return d;
        }

        // initialize
        reset();

        // public API
        this.start = start; //function() { start; }
        this.stop  = stop; //function() { stop; }
    };
});
