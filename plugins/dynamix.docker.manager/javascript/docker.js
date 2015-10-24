function addDockerContainerContext(container, image, template, started, update, autostart, webui){
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
    opts.push({text: 'Stop', icon:'fa-stop', action: function(e){ e.preventDefault(); containerControl(container, 'stop'); }});
    opts.push({text: 'Restart', icon:'fa-refresh', action: function(e){ e.preventDefault(); containerControl(container, 'restart'); }});
  } else {
    opts.push({text: 'Start', icon:'fa-play', action: function(e){ e.preventDefault(); containerControl(container, 'start'); }});
  }
  opts.push({divider: true});
  if (location.pathname.indexOf("/Dashboard") === 0) {
    opts.push({text: 'Logs', icon:'fa-navicon', action: function(e){ e.preventDefault(); containerLogs(container); }});
  }
  if (template) {
    opts.push({text: 'Edit', icon:'fa-wrench', action: function(e){ e.preventDefault(); editContainer(container, template); }});
  }
  opts.push({divider: true});
  opts.push({text: 'Remove', icon:'fa-trash', action: function(e){ e.preventDefault(); rmContainer(container, image); }});
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

function rmContainer(containers, images){
  var ctCmd = "/plugins/dynamix.docker.manager/scripts/docker rm -f";
  var imgCmd = "/plugins/dynamix.docker.manager/scripts/docker rmi";
  var ctTitle = "";
  if (typeof containers === "object") {
    for (var i = 0; i < containers.length; i++) {
      ctCmd  += " " + containers[i];
      imgCmd += " " + images[i];
      ctTitle += containers[i] + "<br>";
    }
  } else {
    ctCmd += " " + containers;
    imgCmd += " " + images;
    ctTitle += containers + "<br>";
  }
  var title = 'Removing container';
  $( "#dialog-confirm" ).html(ctTitle);
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
        var cmd = '/plugins/dynamix.docker.manager/include/Exec.php?cmd=' + encodeURIComponent(ctCmd);
        popupWithIframe(title, cmd, true);
      },
      "Container and image": function() {
        $( this ).dialog( "close" );
        var cmd = '/plugins/dynamix.docker.manager/include/Exec.php?cmd=' + encodeURIComponent(ctCmd + ";" + imgCmd);
        popupWithIframe(title, cmd, true);
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

function updateContainer(containers){
  var ctCmd ="";
  var ctTitle = "";
  if (typeof containers === "object") {
    for (var i = 0; i < containers.length; i++) {
      ctCmd  += "&ct[]=" + encodeURIComponent(containers[i]);
      ctTitle += containers[i] + "<br>";
    }
  } else {
    ctCmd += "&ct[]=" + encodeURIComponent(containers);
    ctTitle += containers + "<br>";
  }
  var title = 'Updating container';
  $( "#dialog-confirm" ).html(ctTitle);
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

function rmImage(images, imageName){
  var imgCmd   = "/plugins/dynamix.docker.manager/scripts/docker rmi";
  var imgTitle = "";
  if (typeof images === "object") {
    for (var i = 0; i < images.length; i++) {
      imgCmd += " " + images[i];
      imgTitle += imageName[i] + "<br>";
    }
  } else {
    imgCmd += " " + images;
    imgTitle += imageName + "<br>";
  }
  var title = "Removing image";
  $( "#dialog-confirm" ).html(imgTitle);
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
        var cmd = '/plugins/dynamix.docker.manager/include/Exec.php?cmd=' + encodeURIComponent(imgCmd);
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

function containerControl(container, action){
  $("#cmdStartStop").val("/plugins/dynamix.docker.manager/scripts/docker");
  $("#cmdArg1").val(action);
  $("#cmdArg2").val(container);
  $("#formStartStop").submit();
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

function containerLogs(container){
  openWindow('/plugins/dynamix.docker.manager/scripts/docker&arg1=logs&arg2=--tail=350&arg3=-f&arg4=' + container, 'Log for: ' + container, 600, 900);
}
