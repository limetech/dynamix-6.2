<?PHP
/* Copyright 2015, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$var = parse_ini_file('state/var.ini');
$unraid = parse_ini_file('/etc/unraid-version');
$keyfile = trim(base64_encode(@file_get_contents($var['regFILE'])));

if (array_key_exists('getdiagnostics', $_GET)) {
    $diag_file = '/tmp/feedback_diagnostics_'.time().'.zip';
    exec("/usr/local/emhttp/plugins/dynamix/scripts/diagnostics $diag_file");
    echo base64_encode(@file_get_contents($diag_file));
    @unlink($diag_file);
    exit;
}
?>
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-white.css">
<style>
#spinner_image{position:fixed;left:46%;top:46%;width:16px;height:16px;display:none;}
#control_panel{position:fixed;left:5px;right:5px;top:0;padding-top:8px;line-height:24px;white-space:nowrap;}
.four{text-align:center;box-sizing:border-box;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;}
.four label:first-child{margin-left:0;}
.four label{margin-left:2%;cursor:pointer;}
.allpanels{display:none;position:absolute;left:15px;right:15px;top:40px;bottom:25px;overflow:auto;margin-top:15px;}
#footer_panel{position:fixed;left:5px;right:5px;bottom:0;height:30px;line-height:10px;text-align:center}
textarea{width:100%;height:250px;margin:10px 0}
input[type=button]{margin-right:0;float:right}
input[type=email]{margin-top:8px;float:left}
</style>
<body>
<div style="margin-top:0;font-size:12px;line-height:30px;color:#303030;margin-left:5px;margin-right:5px">
<div id="control_panel" class="four">
<label for="optOnlinePoll"><input type="radio" name="mode" id="optOnlinePoll" value="onlinepoll" checked="checked" /> Online Poll</label>
<label for="optFeatureRequest"><input type="radio" name="mode" id="optFeatureRequest" value="featurerequest"/> Feature Request</label>
<label for="optBugReport"><input type="radio" name="mode" id="optBugReport" value="bugreport"/> Bug Report</label>
<label for="optComment"><input type="radio" name="mode" id="optComment" value="comment"/> Other Comment</label>
<hr>
</div>
<div id="thanks_panel" class="allpanels"></div>
<div id="onlinepoll_panel" class="allpanels"></div>
<div id="featurerequest_panel" class="allpanels">
<textarea id="featureDescription" placeholder="Please summarize your feature request here."></textarea>
<br>
<input type="email" id="featureEmail" placeholder="Contact Email Address (optional)" /><input type="button" id="featureSubmit" value="Submit"/>
</div>
<div id="bugreport_panel" class="allpanels">
<textarea id="bugDescription"></textarea>
<p style="line-height:14px;margin-top:0;font-size:11px"><b>NOTE:</b> <i>Submission of this bug report will automatically send your system diagnostics to Lime Technology.</i></p>
<input type="email" id="bugEmail" placeholder="Contact Email Address (optional)" /><input type="button" id="bugSubmit" value="Submit"/>
</div>
<div id="comment_panel" class="allpanels">
<textarea id="commentDescription" placeholder="Type your question or comment to Lime Technology here.  We will respond by to the e-mail address associated with your registration key."></textarea>
<br>
<input type="email" id="commentEmail" placeholder="Contact Email Address (optional)" /><input type="button" id="commentSubmit" value="Submit"/>
</div>
<div id="spinner_image"><img src="/webGui/images/loading.gif"/></div>
<div id="footer_panel">
<hr>
<a href="https://lime-technology.com" target="_blank">Website</a>&nbsp;|&nbsp;
<a href="https://lime-technology.com/forum" target="_blank">Forum</a>&nbsp;|&nbsp;
<a href="https://lime-technology.com/wiki" target="_blank">Wiki</a>
</div>
</div>
<script type="text/javascript" src="/webGui/javascript/dynamix.js"></script>
<script>
var keyfile = '<?=$keyfile?>';
var unraid_osversion = '<?=$unraid['version']?>';
var unraid_timestamp = <?=time()?>;
var pageurl = window.top.location.href;

function onlinepoll_load() {
    $('#onlinepoll_panel').fadeOut('fast');
    $('#spinner_image').fadeIn('fast');

    $.post('https://keys.lime-technology.com/polls',{timestamp:unraid_timestamp,osversion:unraid_osversion,keyfile:keyfile},function(data) {
        $('#onlinepoll_panel').hide().html(data).fadeIn('fast');
    }).fail(function(data) {
        var msg = "<p>Sorry, an error ("+data.status+") occurred.  Please try again later.</p>";
        $('#onlinepoll_panel').hide().html(msg).fadeIn('fast');
    }).always(function() {
        $('#spinner_image').fadeOut('fast');
    });
}

function featurerequest_reset() {
    $('#featureDescription').val("");
    $('#featureEmail').val("");
}
function bugreport_reset() {
    $('#bugDescription').val("Bug Description:\n\nHow to reproduce:\n\nExpected results:\n\nActual results:\n\nOther information:\n\n");
    $('#bugEmail').val("");
}
function comment_reset() {
    $('#commentDescription').val("");
    $('#commentEmail').val("");
}

function form_submit(url, params, $panel, diagnostics) {
    $panel.find('textarea,input').prop('disabled', true);
    $('#spinner_image').fadeIn('fast');

    if (diagnostics) {
        $.get('/webGui/include/Feedback.php',{getdiagnostics:1},function(data) {
            params.diagnostics = data;
            form_submit(url, params, $panel);
        }).fail(function() {
            $('#spinner_image').fadeOut('fast');
            $panel.fadeOut('fast').find('textarea,input').prop('disabled', false);
            var failure_message = '<p class="red-text" style="text-align:center;">Sorry, an error (Unable to generate system diagnostics) occurred.  Please try again later.</p>';
            $('#thanks_panel').html(failure_message).fadeIn('fast');
        });

        return;
    }

    params.timestamp = unraid_timestamp;
    params.osversion = unraid_osversion;
    params.keyfile = keyfile;
    params.pageurl = pageurl;

    $.post(url,params,function(data) {
        if (data.error) {
            var failure_message = '<p class="red-text" style="text-align:center;">Sorry, an error ('+data.error+') occurred.  Please try again later.</p>';
            $('#thanks_panel').html(failure_message).fadeIn('fast');
        } else {
            data.message = data.message || '';

            var url_parts = url.split('/');
            var success_message = '<center><h2>Thank You!</h2><img src="/webGui/images/feedback_'+url_parts[4]+'.jpg"/><p style="text-align:center">'+data.message+'</p></center>';

            $('#thanks_panel').html(success_message).fadeIn('fast', function() {
                var resetfunction = window[url_parts[4]+'_reset'];
                if (typeof resetfunction !== 'undefined' && $.isFunction(resetfunction)) {
                    resetfunction();
                }
            });
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
            errorThrown = jqXHR.responseJSON.error;
        }
        var failure_message = '<p class="red-text" style="text-align:center;">Sorry, an error ('+errorThrown+') occurred.  Please try again later.</p>';
        $('#thanks_panel').html(failure_message).fadeIn('fast');
    }).always(function() {
        $('#spinner_image').fadeOut('fast');
        $panel.fadeOut('fast').find('textarea,input').prop('disabled', false);
    });
}

$(function() {
    $('#control_panel input[type=radio]').click(function() {
        var showPanel = '#'+$( "#control_panel input[type=radio]:checked" ).val()+'_panel';
        $('.allpanels').not(showPanel).fadeOut('fast');

        var loadfunction = window[$( "#control_panel input[type=radio]:checked" ).val()+'_load'];
        if (typeof loadfunction !== 'undefined' && $.isFunction(loadfunction)) {
            loadfunction();
        } else {
            $(showPanel).fadeIn('fast');
        }
    });

    $('#featureSubmit').click(function featureSubmitClick(){
        if ($('#featureDescription').val() === '') return;
        form_submit('https://keys.lime-technology.com/feedback/featurerequest',{description:$('#featureDescription').val(),email:$('#featureEmail').val()},$('#featurerequest_panel'));
    });

    $('#bugSubmit').click(function bugSubmitClick(){
        if ($('#bugDescription').val() === '') return;
        form_submit('https://keys.lime-technology.com/feedback/bugreport',{description:$('#bugDescription').val(),email:$('#bugEmail').val()},$('#bugreport_panel'),true); // attach diagnostics
    });

    $('#commentSubmit').click(function commentSubmitClick(){
        if ($('#commentDescription').val() === '') return;
        form_submit('https://keys.lime-technology.com/feedback/comment',{description:$('#commentDescription').val(),email:$('#commentEmail').val()},$('#comment_panel'));
    });

    featurerequest_reset();
    bugreport_reset();
    comment_reset();
    $('#optOnlinePoll').click();
});
</script>
</body>
