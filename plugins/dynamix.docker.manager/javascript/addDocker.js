var pathNum = 2;
var portNum = 0;
var varNum = 0;
var currentPath = "/mnt/";

if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) {
      return typeof args[number] != 'undefined' ? args[number] : match;
    });
  };
}

function rmTemplate(tmpl) {
	var name = tmpl.split(/[\/]+/).pop();
  swal({title:"Are you sure?",text:"Remove template: "+name,type:"warning",showCancelButton:true},function(){$("#rmTemplate").val(tmpl);$("#formTemplate").submit();});
}

function toggleBrowser(N) {
	var el = $('#fileTree' + N);
	if (el.is(':visible')) {
		hideBrowser(N);
	} else {
		$( el ).fileTree({
			root: currentPath,
			filter: 'HIDE_FILES_FILTER'
		},
		function(file) {},
		function(folder) {
			$("#hostPath" + N).val(folder);
		});
		$( el ).slideDown('fast');
	}
}

function hideBrowser(N) {
	$("#fileTree" + N).slideUp('fast', function () {
		$(this).html("");
	});
}

function addPort(frm) {
	portNum++;
	var hostPort = $("#hostPort1");
	var containerPort = $("#containerPort1");
	var portProtocol = $("#portProtocol1");

	var select = "";
	if (portProtocol.val() == "udp"){
		select = "selected";
	}

	var row = [
	'<tr id="portNum{0}" style="display: none;">',
		'<td>',
			'<input type="number" min="1" max="65535" name="containerPort[]" value="{2}" class="textPort" title="Set the port your app uses inside the container.">',
		'</td>',
		'<td>',
			'<input type="number" min="1" max="65535" name="hostPort[]" value="{1}" class="textPort" title="Set the port you use to interact with the app.">',
		'</td>',
		'<td>',
			'<select name="portProtocol[]">',
				'<option value="tcp">tcp</option>',
				'<option value="udp" {3}>udp</option>',
			'</select>',
		'</td>',
		'<td>',
			'<input type="button" value="Remove" onclick="removePort({0});">',
		'</td>',
	'</tr>'
	].join('').format(portNum, hostPort.val(), containerPort.val(), select);

	$(row).appendTo('#portRows').fadeIn("fast");
	hostPort.val('');
	containerPort.val('');
	portProtocol.val('tcp');
}

function removePort(rnum) {
	$('#portNum' + rnum).fadeOut("fast", function() { $(this).remove(); });
}

function addPath(frm) {
	pathNum++;
	var hostPath = $("#hostPath1");
	var containerPath = $("#containerPath1");
	var hostWritable = $("#hostWritable1");

	var select = "";
	if (hostWritable.val() == "ro"){
		select = "selected";
	}

	var row = [
	'<tr id="pathNum{0}" style="display: none;">',
		'<td>',
			'<input type="text" name="containerPath[]" value="{2}" class="textPath" onclick="hideBrowser({0});" title="The directory your app uses inside the container. Ex: /config">',
		'</td>',
		'<td>',
			'<input type="text" id="hostPath{0}" name="hostPath[]" value="{1}" class="textPath" onclick="toggleBrowser({0});" title="The directory in your array the app have access to. Ex: /mnt/user/Movies"/>',
			'<div id="fileTree{0}" class="fileTree"></div>',
		'</td>',
		'<td>',
			'<select name="hostWritable[]">',
				'<option value="rw">Read/Write</option>',
				'<option value="ro" {3}>Read Only</option>',
			'</select>',
		'</td>',
		'<td>',
			'<input type="button" value="Remove" onclick="removePath({0});"></td></tr>',
		'</td>',
	'</tr>'
	].join('').format(pathNum, hostPath.val(), containerPath.val(), select);

	$(row).appendTo('#pathRows tbody').fadeIn("fast");
	hostPath.val('');
	containerPath.val('');
	hostWritable.val('rw');
}

function removePath(rnum) {
	$('#pathNum' + rnum).fadeOut("fast", function() { $(this).remove(); });
}

function addEnv(frm) {
	varNum++;
	var VariableName = $("#VariableName1");
	var VariableValue = $("#VariableValue1");

	var row = [
	'<tr id="varNum{0}" style="display: none;">',
		'<td>',
			'<input type="text" name="VariableName[]" value="{1}" class="textEnv">',
		'</td>',
		'<td>',
			'<input type="text" name="VariableValue[]" value="{2}" class="textEnv">',
			'<input type="button" value="Remove" onclick="removeEnv({0});">',
		'</td>',
	'</tr>'
	].join('').format(varNum, VariableName.val(), VariableValue.val());

	$(row).appendTo('#envRows tbody').fadeIn("fast");
	VariableName.val('');
	VariableValue.val('');
}

function removeEnv(rnum) {
	$('#varNum' + rnum).fadeOut("fast", function() { $(this).remove(); });
}

function toggleMode(){
	$("#toggleMode").toggleClass("fa-toggle-off fa-toggle-on");
	$(".additionalFields").slideToggle();
}
