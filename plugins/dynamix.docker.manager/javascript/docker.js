var eventURL = "/plugins/dynamix.docker.manager/include/Events.php";

function addDockerContainerContext(container, image, template, started, update, autostart, webui, id){
  var opts = [{header: container, image: "/plugins/dynamix.docker.manager/images/dynamix.docker.manager.png"}];
  if (started && (webui != "#")) {
    opts.push({text: 'WebUI', icon:'fa-globe', href: webui, target: '_blank' });
    opts.push({divider: true});
  }
  if (! update){
    opts.push({text: 'Update', icon:'fa-arrow-down', action: function(e){ e.preventDefault(); execUpContainer(container); }});
    opts.push({divider: true});
  }
  if (started){
    opts.push({text: 'Stop', icon:'fa-stop', action: function(e){ e.preventDefault(); containerControl(id, 'stop', true); }});
    opts.push({text: 'Restart', icon:'fa-refresh', action: function(e){ e.preventDefault(); containerControl(id, 'restart', true); }});
  } else {
    opts.push({text: 'Start', icon:'fa-play', action: function(e){ e.preventDefault(); containerControl(id, 'start', true); }});
  }
  opts.push({divider: true});
  if (location.pathname.indexOf("/Dashboard") === 0) {
    opts.push({text: 'Logs', icon:'fa-navicon', action: function(e){ e.preventDefault(); containerLogs(container, id); }});
  }
  if (template) {
    opts.push({text: 'Edit', icon:'fa-wrench', action: function(e){ e.preventDefault(); editContainer(container, template); }});
  }
  opts.push({divider: true});
  opts.push({text: 'Remove', icon:'fa-trash', action: function(e){ e.preventDefault(); rmContainer(container, image, id); }});
  context.attach('#context-'+container, opts);
}

function addDockerImageContext(image, imageTag){
  var opts = [{header: '(orphan image)'}];
  opts.push({text: 'Remove', icon:'fa-trash', action: function(e){ e.preventDefault(); rmImage(image, imageTag); }});
  context.attach('#context-'+image, opts);
}

function execUpContainer(container){
  var title = 'Updating the container: ' + container;
  var address = "/plugins/dynamix.docker.manager/include/CreateDocker.php?updateContainer=true&ct[]=" + encodeURIComponent(container);
  popupWithIframe(title, address, true);
}

function popupWithIframe(title, cmd, reload) {
  pauseEvents();

  $( "#iframe-popup" ).html('<iframe id="myIframe" frameborder="0" scrolling="yes" width="100%" height="99%"></iframe>');
  $("#iframe-popup").dialog({
    autoOpen: true,
    title: title,
    draggable: true,
    width : 800,
    height : ((screen.height/5)*4)||0,
    resizable : true,
    modal : true,
    show : {effect: 'fade' , duration: 250},
    hide : {effect: 'fade' , duration: 250},
    open: function(ev, ui){
      $('#myIframe').attr('src', cmd);
    },
    close: function( event, ui ) {
      if (reload && !$('#myIframe').contents().find('#canvas').length){
        location = window.location.href;
      } else {
        resumeEvents();
      }
    }
  });
  $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
  $(".ui-dialog .ui-dialog-content").css('padding','0');
  $(".ui-dialog .ui-dialog-title").css('text-align','center');
  $(".ui-dialog .ui-dialog-title").css('width', "100%");
  //$('.ui-widget-overlay').click(function() { $("#iframe-popup").dialog("close"); });
}

function addContainer() {
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);

  location = path + '/AddContainer';
}

function editContainer(container, template) {
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);

  location = path + '/UpdateContainer?xmlTemplate=edit:' + template;
}

function rmContainer(container, image, id){
  var title = 'Removing container: '+ container;
  $( "#dialog-confirm" ).html('');
  $( "#dialog-confirm" ).append( "<br><span style='color: #E80000;'>Are you sure?</span>" );
  $( "#dialog-confirm" ).dialog({
    title: title,
    resizable: false,
    width: 500,
    modal: true,
    show : {effect: 'fade' , duration: 250},
    hide : {effect: 'fade' , duration: 250},
    buttons: {
      "Just the container": function() {
        $( this ).dialog( "close" );
        containerControl(id, 'remove_container', true);
      },
      "Container and image": function() {
        $( this ).dialog( "close" );
        containerControl(id, 'remove_container', false);
        imageControl(image, "remove_image", true);
      },
      Cancel: function() {
        $( this ).dialog( "close" );
        $( this ).html("");
      }
    }
  });
  $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
  $(".ui-dialog .ui-dialog-title").css('text-align','center').css( 'width', "100%");
  $(".ui-dialog .ui-dialog-content").css('padding-top','15px').css('font-weight','bold');
  $(".ui-button-text").css('padding','0px 5px');
}

function updateContainer(container){
  var ctCmd = "&ct[]=" + encodeURIComponent(container);
  var ctTitle = "";

  var title = 'Updating container: '+container;
  $( "#dialog-confirm" ).html('');
  $( "#dialog-confirm" ).append( "<br><span style='color: #E80000;'>Are you sure?</span>" );
  $( "#dialog-confirm" ).dialog({
    title: title,
    resizable: false,
    width: 500,
    modal: true,
    show : {effect: 'fade' , duration: 250},
    hide : {effect: 'fade' , duration: 250},
    buttons: {
      "Just do it!": function() {
        $( this ).dialog( "close" );
        var cmd = "/plugins/dynamix.docker.manager/include/CreateDocker.php?updateContainer=true" + ctCmd;
        popupWithIframe(title, cmd, true);
      },
      Cancel: function() {
        $( this ).dialog( "close" );
      }
    }
  });
  $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
  $(".ui-dialog .ui-dialog-title").css('text-align','center');
  $(".ui-dialog .ui-dialog-content").css('padding-top','15px');
  $(".ui-dialog .ui-dialog-content").css('font-weight','bold');
  $(".ui-button-text").css('padding','0px 5px');
  $( ".ui-dialog .ui-dialog-title" ).css( 'width', "100%");
}

function rmImage(image, imageName){
  var imageName = $('<textarea />').html(imageName).text();
  var title = "Removing image: "+imageName;
  $( "#dialog-confirm" ).html('');
  $( "#dialog-confirm" ).append( "<br><span style='color: #E80000;'>Are you sure?</span>" );
  $( "#dialog-confirm" ).dialog({
    title: title,
    dialogClass: "alert",
    resizable: false,
    width: 500,
    modal: true,
    show : {effect: 'fade' , duration: 250},
    hide : {effect: 'fade' , duration: 250},
    buttons: {
      "Just do it!": function() {
        $( this ).dialog( "close" );
        imageControl(image, "remove_image", true);
      },
      Cancel: function() {
        $( this ).dialog( "close" );
      }
    }
  });
  $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
  $(".ui-dialog .ui-dialog-title").css('text-align','center');
  $(".ui-dialog .ui-dialog-content").css('padding-top','15px');
  $(".ui-dialog .ui-dialog-content").css('font-weight','bold');
  $(".ui-button-text").css('padding','0px 5px');
  $( ".ui-dialog .ui-dialog-title" ).css( 'width', "100%");
}

function imageControl(image, action, reload){
  if (typeof reload == undefined) reload = true
  $.post(eventURL,{action:action, image:image},function(data){
    if(data.success === true) {
      if (reload) location.reload();
    } else {
      alert("Error:\n\n"+data.success);
    }},"json");
}

function containerControl(container, action, reload){
  if (typeof reload == undefined) reload = true
  $.post(eventURL,{action:action, container:container},function(data){
    if(data.success === true) {
      if (reload) location.reload();
    } else {
      alert("Error:\n\n"+data.success);
    }},"json");
}

function reloadUpdate(){
  $(".updatecolumn").html("<span style=\"color:#267CA8;white-space:nowrap;\"><i class=\"fa fa-spin fa-refresh\"></i> checking...</span>");
  $("#cmdStartStop").val("/plugins/dynamix.docker.manager/scripts/dockerupdate.php");
  $("#cmdArg1").remove();
  $("#cmdArg2").remove();
  $("#formStartStop").submit();
}

function autoStart(container, event){
  document.getElementsByName("container")[0].value = container;
  $("#formStartStop").submit();
}

function containerLogs(container, id){
  var height = 600;
  var width = 900;
  var run = eventURL+'?action=log&container='+id+'&title=Log for: '+container;
  var top = (screen.height-height)/2;
  var left = (screen.width-width)/2;
  var options = 'resizeable=yes,scrollbars=yes,height='+height+',width='+width+',top='+top+',left='+left;
  window.open(run, 'log', options);
}

