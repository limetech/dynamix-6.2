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
$keyfile = base64_encode(file_get_contents($var['regFILE']));
?>
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-white.css">
<script src="/webGui/javascript/dynamix.js"></script>
<script>
function replaceKey(email, guid, keyfile) {
  if (email.length) {
    var timestamp = <?=time()?>;
    $('#status_panel').slideUp('fast');
    $('#input_form').find('input').prop('disabled', true);
    // Nerds love spinners, Maybe place a spinner image next to the submit button; we'll show it now:
    $('#spinner_image').fadeIn('fast');

    $.post('https://keys.lime-technology.com/account/license/transfer',{timestamp:timestamp,guid:guid,email:email,keyfile:keyfile},function(data) {
        $('#spinner_image').fadeOut('fast');
        var msg = "<p>A registration replacement key has been created for USB Flash GUID <strong>"+guid+"</strong></p>" +
                  "<p>An email has been sent to <strong>"+email+"</strong> containing your key file URL." +
                  " When received, please paste the URL into the <i>Key file URL</i> box and" +
                  " click <i>Install Key</i>.</p>" +
                  "<p>If you do not receive an email, please check your spam or junk-email folder.</p>";

        $('#status_panel').hide().html(msg).slideDown('fast');
        $('#input_form').fadeOut('fast');
    }).fail(function(data) {
        $('#input_form').find('input').prop('disabled', false);
        $('#spinner_image').fadeOut('fast');
        var status = data.status;
        var obj = data.responseJSON;
        var msg = "<p>Sorry, an error ("+status+") occurred registering USB Flash GUID <strong>"+guid+"</strong><p>" +
                  "<p>The error is: "+obj.error+"</p>";

        $('#status_panel').hide().html(msg).slideDown('fast');
    });
  }
}
</script>
<body>
<div style="margin-top:20px;font-size:12px;line-height:30px;color:#303030;margin-left:40px;">
<div id="status_panel"></div>
<form markdown="1" id="input_form">

Email address: <input type="text" name="email" maxlength="1024" value="" style="width:33%">

<input type="button" value="Replace Key" onclick="replaceKey(this.form.email.value.trim(), '<?=$var['flashGUID']?>', '<?=$keyfile?>')">

</form>
</div>
</body>
