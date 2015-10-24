<?PHP
/* Copyright 2015, Lime Technology
 * Copyright 2015, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
ignore_user_abort(true);
require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");
$DockerClient = new DockerClient();
$DockerUpdate = new DockerUpdate();
$DockerTemplates = new DockerTemplates();

function prepareDir($dir){
  if (strlen($dir)){
    if ( ! is_dir($dir) && ! is_file($dir)){
      mkdir($dir, 0777, true);
      chown($dir, 'nobody');
      chgrp($dir, 'users');
      sleep(1);
    }
  }
}

function ContainerExist($container){
  global $DockerClient;

  $all_containers = $DockerClient->getDockerContainers();
  if ( ! $all_containers) { return FALSE; }
  foreach ($all_containers as $ct) {
    if ($ct['Name'] == $container){
      return True;
      break;
    }
  }
  return False;
}

function trimLine($text){
  return preg_replace("/([\n^])[\s]+/", '$1', $text);
}

function pullImage($image) {
  if (! preg_match("/:[\w]*$/i", $image)) $image .= ":latest";
  readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
  echo '<script>function add_to_id(m){$(".id:last").append(" "+m);}</script>';
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Pulling image: " . $image . "</legend><p class=\"logLine\" id=\"logBody\"></p></fieldset>');</script>";
  @flush();

  $fp = stream_socket_client('unix:///var/run/docker.sock', $errno, $errstr);
  if ($fp === false) {
    echo "Couldn't create socket: [$errno] $errstr";
    return NULL;
  }
  $out="POST /images/create?fromImage=$image HTTP/1.1\r\nConnection: Close\r\n\r\n";
  fwrite($fp, $out);
  $cid = "";
  $cstatus="";
  $alltotals = [];
  while (!feof($fp)) {
    $cnt =  json_decode( fgets($fp, 5000), TRUE );
    $id = ( isset( $cnt['id'] )) ? $cnt['id'] : "";
    if ($id != $cid && strlen($id)) {
      $cid = $id;
      $cstatus = "";
      echo "<script>addLog('IMAGE ID [". $id ."]: <span class=\"id\"></span>');</script>";
      @flush();
    }
    $status = ( isset( $cnt['status'] )) ? $cnt['status'] : "";
    if ($status != $cstatus && strlen($status)) {
      if ( isset($cnt['progressDetail']['total']) && $cnt['progressDetail']['total'] > 0 ) {
        $alltotals[$cnt['id']] = $cnt['progressDetail']['total'];
      }
      $cstatus = $status;
      echo "<script>add_to_id('". $status ."<span class=\"progress\"></span>.');</script>";
      @flush();
    }
    if ($status == "Downloading") {
      $total = $cnt['progressDetail']['total'];
      $current = $cnt['progressDetail']['current'];
      $alltotals[$cnt['id']] = $cnt['progressDetail']['current'];
      if ($total > 0) {
        $percentage = round(($current/$total) * 100);
        echo "<script>show_Prog(' ". $percentage ."% of " . sizeToHuman($total) . "');</script>\n";
      } else {
        // Docker must not know the total download size (http-chunked or something?)
        //  just show the current download progress without the percentage
        echo "<script>show_Prog(' " . sizeToHuman($current) . "');</script>\n";
      }
      @flush();
    }
  }
  echo "<script>addLog('<br><b>TOTAL DATA PULLED:</b> " . sizeToHuman(array_sum($alltotals)) . "<span class=\"progress\"></span>');</script>\n";
}

function sizeToHuman($size) {
  $units = ['B','KB','MB','GB'];
  $unitsIndex = 0;
  while ($size > 1024 && (($unitsIndex+1) < count($units))) {
    $size /= 1024;
    $unitsIndex++;
  }
  return ceil($size) . " " . $units[$unitsIndex];
}

function xmlToCommand($xmlFile){
  global $var;
    $doc = new DOMDocument();
    $doc->loadXML($xmlFile);

  $Name          = $doc->getElementsByTagName( "Name" )->item(0)->nodeValue;
  $cmdName       = (strlen($Name)) ? '--name="' . $Name . '"' : "";
  $Privileged    = $doc->getElementsByTagName( "Privileged" )->item(0)->nodeValue;
  $cmdPrivileged = (strtolower($Privileged) == 'true') ?  '--privileged="true"' : "";
  $Repository    = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
  $Mode          = $doc->getElementsByTagName( "Mode" )->item(0)->nodeValue;
  $cmdMode       = '--net="'.strtolower($Mode).'"';
  $BindTime      = $doc->getElementsByTagName( "BindTime" )->item(0)->nodeValue;
  // $cmdBindTime   = (strtolower($BindTime) == "true") ? '"/etc/localtime":"/etc/localtime":ro' : '';
  $cmdBindTime   = (strtolower($BindTime) == "true") ? 'TZ="' . $var['timeZone'] . '"' : '';

  $Ports = array('');
  foreach($doc->getElementsByTagName('Port') as $port){
    $ContainerPort = $port->getElementsByTagName( "ContainerPort" )->item(0)->nodeValue;
    if (! strlen($ContainerPort)){ continue; }
    $HostPort      = $port->getElementsByTagName( "HostPort" )->item(0)->nodeValue;
    $Protocol      = $port->getElementsByTagName( "Protocol" )->item(0)->nodeValue;
    $Ports[]       = sprintf("%s:%s/%s", $HostPort, $ContainerPort, $Protocol);
  }

  $Volumes = array('');
  foreach($doc->getElementsByTagName('Volume') as $volume){
    $ContainerDir = $volume->getElementsByTagName( "ContainerDir" )->item(0)->nodeValue;
    if (! strlen($ContainerDir)){ continue; }
    $HostDir      = $volume->getElementsByTagName( "HostDir" )->item(0)->nodeValue;
    $DirMode      = $volume->getElementsByTagName( "Mode" )->item(0)->nodeValue;
    $Volumes[]    = sprintf( '"%s":"%s":%s', $HostDir, $ContainerDir, $DirMode);
  }

  // if (strlen($cmdBindTime)) {
  //   $Volumes[] = $cmdBindTime;
  // }

  $Variables = array('');
  foreach($doc->getElementsByTagName('Variable') as $variable){
    $VariableName  = $variable->getElementsByTagName( "Name" )->item(0)->nodeValue;
    if (! strlen($VariableName)){ continue; }
    $VariableValue = $variable->getElementsByTagName( "Value" )->item(0)->nodeValue;
    $Variables[]   = sprintf('%s="%s"', $VariableName, $VariableValue);
  }

  if (strlen($cmdBindTime)) {
    $Variables[] = $cmdBindTime;
  }

  $templateExtraParams = '';
  if ( $doc->getElementsByTagName( "ExtraParams" )->length > 0 ) {
    $templateExtraParams = $doc->getElementsByTagName( "ExtraParams" )->item(0)->nodeValue;
  }

  $cmd = sprintf('/plugins/dynamix.docker.manager/scripts/docker run -d %s %s %s %s %s %s %s %s', $cmdName, $cmdMode, $cmdPrivileged, implode(' -e ', $Variables),
       implode(' -p ', $Ports), implode(' -v ', $Volumes), $templateExtraParams, $Repository);
  $cmd = preg_replace('/\s+/', ' ', $cmd);

  return array($cmd, $Name, $Repository);
}

function addElement($doc, $el, $elName, $elVal){
  $node = $el->appendChild($doc->createElement($elName));
  $node->appendChild($doc->createTextNode($elVal));
  return $node;
}

function postToXML($post, $setOwnership = FALSE){
  global $DockerUpdate;
  $doc = new DOMDocument('1.0', 'utf-8');
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $root = $doc->createElement('Container');
  $root = $doc->appendChild($root);

  $docName       = $root->appendChild($doc->createElement('Name'));
  if ( isset( $post[ 'Description' ] )) addElement($doc, $root, 'Description', $post[ 'Description' ]);
  if ( isset( $post[ 'Registry' ] ))    addElement($doc, $root, 'Registry', $post[ 'Registry' ]);
  $docRepository = $root->appendChild($doc->createElement('Repository'));
  $BindTime      = $root->appendChild($doc->createElement('BindTime'));
  $Privileged    = $root->appendChild($doc->createElement('Privileged'));
  $Environment   = $root->appendChild($doc->createElement('Environment'));
  $docNetworking = $root->appendChild($doc->createElement('Networking'));
  $Data          = $root->appendChild($doc->createElement('Data'));
  $Version       = $root->appendChild($doc->createElement('Version'));
  $Mode          = $docNetworking->appendChild($doc->createElement('Mode'));
  $Publish       = $docNetworking->appendChild($doc->createElement('Publish'));
  $Name          = preg_replace('/\s+/', '', $post["containerName"]);

  // Editor Values
  if ( isset( $post[ 'WebUI' ] ))  addElement($doc, $root, 'WebUI', $post[ 'WebUI' ]);
  if ( isset( $post[ 'Banner' ] )) addElement($doc, $root, 'Banner', $post[ 'Banner' ]);
  if ( isset( $post[ 'Icon' ] ))   addElement($doc, $root, 'Icon', $post[ 'Icon' ]);

  if ( isset( $post[ 'ExtraParams' ] ))   addElement($doc, $root, 'ExtraParams', $post[ 'ExtraParams' ]);

  $docName->appendChild($doc->createTextNode($Name));
  $docRepository->appendChild($doc->createTextNode($post["Repository"]));
  $BindTime->appendChild($doc->createTextNode((strtolower($post["BindTime"])     == 'on') ? 'true' : 'false'));
  $Privileged->appendChild($doc->createTextNode((strtolower($post["Privileged"]) == 'on') ? 'true' : 'false'));
  $Mode->appendChild($doc->createTextNode(strtolower($post["NetworkType"])));

  for ($i = 0; $i < count($post["hostPort"]); $i++){
    if (! strlen($post["containerPort"][$i])) { continue; }
    $protocol      = $post["portProtocol"][$i];
    $Port          = $Publish->appendChild($doc->createElement('Port'));
    $HostPort      = $Port->appendChild($doc->createElement('HostPort'));
    $ContainerPort = $Port->appendChild($doc->createElement('ContainerPort'));
    $Protocol      = $Port->appendChild($doc->createElement('Protocol'));
    $HostPort->appendChild($doc->createTextNode(trim($post["hostPort"][$i])));
    $ContainerPort->appendChild($doc->createTextNode($post["containerPort"][$i]));
    $Protocol->appendChild($doc->createTextNode($protocol));
  }

  for ($i = 0; $i < count($post["VariableName"]); $i++){
    if (! strlen($post["VariableName"][$i])) { continue; }
    $Variable      = $Environment->appendChild($doc->createElement('Variable'));
    $VariableName  = $Variable->appendChild($doc->createElement('Name'));
    $VariableValue = $Variable->appendChild($doc->createElement('Value'));
    $VariableName->appendChild($doc->createTextNode(trim($post["VariableName"][$i])));
    $VariableValue->appendChild($doc->createTextNode(trim($post["VariableValue"][$i])));
  }

  for ($i = 0; $i < count($post["hostPath"]); $i++){
    if (! strlen($post["hostPath"][$i])) { continue; }
    if (! strlen($post["containerPath"][$i])) { continue; }
    $tmpMode = $post["hostWritable"][$i];
    if ($setOwnership){
      prepareDir($post["hostPath"][$i]);
    }
    $Volume       = $Data->appendChild($doc->createElement('Volume'));
    $HostDir      = $Volume->appendChild($doc->createElement('HostDir'));
    $ContainerDir = $Volume->appendChild($doc->createElement('ContainerDir'));
    $DirMode      = $Volume->appendChild($doc->createElement('Mode'));
    $HostDir->appendChild($doc->createTextNode($post["hostPath"][$i]));
    $ContainerDir->appendChild($doc->createTextNode($post["containerPath"][$i]));
    $DirMode->appendChild($doc->createTextNode($tmpMode));
  }

  $currentVersion = $DockerUpdate->getRemoteVersion($post["Registry"], $post["Repository"]);
  $Version->appendChild($doc->createTextNode($currentVersion));

  return $doc->saveXML();
}

if ($_POST){

  $postXML = postToXML($_POST, TRUE);

  // Get the command line
  list($cmd, $Name, $Repository) = xmlToCommand($postXML);

  // Saving the generated configuration file.
  $userTmplDir = $dockerManPaths['templates-user'];
  if(is_dir($userTmplDir) === FALSE){
    mkdir($userTmplDir, 0777, true);
  }

  if(strlen($Name)) {
    $filename = sprintf('%s/my-%s.xml', $userTmplDir, $Name);
    file_put_contents($filename, $postXML);
  }

  // Pull image
  pullImage($Repository);

  // Remove existing container
  if (ContainerExist($Name)){
    $_GET['cmd'] = "/plugins/dynamix.docker.manager/scripts/docker rm -f $Name";
    include($dockerManPaths['plugin'] . "/include/Exec.php");
  }

  // Remove old container if renamed
  $existing = isset($_POST['existingContainer']) ? $_POST['existingContainer'] : FALSE;
  if ($existing && ContainerExist($existing)){
    $_GET['cmd'] = "/plugins/dynamix.docker.manager/scripts/docker rm -f $existing";
    include($dockerManPaths['plugin'] . "/include/Exec.php");
  }

  // Injecting the command in $_GET variable and executing.
  $_GET['cmd'] = $cmd;
  include($dockerManPaths['plugin'] . "/include/Exec.php");

  $DockerTemplates->removeInfo($Name);
  $DockerUpdate->syncVersions($Name);

  echo '<center><input type="button" value="Done" onclick="done()"></center><br>';
  die();
}


if ($_GET['updateContainer']){
  foreach ($_GET['ct'] as $value) {
    $Name = urldecode($value);
    $tmpl = $DockerTemplates->getUserTemplate($Name);

    if (! $tmpl){
      echo 'Configuration not found. Was this container created using this plugin?';
      continue;
    }

    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->preserveWhiteSpace = false;
    $doc->load( $tmpl );
    $doc->formatOutput = TRUE;

    $Repository = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
    $Registry = $doc->getElementsByTagName( "Registry" )->item(0)->nodeValue;

    readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
    echo "<script>addLog('<p>Preparing to update: " . $Repository . "</p>');</script>";
    @flush();

    $CurrentVersion = $DockerUpdate->getRemoteVersion($Registry, $Repository);

    if ($CurrentVersion){
      if ( $doc->getElementsByTagName( "Version" )->length == 0 ) {
        $root    = $doc->getElementsByTagName( "Container" )->item(0);
        $Version = $root->appendChild($doc->createElement('Version'));
      } else {
        $Version = $doc->getElementsByTagName( "Version" )->item(0);
      }
      $Version->nodeValue = $CurrentVersion;

      file_put_contents($tmpl, $doc->saveXML());
    }

    $oldContainerID = $DockerClient->getImageID($Repository);
    list($cmd, $Name, $Repository) = xmlToCommand($doc->saveXML());

    // Pull image
    flush();
    pullImage($Repository);

    $_GET['cmd'] = "/plugins/dynamix.docker.manager/scripts/docker rm -f $Name";
    include($dockerManPaths['plugin'] . "/include/Exec.php");

    $_GET['cmd'] = $cmd;
    include($dockerManPaths['plugin'] . "/include/Exec.php");

    $DockerTemplates->removeInfo($Name);
    $newContainerID = $DockerClient->getImageID($Repository);
    if ( $oldContainerID and $oldContainerID != $newContainerID){
      $_GET['cmd'] = sprintf("/plugins/dynamix.docker.manager/scripts/docker rmi %s", $oldContainerID);
      include($dockerManPaths['plugin'] . "/include/Exec.php");
    }

    $DockerTemplates->removeInfo($Name);
    $DockerUpdate->syncVersions($Name);
  }

  echo '<center><input type="button" value="Done" onclick="window.parent.jQuery(\'#iframe-popup\').dialog(\'close\');"></center><br>';
  die();
}


if($_GET['rmTemplate']){
  unlink($_GET['rmTemplate']);
}

if($_GET['xmlTemplate']){
  list($xmlType, $xmlTemplate) = split(':', urldecode($_GET['xmlTemplate']));
  if(is_file($xmlTemplate)){
    $doc = new DOMDocument();
    $doc->load($xmlTemplate);

    $templateRepository   = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
    $templateName         = $doc->getElementsByTagName( "Name" )->item(0)->nodeValue;
    $Registry             = $doc->getElementsByTagName( "Registry" )->item(0)->nodeValue;
    $templatePrivileged   = (strtolower($doc->getElementsByTagName( "Privileged" )->item(0)->nodeValue) == 'true') ? 'checked' : "";
    $templateMode         = $doc->getElementsByTagName( "Mode" )->item(0)->nodeValue;;
    $readonly             = ($xmlType == 'default') ? 'readonly="readonly"' : '';
    $required             = ($xmlType == 'default') ? 'required' : '';
    $disabled             = ($xmlType == 'default') ? 'disabled="disabled"' : '';

    if ( $doc->getElementsByTagName( "Description" )->length > 0 ) {
      $templateDescription = $doc->getElementsByTagName( "Description" )->item(0)->nodeValue;
    } else {
      $templateDescription = $DockerTemplates->getTemplateValue($templateRepository, "Description", "default");
    }

    if ( $doc->getElementsByTagName( "Registry" )->length > 0 ) {
      $templateRegistry = $doc->getElementsByTagName( "Registry" )->item(0)->nodeValue;
    } else {
      $templateRegistry = $DockerTemplates->getTemplateValue($templateRepository, "Registry", "default");
    }

    if ( $doc->getElementsByTagName( "WebUI" )->length > 0 ) {
      $templateWebUI = $doc->getElementsByTagName( "WebUI" )->item(0)->nodeValue;
    } else {
      $templateWebUI = $DockerTemplates->getTemplateValue($templateRepository, "WebUI", "default");
    }

    if ( $doc->getElementsByTagName( "Banner" )->length > 0 ) {
      $templateBanner = $doc->getElementsByTagName( "Banner" )->item(0)->nodeValue;
    } else {
      $templateBanner = $DockerTemplates->getTemplateValue($templateRepository, "Banner", "default");
    }

    if ( $doc->getElementsByTagName( "Icon" )->length > 0 ) {
      $templateIcon = $doc->getElementsByTagName( "Icon" )->item(0)->nodeValue;
    } else {
      $templateIcon = $DockerTemplates->getTemplateValue($templateRepository, "Icon", "default");
    }

    if ( $doc->getElementsByTagName( "ExtraParams" )->length > 0 ) {
      $templateExtraParams = $doc->getElementsByTagName( "ExtraParams" )->item(0)->nodeValue;
    } else {
      $templateExtraParams = $DockerTemplates->getTemplateValue($templateRepository, "ExtraParams", "default");
    }

    $templateDescription = stripslashes($templateDescription);
    $templateRegistry    = stripslashes($templateRegistry);
    $templateWebUI       = stripslashes($templateWebUI);
    $templateBanner      = stripslashes($templateBanner);
    $templateIcon        = stripslashes($templateIcon);
    $templateExtraParams = stripslashes($templateExtraParams);

    $templateDescBox      = preg_replace('/\[/', '<', $templateDescription);
    $templateDescBox      = preg_replace('/\]/', '>', $templateDescBox);

    $templatePorts = '';
    $row = '
    <tr id="portNum%s">
      <td>
        <input type="number" min="1" max="65535" name="containerPort[]" value="%s" class="textPort" %s title="Set the port your app uses inside the container."/>
      </td>
      <td>
        <input type="number" min="1" max="65535" name="hostPort[]" value="%s" class="textPort" %s title="Set the port you use to interact with the app."/>
      </td>
      <td>
        <select name="portProtocol[]" class="narrow">
          <option value="tcp">TCP</option>
          <option value="udp" %s>UDP</option>
        </select>
      </td>
      <td>
        <input type="button" value="Remove" onclick="removePort(%s);" %s/>
      </td>
    </tr>';

    $i = 1;
    foreach($doc->getElementsByTagName('Port') as $port){
      $j = $i + 100;
      $ContainerPort  = $port->getElementsByTagName( "ContainerPort" )->item(0)->nodeValue;
      if (! strlen($ContainerPort)){ continue; }
      $HostPort       = $port->getElementsByTagName( "HostPort" )->item(0)->nodeValue;
      $Protocol       = $port->getElementsByTagName( "Protocol" )->item(0)->nodeValue;
      $select = ($Protocol == 'udp') ? 'selected' : '';
      $templatePorts .= sprintf($row, $j, htmlspecialchars($ContainerPort), $readonly, htmlspecialchars($HostPort), $required, $select, $j, $disabled);
      $i++;
    }

    $templateVolumes = '';
    $row = '
    <tr id="pathNum%s">
      <td>
        <input type="text" name="containerPath[]" value="%s" class="textPath" onclick="hideBrowser(%s);" %s title="The directory your app uses inside the container. Ex: /config"/>
      </td>
      <td>
        <input type="text" id="hostPath%s" name="hostPath[]" value="%s" class="textPath" onclick="toggleBrowser(%s);" %s title="The directory in your array the app have access to. Ex: /mnt/user/Movies"/>
        <div id="fileTree%s" class="textarea fileTree"></div>
      </td>
      <td>
        <select name="hostWritable[]">
          <option value="rw">Read/Write</option>
          <option value="ro" %s>Read Only</option>
        </select>
      </td>
      <td>
        <input type="button" value="Remove" onclick="removePath(%s);" %s/>
      </td>
    </tr>';

    $i = 1;
    foreach($doc->getElementsByTagName('Volume') as $volume){
      $j = $i + 100;
      $ContainerDir     = $volume->getElementsByTagName( "ContainerDir" )->item(0)->nodeValue;
      if (! strlen($ContainerDir)){ continue; }
      $HostDir          = $volume->getElementsByTagName( "HostDir" )->item(0)->nodeValue;
      $Mode             = $volume->getElementsByTagName( "Mode" )->item(0)->nodeValue;
      $Mode             = ($Mode == "ro") ? "selected" : '';
      $templateVolumes .= sprintf($row, $j, htmlspecialchars($ContainerDir), $j, $readonly, $j, htmlspecialchars($HostDir), $j, $required, $j, $Mode, $j, $disabled);
      $i++;
    }

    $templateVariables = '';
    $row = '
    <tr id="varNum%s">
      <td>
        <input type="text" name="VariableName[]" value="%s" class="textEnv" %s/>
      </td>
      <td>
        <input type="text" name="VariableValue[]" value="%s" class="textEnv" %s/>
        <input type="button" value="Remove" onclick="removeEnv(%s);" %s/>
      </td>
    </tr>';

    $i = 1;
    foreach($doc->getElementsByTagName('Variable') as $variable){
      $j = $i + 100;
      $VariableName       = $variable->getElementsByTagName( "Name" )->item(0)->nodeValue;
      if (! strlen($VariableName)){ continue; }
      $VariableValue      = $variable->getElementsByTagName( "Value" )->item(0)->nodeValue;
      $templateVariables .= sprintf($row, $j, htmlspecialchars($VariableName), $readonly, htmlspecialchars($VariableValue), $required, $j, $disabled);
      $i++;
    }
  }
}

$showAdditionalInfo = true;
?>

<link type="text/css" rel="stylesheet" href="/webGui/styles/font-awesome.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.filetree.css" media="screen">
<style>
body{-webkit-overflow-scrolling:touch;}
.fileTree{width:240px;height:150px;overflow:scroll;position:absolute;z-index:100;display:none;}
#TemplateSelect{width:255px;}
option.list{padding:0 0 0 7px;font-size:11px;}
optgroup.bold{font-weight:bold;font-size:12px;margin-top:5px;}
optgroup.title{background-color:#625D5D;color:#FFFFFF;text-align:center;margin-top:10px;}
input.textPath{width:240px;}
input.textTemplate,textarea.textTemplate{width:555px;}
input.textEnv{width:230px;}
input.textPort{width:100px;}
table.pathTab{width:700px;}
table.portRows{width:400px;}
table.envTab{width:620px;}
table.Preferences{width:100%;}
.show{display:block;}
table td{font-size:14px;vertical-align:bottom;text-align:left;}
.inline_help{font-size:12px;}
.desc{padding:6px;line-height:15px;width:inherit;}
.toggleMode{cursor:pointer;color:#a3a3a3;letter-spacing:0;padding:0;padding-right:10px;font-family:arimo;font-size:12px;line-height:1.3em;font-weight:bold;margin:0;}
.toggleMode:hover,.toggleMode:focus,.toggleMode:active,.toggleMode .active{color:#625D5D;}
</style>
<form method="GET" id="formTemplate">
  <input type="hidden" id="xmlTemplate" name="xmlTemplate" value="" />
  <input type="hidden" id="rmTemplate" name="rmTemplate" value="" />
</form>

<div id="canvas" style="z-index:1;">
  <form method="post" id="createContainer">
    <table class="Preferences">
      <? if($xmlType == "edit"):
        if (ContainerExist($templateName)): echo "<input type='hidden' name='existingContainer' value='${templateName}'>\n"; endif;
      else:?>
      <tr>
        <td style="width: 150px;">Template:</td>
        <td >
          <select id="TemplateSelect" size="1">
            <option value="">Select a template</option>
            <?
            $rmadd = '';
            $all_templates = array();
            $all_templates['user'] = $DockerTemplates->getTemplates("user");
            $all_templates['default'] = $DockerTemplates->getTemplates("default");
            foreach ($all_templates as $key => $templates) {
              if ($key == "default") $title = "Default templates";
              if ($key == "user") $title = "User defined templates";
              printf("\t\t\t\t\t<optgroup class=\"title bold\" label=\"[ %s ]\"></optgroup>\n", htmlspecialchars($title));
              $prefix = '';
              foreach ($templates as $value){
                if ($value["prefix"] != $prefix) {
                  if ($prefix != '') {
                    printf("\t\t\t\t\t</optgroup>\n");
                  }
                  $prefix = $value["prefix"];
                  printf("\t\t\t\t\t<optgroup class=\"bold\" label=\"[ %s ]\">\n", htmlspecialchars($prefix));
                }
                //$value['name'] = str_replace("my-", '', $value['name']);
                $selected = (isset($xmlTemplate) && $value['path'] == $xmlTemplate) ? ' selected ' : '';
                if ($selected && ($key == "default")) $showAdditionalInfo = false;
                if (strlen($selected) && $key == 'user' ){ $rmadd = $value['path']; }
                printf("\t\t\t\t\t\t<option class=\"list\" value=\"%s:%s\" {$selected} >%s</option>\n", htmlspecialchars($key), htmlspecialchars($value['path']), htmlspecialchars($value['name']));
              }
              printf("\t\t\t\t\t</optgroup>\n");
            }
            ?>
          </select>
          <? if (!empty($rmadd)) {
            echo "<a onclick=\"rmTemplate('" . addslashes($rmadd) . "');\" style=\"cursor:pointer;\"><img src=\"/plugins/dynamix.docker.manager/images/remove.png\" title=\"" . htmlspecialchars($rmadd) . "\" width=\"30px\"></a>";
          }?>

        </td>
      </tr>
      <tr class="inline_help" style="display: none">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Templates are a quicker way to setting up Docker Containers on your unRAID server.  There are two types of templates:</p>

            <p>
              <b>Default templates</b><br>
              When valid repositories are added to your Docker Repositories page, they will appear in a section on this drop down for you to choose (master categorized by author, then by application template).  After selecting a default template, the page will populate with new information about the application in the Description field, and will typically provide instructions for how to setup the container.  Select a default template when it is the first time you are configuring this application.
            </p>

            <p>
              <b>User-defined templates</b><br>
              Once you've added an application to your system through a Default template, the settings you specified are saved to your USB flash device to make it easy to rebuild your applications in the event an upgrade were to fail or if another issue occurred.  To rebuild, simply select the previously loaded application from the User-defined list and all the settings for the container will appear populated from your previous setup.  Clicking create will redownload the necessary files for the application and should restore you to a working state.  To delete a User-defined template, select it from the list above and click the red X to the right of it.
            </p>
          </blockquote>
        </td>
      </tr>
      <?endif;?>
      <?if(!empty($templateDescBox)){?>
      <tr>
        <td style="vertical-align: top;">Description:</td>
        <td>
          <div class="textarea desc">
            <?
            echo $templateDescBox;
            if(!empty($Registry)){
              echo "<br><br>Container Page: <a href=\"" . htmlspecialchars($Registry) . "\" target=\"_blank\">" . htmlspecialchars($Registry) . "</a>";
            }
            ?>
          </div>
        </td>
      </tr>
      <?};?>
      <tr>
        <td>Name:</td>

        <td><input type="text" name="containerName" class="textPath" value="<? if(isset($templateName)){ echo htmlspecialchars(trim($templateName));} ?>"></td>
      </tr>
      <tr class="inline_help" style="display: none">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>Give the container a name or leave it as default.</p>
          </blockquote>
        </td>
      </tr>

      <tr class="additionalFields" style="display:none">
        <td>Repository:</td>

        <td><input type="text" name="Repository" class="textPath" value="<? if(isset($templateRepository)){ echo htmlspecialchars(trim($templateRepository));} ?>"></td>
      </tr>
      <tr class="additionalFields" style="display:none">
        <td colspan="2" class="inline_help" style="display:none">
          <blockquote class="inline_help">
            <p>The repository for the application on the Docker Registry.  Format of authorname/appname.  Optionally you can add a : after appname and request a specific version for the container image.</p>
          </blockquote>
        </td>
      </tr>

      <tr>
        <td>Network type:</td>

        <td><select id="NetworkType" name="NetworkType" class="narrow">
          <? foreach (array('bridge', 'host', 'none') as $value) {
            $selected = ($templateMode == $value) ? "selected" : "";
            echo "<option value=\"{$value}\" {$selected}>".ucwords($value)."</option>";
          }?>
        </select></td>
      </tr>
      <tr class="inline_help" style="display:none">
        <td colspan="2">
          <blockquote class="inline_help">
            <p>If the Bridge type is selected, the application’s network access will be restricted to only communicating on the ports specified in the port mappings section.  If the Host type is selected, the application will be given access to communicate using any port on the host that isn’t already mapped to another in-use application/service.  Generally speaking, it is recommended to leave this setting to its default value as specified per application template.</p>
            <p>IMPORTANT NOTE:  If adjusting port mappings, do not modify the settings for the Container port as only the Host port can be adjusted.</p>
          </blockquote>
        </td>
      </tr>

      <tr class="additionalFields" style="display:none">
        <td>Privileged:</td>

        <td><input type="checkbox" name="Privileged" <?if(isset($templatePrivileged)) {echo $templatePrivileged;}?>></td>
      </tr>
      <tr class="additionalFields" style="display:none">
        <td colspan="2" class="inline_help" style="display:none">
          <blockquote class="inline_help">
            <p>For containers that require the use of host-device access directly or need full exposure to host capabilities, this option will need to be selected.  For more information, see this link:  <a href="https://docs.docker.com/reference/run/#runtime-privilege-linux-capabilities-and-lxc-configuration" target="_blank">https://docs.docker.com/reference/run/#runtime-privilege-linux-capabilities-and-lxc-configuration</a></p>
          </blockquote>
        </td>
      </tr>

      <tr class="additionalFields" style="display:none">
        <td>Bind time:</td>

        <td><input type="checkbox" name="BindTime" checked></td>
      </tr>
      <tr class="additionalFields" style="display:none">
        <td colspan="2" class="inline_help" style="display:none">
          <blockquote class="inline_help">
            <p>There's two ways of bind time: one is to mount /etc/localtime to the container (chosen method); the other is to set a variable TZ with the time zone.</p>
          </blockquote>
        </td>
      </tr>
    </table>

    <div id="title">
      <span class="left"><img src="/plugins/dynamix.docker.manager/icons/paths.png" class="icon">Volume Mappings</span>
    </div>

    <table id="pathRows" class="pathTab">
      <thead>
        <tr>
          <td>Container volume:</td>
          <td>Host path:</td>
          <td>Access:</td>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td>
            <input type="text" id="containerPath1" name="containerPath[]" class="textPath" onfocus="hideBrowser(1);" title="The directory your app uses inside the container. Ex: /config">
          </td>
          <td>
            <input type="text" id="hostPath1" name="hostPath[]" class="textPath" autocomplete="off" onclick="toggleBrowser(1);" title="The directory in your array the app have access to. Ex: /mnt/user/Movies">
            <div id="fileTree1" class="textarea fileTree"></div>
          </td>
          <td><select id="hostWritable1" name="hostWritable[]">
              <option value="rw" selected="selected">Read/Write</option>
              <option value="ro">Read Only</option>
            </select>
          </td>
          <td>
            <input onclick="addPath(this.form);" type="button" value="Add path" class="btn">
          </td>
        </tr>
        <?if(isset($templateVolumes)){echo $templateVolumes;}?>
      </tbody>
    </table>

    <blockquote class="inline_help">
      <p>Applications can be given read and write access to your data by mapping a directory path from the container to a directory path on the host.  When looking at the volume mappings section, the Container volume represents the path from the container that will be mapped.  The Host path represents the path the Container volume will map to on your unRAID system.  All applications should require at least one volume mapping to store application metadata (e.g., media libraries, application settings, user profile data, etc.).  Clicking inside these fields provides a "picker" that will let you navigate to where the mapping should point.  Additional mappings can be manually created by clicking the Add Path button.  Most applications will need you to specify additional mappings in order for the application to interact with other data on the system (e.g., with Plex Media Server, you should specify an additional mapping to give it access to your media files).  It is important that when naming Container volumes that you specify a path that won’t conflict with already existing folders present in the container.  If unfamiliar with Linux, using a prefix such as "unraid_" for the volume name is a safe bet (e.g., "/unraid_media" is a valid Container volume name).</p>
    </blockquote>

    <div id="titlePort">
      <div id="title">
        <span class="left"><img src="/plugins/dynamix.docker.manager/icons/network.png" class="icon">Port Mappings</span>
      </div>

      <table id="portRows" class="portRows">
        <tbody>
          <tr>
            <td>Container port:</td>
            <td>Host port:</td>
            <td>Protocol:</td>
          </tr>

          <tr>
            <td>
              <input type="number" min="1" max="65535" id="containerPort1" name="containerPort[]" class="textPort" title="Set the port your app uses inside the container.">
            </td>
            <td>
              <input type="number" min="1" max="65535" id="hostPort1" name="hostPort[]" class="textPort" title="Set the port you use to interact with the app.">
            </td>
            <td>
              <select id="portProtocol1" name="portProtocol[]" class="narrow">
                <option value="tcp" selected="selected">TCP</option>
                <option value="udp">UDP</option>
              </select>
            </td>
            <td>
              <input onclick="addPort(this.form);" type="button" value="Add port" class="btn">
            </td>
          </tr>
          <?if(isset($templatePorts)){echo $templatePorts;}?>
        </tbody>
      </table>

      <blockquote class="inline_help">
        <p>When the network type is set to Bridge, you will be given the option of customizing what ports the container will use.  While applications may be configured to talk to a specific port by default, we can remap those to different ports on our host with Docker.  This means that while three different apps may all want to use port 8000, we can map each app to a unique port on the host (e.g., 8000, 8001, and 8002).  When the network type is set to Host, the container will be allowed to use any available port on your system.  Additional port mappings can be created, similar to Volumes, although this is not typically necessary when working with templates as port mappings should already be specified.</p>
      </blockquote>
    </div>

    <div class="additionalFields" style="display:none">
      <div id="title">
        <span class="left"><img src="/plugins/dynamix.docker.manager/icons/default.png" class="icon">Environment Variables</span>
      </div>

      <table id="envRows" class="envTab">
        <thead>
          <tr>
            <td>Variable Name:</td>
            <td>Variable Value:</td>
          </tr>
        </thead>

        <tbody>
          <tr>
            <td>
              <input type="text" id="VariableName1" name="VariableName[]" class="textEnv">
            </td>
            <td>
              <input type="text" id="VariableValue1" name="VariableValue[]" class="textEnv">
              <input onclick="addEnv(this.form);" type="button" value="Add Variable">
            </td>
          </tr>
          <?if(isset($templateVariables)){echo $templateVariables;}?>
        </tbody>
      </table>

      <blockquote class="inline_help">
        <p>For details, see this link: <a href="https://docs.docker.com/reference/run/#env-environment-variables" target="_blank">https://docs.docker.com/reference/run/#env-environment-variables</a></p>
      </blockquote>
    </div>

    <div <?= empty($templateExtraParams) ? 'class="additionalFields" style="display:none"' : '' ?>>
      <div id="title">
        <span class="left"><img src="/plugins/dynamix.docker.manager/icons/extraparams.png" class="icon">Extra Parameters</span>
      </div>

      <input type="text" name="ExtraParams" class="textTemplate" value="<? if(isset($templateExtraParams)){ echo htmlspecialchars(trim($templateExtraParams));} ?>"/>

      <blockquote class="inline_help">
        <p>If you wish to append additional commands to your Docker container at run-time, you can specify them here.  For example, if you wish to pin an application to live on a specific CPU core, you can enter "--cpuset=0" in this field.  Change 0 to the core # on your system (starting with 0).  You can pin multiple cores by separation with a comma or a range of cores by separation with a dash.  For all possible Docker run-time commands, see here: <a href="https://docs.docker.com/reference/run/" target="_blank">https://docs.docker.com/reference/run/</a></p>
      </blockquote>
    </div>

    <div <?= $showAdditionalInfo ? 'class="additionalFields"' : '' ?> style="display:none">
      <div id="title">
        <span class="left"><img src="/plugins/dynamix.docker.manager/icons/vcard.png" class="icon">Additional Fields</span>
      </div>
      <table class="Template">
        <tr>
          <td style="width: 150px;">Docker Hub URL:</td>
          <td>
            <input type="url" name="Registry" class="textTemplate" placeholder="e.g. https://registry.hub.docker.com/u/username/image" value="<? if(isset($templateRegistry)){ echo htmlspecialchars(trim($templateRegistry));} ?>"/>
          </td>
        </tr>
        <tr class="inline_help" style="display: none">
          <td colspan="2">
            <blockquote class="inline_help">
              <p>The path to the container's repository location on the Docker Hub.</p>
            </blockquote>
          </td>
        </tr>
        <tr>
          <td style="width: 150px;">WebUI:</td>
          <td>
            <input type="text" name="WebUI" class="textTemplate" placeholder="e.g. http://[IP]:[PORT:8080]/" value="<? if(isset($templateWebUI)){ echo htmlspecialchars(trim($templateWebUI));} ?>"/>
          </td>
        </tr>
        <tr class="inline_help" style="display: none">
          <td colspan="2">
            <blockquote class="inline_help">
              <p>When you click on an application icon from the Docker Containers page, the WebUI option will link to the path in this field.  Use [IP} to identify the IP of your host and [PORT:####] replacing the #'s for your port.</p>
            </blockquote>
          </td>
        </tr>
        <tr>
          <td style="width: 150px;">Banner:</td>
          <td>
            <input type="url" name="Banner" class="textTemplate" placeholder="e.g. http://address.to/banner.png" value="<? if(isset($templateBanner)){ echo htmlspecialchars(trim($templateBanner));} ?>"/>
          </td>
        </tr>
        <tr class="inline_help" style="display: none">
          <td colspan="2">
            <blockquote class="inline_help">
              <p>Link to the banner image for your application (only displayed on dashboard if Show Dashboard apps under Display Settings is set to Banners).</p>
            </blockquote>
          </td>
        </tr>
        <tr>
          <td style="width: 150px;">Icon:</td>
          <td>
            <input type="url" name="Icon" class="textTemplate" placeholder="e.g. http://address.to/icon.png" value="<? if(isset($templateIcon)){ echo htmlspecialchars(trim($templateIcon));} ?>"/>
          </td>
        </tr>
        <tr class="inline_help" style="display: none">
          <td colspan="2">
            <blockquote class="inline_help">
              <p>Link to the icon image for your application (only displayed on dashboard if Show Dashboard apps under Display Settings is set to Icons).</p>
            </blockquote>
          </td>
        </tr>
        <tr>
          <td style="width: 150px; vertical-align: top;">Description:</td>
          <td>
            <textarea name="Description" rows="10" cols="71" class="textTemplate"><? if(isset($templateDescription)){ echo htmlspecialchars(trimLine($templateDescription));} ?></textarea>
          </td>
        </tr>
        <tr class="inline_help" style="display: none">
          <td colspan="2">
            <blockquote class="inline_help">
              <p>A description for the application container.  Supports basic HTML mark-up.</p>
            </blockquote>
          </td>
        </tr>
      </table>
    </div>
    <br>
    <div>
      <input type="submit" value="<?= ($xmlType != 'edit') ? 'Create' : 'Save' ?>">
      <input type="button" value="Cancel" onclick="done()">
    </div>
  </form>
</div>

<script src="/webGui/javascript/jquery.filetree.js"></script>
<script src="/plugins/dynamix.docker.manager/javascript/addDocker.js"></script>
<script>
$(function() {
  $(document).mouseup(function (e) {
    var container = $(".fileTree");
    if (!container.is(e.target) && container.has(e.target).length === 0) {
      container.slideUp('fast', function () {
        $(this).html("");
      });
    }
  });

  if ($("#NetworkType").val() != 'bridge') {
    $("#titlePort").css({'display': "none"});
  }
  $("#NetworkType").change(function() {
    if ($(this).val() != "bridge") {
      $("#titlePort").css({'display': "none"});
    } else {
      $("#titlePort").css({'display': "block"});
    }
  });
  $("#TemplateSelect").change(function() {
    if ($(this).val() !== "") {
      $("#xmlTemplate").val($(this).val());
      $("#formTemplate").submit();
    }
  });
  $("#toggleMode").addClass("fa-toggle-off");
  $("#toggleMode").removeClass("fa-toggle-on");
  $("#toggleMode").closest("div").fadeIn('slow');
});
</script>
