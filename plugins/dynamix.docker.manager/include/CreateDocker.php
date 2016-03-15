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
require_once '/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php';
$DockerClient = new DockerClient();
$DockerUpdate = new DockerUpdate();
$DockerTemplates = new DockerTemplates();

#   ███████╗██╗   ██╗███╗   ██╗ ██████╗████████╗██╗ ██████╗ ███╗   ██╗███████╗
#   ██╔════╝██║   ██║████╗  ██║██╔════╝╚══██╔══╝██║██╔═══██╗████╗  ██║██╔════╝
#   █████╗  ██║   ██║██╔██╗ ██║██║        ██║   ██║██║   ██║██╔██╗ ██║███████╗
#   ██╔══╝  ██║   ██║██║╚██╗██║██║        ██║   ██║██║   ██║██║╚██╗██║╚════██║
#   ██║     ╚██████╔╝██║ ╚████║╚██████╗   ██║   ██║╚██████╔╝██║ ╚████║███████║
#   ╚═╝      ╚═════╝ ╚═╝  ╚═══╝ ╚═════╝   ╚═╝   ╚═╝ ╚═════╝ ╚═╝  ╚═══╝╚══════╝

$echo = function($m){ echo "<pre>".print_r($m, true)."</pre>"; };

function stopContainer($name) {
  global $DockerClient;
  $waitID = mt_rand();

  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Stopping container: ".addslashes($name)."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();

  $retval = $DockerClient->stopContainer($name);
  $out = ($retval === true) ? "Successfully stopped container '$name'" : "Error: ".$retval;

  echo "<script>stop_Wait($waitID);addLog('<b>".addslashes($out)."</b>');</script>\n";
  @flush();
}

function removeContainer($name) {
  global $DockerClient;
  $waitID = mt_rand();

  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Removing container: ".addslashes($name)."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();

  $retval = $DockerClient->removeContainer($name);
  $out = ($retval === true) ? "Successfully removed container '$name'" : "Error: ".$retval;

  echo "<script>stop_Wait($waitID);addLog('<b>".addslashes($out)."</b>');</script>\n";
  @flush();
}

function removeImage($image) {
  global $DockerClient;
  $waitID = mt_rand();

  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Removing orphan image: ".addslashes($image)."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();

  $retval = $DockerClient->removeImage($image);
  $out = ($retval === true) ? "Successfully removed image '$image'" : "Error: ".$retval;

  echo "<script>stop_Wait($waitID);addLog('<b>".addslashes($out)."</b>');</script>\n";
  @flush();
}

function pullImage($name, $image) {
  global $DockerClient, $DockerTemplates, $DockerUpdate;
  $waitID = mt_rand();
  if (!preg_match("/:[\w]*$/i", $image)) $image .= ":latest";

  echo "<p class=\"logLine\" id=\"logBody\"></p>";
  echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Pulling image: ".addslashes($image)."</legend><p class=\"logLine\" id=\"logBody\"></p><span id=\"wait{$waitID}\">Please wait </span></fieldset>');show_Wait($waitID);</script>\n";
  @flush();

  $alltotals = [];
  $laststatus = [];

  // Force information reload
  $DockerTemplates->removeInfo($name, $image);

  $DockerClient->pullImage($image, function ($line) use (&$alltotals, &$laststatus, &$waitID, $image, $DockerClient, $DockerUpdate) {
    $cnt = json_decode($line, true);
    $id = (isset($cnt['id'])) ? trim($cnt['id']) : '';
    $status = (isset($cnt['status'])) ? trim($cnt['status']) : '';

    if ($waitID !== false) {
      echo "<script>stop_Wait($waitID);</script>\n";
      @flush();
      $waitID = false;
    }

    if (empty($status)) return;

    if (!empty($id)) {
      if (!empty($cnt['progressDetail']) && !empty($cnt['progressDetail']['total'])) {
        $alltotals[$id] = $cnt['progressDetail']['total'];
      }
      if (empty($laststatus[$id])) {
        $laststatus[$id] = '';
      }

      switch ($status) {

        case 'Waiting':
          // Omit
          break;

        case 'Downloading':
          if ($laststatus[$id] != $status) {
            echo "<script>addToID('${id}','".addslashes($status)."');</script>\n";
          }
          $total = $cnt['progressDetail']['total'];
          $current = $cnt['progressDetail']['current'];
          if ($total > 0) {
            $percentage = round(($current / $total) * 100);
            echo "<script>progress('${id}',' ".$percentage."% of ".$DockerClient->formatBytes($total)."');</script>\n";
          } else {
            // Docker must not know the total download size (http-chunked or something?)
            //  just show the current download progress without the percentage
            $alltotals[$id] = $current;
            echo "<script>progress('${id}',' ".$DockerClient->formatBytes($current)."');</script>\n";
          }
          break;

        default:
          if ($laststatus[$id] == "Downloading") {
            echo "<script>progress('${id}',' 100% of ".$DockerClient->formatBytes($alltotals[$id])."');</script>\n";
          }
          if ($laststatus[$id] != $status) {
            echo "<script>addToID('${id}','".addslashes($status)."');</script>\n";
          }
          break;
      }

      $laststatus[$id] = $status;

    } else {
      if (strpos($status, 'Status: ') === 0) {
        echo "<script>addLog('".addslashes($status)."');</script>\n";
      }
      if (strpos($status, 'Digest: ') === 0) {
        $DockerUpdate->setUpdateStatus($image, substr($status, 8));
      }
    }
    @flush();
  });

  echo "<script>addLog('<br><b>TOTAL DATA PULLED:</b> " . $DockerClient->formatBytes(array_sum($alltotals)) . "');</script>\n";
  @flush();
}

function xml_encode($string) {
  return htmlspecialchars($string, ENT_XML1, 'UTF-8');
}
function xml_decode($string) {
  return strval(html_entity_decode($string, ENT_XML1, 'UTF-8'));
}

function postToXML($post, $setOwnership = false) {
  $dom = new domDocument;
  $dom->appendChild($dom->createElement("Container"));
  $xml = simplexml_import_dom($dom);
  $xml["version"]          = 2;
  $xml->Name               = xml_encode($post['contName']);
  $xml->Repository         = xml_encode($post['contRepository']);
  $xml->Registry           = xml_encode($post['contRegistry']);
  $xml->Network            = xml_encode($post['contNetwork']);
  $xml->Privileged         = (strtolower($post["contPrivileged"]) == 'on') ? 'true' : 'false';
  $xml->Support            = xml_encode($post['contSupport']);
  $xml->Overview           = xml_encode($post['contOverview']);
  $xml->Category           = xml_encode($post['contCategory']);
  $xml->WebUI              = xml_encode($post['contWebUI']);
  $xml->Icon               = xml_encode($post['contIcon']);
  $xml->ExtraParams        = xml_encode($post['contExtraParams']);

  # V1 compatibility
  $xml->Description      = xml_encode($post['contOverview']);
  $xml->Networking->Mode = xml_encode($post['contNetwork']);
  $xml->Networking->addChild("Publish");
  $xml->addChild("Data");
  $xml->addChild("Environment");

  for ($i = 0; $i < count($post["confName"]); $i++) {
    $Type                  = $post['confType'][$i];
    $config                = $xml->addChild('Config');
    $config->{0}           = xml_encode($post['confValue'][$i]);
    $config['Name']        = xml_encode($post['confName'][$i]);
    $config['Target']      = xml_encode($post['confTarget'][$i]);
    $config['Default']     = xml_encode($post['confDefault'][$i]);
    $config['Mode']        = xml_encode($post['confMode'][$i]);
    $config['Description'] = xml_encode($post['confDescription'][$i]);
    $config['Type']        = xml_encode($post['confType'][$i]);
    $config['Display']     = xml_encode($post['confDisplay'][$i]);
    $config['Required']    = xml_encode($post['confRequired'][$i]);
    $config['Mask']        = xml_encode($post['confMask'][$i]);
    # V1 compatibility
    if ($Type == "Port") {
      $port                = $xml->Networking->Publish->addChild("Port");
      $port->HostPort      = $post['confValue'][$i];
      $port->ContainerPort = $post['confTarget'][$i];
      $port->Protocol      = $post['confMode'][$i];
    } else if ($Type == "Path") {
      $path               = $xml->Data->addChild("Volume");
      $path->HostDir      = $post['confValue'][$i];
      $path->ContainerDir = $post['confTarget'][$i];
      $path->Mode         = $post['confMode'][$i];
    } else if ($Type == "Variable") {
      $variable        = $xml->Environment->addChild("Variable");
      $variable->Value = $post['confValue'][$i];
      $variable->Name  = $post['confTarget'][$i];
      $variable->Mode  = $post['confMode'][$i];
    }
  }
  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml->asXML());
  return $dom->saveXML();
}

function xmlToVar($xml) {
  global $var;
  $xml           = (is_file($xml)) ? simplexml_load_file($xml) : simplexml_load_string($xml);

  $out                = [];
  $out['Name']        = xml_decode($xml->Name);
  $out['Repository']  = xml_decode($xml->Repository);
  $out['Registry']    = xml_decode($xml->Registry);
  $out['Network']     = (isset($xml->Network)) ? xml_decode($xml->Network) : xml_decode($xml->Network['Default']);
  $out['Privileged']  = xml_decode($xml->Privileged);
  $out['Support']     = xml_decode($xml->Support);
  $out['Overview']    = stripslashes(xml_decode($xml->Overview));
  $out['Category']    = xml_decode($xml->Category);
  $out['WebUI']       = xml_decode($xml->WebUI);
  $out['Icon']        = xml_decode($xml->Icon);
  $out['ExtraParams'] = xml_decode($xml->ExtraParams);

  $out['Config'] = [];
  if (isset($xml->Config)) {
    foreach ($xml->Config as $config) {
      $c = [];
      $c['Value'] = strlen(xml_decode($config)) ? xml_decode($config) : xml_decode($config['Default']);
      foreach ($config->attributes() as $key => $value) $c[$key] = xml_decode(xml_decode($value));
      $out['Config'][] = $c;
    }
  }

  # V1 compatibility
  if ($xml["version"] != "2") {
    if (isset($xml->Networking->Mode)) {
      $out['Network'] = xml_decode($xml->Networking->Mode);
    }
    if (isset($xml->Description)) {
      $out['Overview'] = stripslashes(xml_decode($xml->Description));
    }

    if (isset($xml->Networking->Publish->Port)) {
      $portNum = 0;
      foreach ($xml->Networking->Publish->Port as $port) {
        if (empty(xml_decode($port->ContainerPort))) continue;
        $portNum += 1;
        $out['Config'][] = [
          'Name'        => "Port ${portNum}",
          'Target'      => xml_decode($port->ContainerPort),
          'Default'     => xml_decode($port->HostPort),
          'Value'       => xml_decode($port->HostPort),
          'Mode'        => xml_decode($port->Protocol),
          'Description' => '',
          'Type'        => 'Port',
          'Display'     => 'always',
          'Required'    => 'true',
          'Mask'        => 'false'
        ];
      }
    }

    if (isset($xml->Data->Volume)) {
      $volNum = 0;
      foreach ($xml->Data->Volume as $vol) {
        if (empty(xml_decode($vol->ContainerDir))) continue;
        $volNum += 1;
        $out['Config'][] = [
          'Name'        => "Path ${volNum}",
          'Target'      => xml_decode($vol->ContainerDir),
          'Default'     => xml_decode($vol->HostDir),
          'Value'       => xml_decode($vol->HostDir),
          'Mode'        => xml_decode($vol->Mode),
          'Description' => '',
          'Type'        => 'Path',
          'Display'     => 'always',
          'Required'    => 'true',
          'Mask'        => 'false'
        ];
      }
    }

    if (isset($xml->Environment->Variable)) {
      $varNum = 0;
      foreach ($xml->Environment->Variable as $var) {
        if (empty(xml_decode($var->Name))) continue;
        $varNum += 1;
        $out['Config'][] = [
          'Name'        => "Variable ${varNum}",
          'Target'      => xml_decode($var->Name),
          'Default'     => xml_decode($var->Value),
          'Value'       => xml_decode($var->Value),
          'Mode'        => '',
          'Description' => '',
          'Type'        => 'Variable',
          'Display'     => 'always',
          'Required'    => 'false',
          'Mask'        => 'false'
        ];
      }
    }
  }

  return $out;
}

function xmlToCommand($xml, $create_paths=false) {
  global $var;
  $xml           = xmlToVar($xml);
  $cmdName       = (strlen($xml['Name'])) ? '--name="'.$xml['Name'].'"' : "";
  $cmdPrivileged = (strtolower($xml['Privileged']) == 'true') ? '--privileged="true"' : "";
  $cmdNetwork    = '--net="'.strtolower($xml['Network']).'"';
  $Volumes       = [''];
  $Ports         = [''];
  $Variables     = [''];
  $Devices       = [''];
  # Bind Time
  $Variables[]   = 'TZ="' . $var['timeZone'] . '"';
  # Add HOST_OS variable
  $Variables[]   = 'HOST_OS="unRAID"';

  foreach ($xml['Config'] as $key => $config) {
    $confType        = strtolower(strval($config['Type']));
    $hostConfig      = strlen($config['Value']) ? $config['Value'] : $config['Default'];
    $containerConfig = strval($config['Target']);
    $Mode            = strval($config['Mode']);
    if (!strlen($containerConfig)) continue;
    if ($confType == "path") {
      $Volumes[] = sprintf('"%s":"%s":%s', $hostConfig, $containerConfig, $Mode);
      if ( ! file_exists($hostConfig) && $create_paths ) {
        @mkdir($hostConfig, 0777, true);
        @chown($hostConfig, 99);
        @chgrp($hostConfig, 100);
      }
    } elseif ($confType == 'port') {
      # Export ports as variable if Network is set to host
      if (strtolower($xml['Network']) == 'host') {
        $Variables[] = strtoupper(sprintf('"%s_PORT_%s"="%s"', $Mode, $containerConfig, $hostConfig));
      } else {
        $Ports[] = sprintf("%s:%s/%s", $hostConfig, $containerConfig, $Mode);
      }
    } elseif ($confType == "variable") {
      $Variables[] = sprintf('"%s"="%s"', $containerConfig, $hostConfig);
    } elseif ($confType == "device") {
      $Devices[] = '"'.$containerConfig.'"';
    }
  }
  $cmd = sprintf('/plugins/dynamix.docker.manager/scripts/docker create %s %s %s %s %s %s %s %s %s',
                 $cmdName,
                 $cmdNetwork,
                 $cmdPrivileged,
                 implode(' -e ', $Variables),
                 implode(' -p ', $Ports),
                 implode(' -v ', $Volumes),
                 implode(' --device=', $Devices),
                 $xml['ExtraParams'],
                 $xml['Repository']);

  $cmd = preg_replace('/\s+/', ' ', $cmd);
  return [$cmd, $xml['Name'], $xml['Repository']];
}

function getXmlVal($xml, $element, $attr = null, $pos = 0) {
  $xml = (is_file($xml)) ? simplexml_load_file($xml) : simplexml_load_string($xml);
  $element = $xml->xpath("//$element")[$pos];
  return isset($element) ? (isset($element[$attr]) ? strval($element[$attr]) : strval($element)) : "";
}

function setXmlVal(&$xml, $value, $el, $attr = null, $pos = 0) {
  global $echo;
  $xml = (is_file($xml)) ? simplexml_load_file($xml) : simplexml_load_string($xml);
  $element = $xml->xpath("//$el")[$pos];
  if (!isset($element)) $element = $xml->addChild($el);
  if ($attr) {
    $element[$attr] = $value;
  } else {
    $element->{0} = $value;
  }
  $dom = new DOMDocument('1.0');
  $dom->preserveWhiteSpace = false;
  $dom->formatOutput = true;
  $dom->loadXML($xml->asXML());
  $xml = $dom->saveXML();
}


#    ██████╗ ██████╗ ██████╗ ███████╗
#   ██╔════╝██╔═══██╗██╔══██╗██╔════╝
#   ██║     ██║   ██║██║  ██║█████╗
#   ██║     ██║   ██║██║  ██║██╔══╝
#   ╚██████╗╚██████╔╝██████╔╝███████╗
#    ╚═════╝ ╚═════╝ ╚═════╝ ╚══════╝

##
##   CREATE CONTAINER
##

if (isset($_POST['contName'])) {

  $postXML = postToXML($_POST, true);
  $dry_run = ($_POST['dryRun'] == "true") ? true : false;
  $create_paths = $dry_run ? false : true;

  // Get the command line
  list($cmd, $Name, $Repository) = xmlToCommand($postXML, $create_paths);

  // Run dry
  if ($dry_run) {
    echo "<h2>XML</h2>";
    echo "<pre>".htmlentities($postXML)."</pre>";
    echo "<h2>COMMAND:</h2>";
    echo "<pre>".htmlentities($cmd)."</pre>";
    echo '<center><input type="button" value="Done" onclick="done()"></center><br>';
    goto END;
  }

  readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
  @flush();

  // Will only pull image if it's absent
  if (!$DockerClient->doesImageExist($Repository)) {
    // Pull image
    pullImage($Name, $Repository);
  }

  // Saving the generated configuration file.
  $userTmplDir = $dockerManPaths['templates-user'];
  if (!is_dir($userTmplDir)) {
    mkdir($userTmplDir, 0777, true);
  }
  if (!empty($Name)) {
    $filename = sprintf('%s/my-%s.xml', $userTmplDir, $Name);
    file_put_contents($filename, $postXML);
  }

  $startContainer = true;

  // Remove existing container
  if ($DockerClient->doesContainerExist($Name)) {
    // attempt graceful stop of container first
    $oldContainerDetails = $DockerClient->getContainerDetails($Name);
    if (!empty($oldContainerDetails) && !empty($oldContainerDetails['State']) && !empty($oldContainerDetails['State']['Running'])) {
      // attempt graceful stop of container first
      stopContainer($Name);
    }

    // force kill container if still running after 10 seconds
    removeContainer($Name);
  }

  // Remove old container if renamed
  $existing = isset($_POST['existingContainer']) ? $_POST['existingContainer'] : false;
  if ($existing && $DockerClient->doesContainerExist($existing)) {
    // determine if the container is still running
    $oldContainerDetails = $DockerClient->getContainerDetails($existing);
    if (!empty($oldContainerDetails) && !empty($oldContainerDetails['State']) && !empty($oldContainerDetails['State']['Running'])) {
      // attempt graceful stop of container first
      stopContainer($existing);
    } else {
      // old container was stopped already, ensure newly created container doesn't start up automatically
      $startContainer = false;
    }

    // force kill container if still running after 10 seconds
    removeContainer($existing);
  }

  if ($startContainer) {
    $cmd = str_replace('/plugins/dynamix.docker.manager/scripts/docker create ', '/plugins/dynamix.docker.manager/scripts/docker run -d ', $cmd);
  }

  // Injecting the command in $_GET variable and executing.
  $_GET['cmd'] = $cmd;
  include($dockerManPaths['plugin'] . "/include/Exec.php");

  echo '<center><input type="button" value="Done" onclick="done()"></center><br>';
  goto END;
}

##
##   UPDATE CONTAINER
##
if ($_GET['updateContainer']){
  readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
  @flush();

  foreach ($_GET['ct'] as $value) {
    $tmpl = $DockerTemplates->getUserTemplate(urldecode($value));
    if (!$tmpl) {
      echo "<script>addLog('<p>Configuration not found. Was this container created using this plugin?</p>');</script>";
      @flush();
      continue;
    }

    $xml = file_get_contents($tmpl);
    list($cmd, $Name, $Repository) = xmlToCommand($tmpl);
    $Registry = getXmlVal($xml, "Registry");

    $oldImageID = $DockerClient->getImageID($Repository);

    // Pull image
    pullImage($Name, $Repository);

    $oldContainerDetails = $DockerClient->getContainerDetails($Name);

    // determine if the container is still running
    if (!empty($oldContainerDetails) && !empty($oldContainerDetails['State']) && !empty($oldContainerDetails['State']['Running'])) {
      // since container was already running, put it back it to a running state after update
      $cmd = str_replace('/plugins/dynamix.docker.manager/scripts/docker create ', '/plugins/dynamix.docker.manager/scripts/docker run -d ', $cmd);

      // attempt graceful stop of container first
      stopContainer($Name);
    }

    // force kill container if still running after 10 seconds
    removeContainer($Name);

    $_GET['cmd'] = $cmd;
    include($dockerManPaths['plugin'] . "/include/Exec.php");

    $DockerClient->flushCaches();

    $newImageID = $DockerClient->getImageID($Repository);
    if ($oldImageID && $oldImageID != $newImageID) {
      // remove old orphan image since it's no longer used by this container
      removeImage($oldImageID);
    }
  }

  echo '<center><input type="button" value="Done" onclick="window.parent.jQuery(\'#iframe-popup\').dialog(\'close\');"></center><br>';
  goto END;
}

##
##   REMOVE TEMPLATE
##

if ($_GET['rmTemplate']) {
  unlink($_GET['rmTemplate']);
}

##
##   LOAD TEMPLATE
##

if ($_GET['xmlTemplate']) {
  list($xmlType, $xmlTemplate) = split(':', urldecode($_GET['xmlTemplate']));
  if (is_file($xmlTemplate)) {
    $xml = xmlToVar($xmlTemplate);
    $templateName = $xml["Name"];
    if ($xmlType == "default") {
      if (!empty($dockercfg["DOCKER_APP_CONFIG_PATH"]) && file_exists($dockercfg["DOCKER_APP_CONFIG_PATH"])) {
        // override /config
        foreach ($xml['Config'] as &$arrConfig) {
          if ($arrConfig['Type'] == 'Path' && strtolower($arrConfig['Target']) == '/config') {
            $arrConfig['Default'] = $arrConfig['Value'] = realpath($dockercfg["DOCKER_APP_CONFIG_PATH"]).'/'.$xml["Name"];
            $arrConfig['Display'] = 'hidden';
            $arrConfig['Name'] = 'AppData Config Path';
          }
        }
      }
      if (!empty($dockercfg["DOCKER_APP_UNRAID_PATH"]) && file_exists($dockercfg["DOCKER_APP_UNRAID_PATH"])) {
        // override /unraid
        $boolFound = false;
        foreach ($xml['Config'] as &$arrConfig) {
          if ($arrConfig['Type'] == 'Path' && strtolower($arrConfig['Target']) == '/unraid') {
            $arrConfig['Default'] = $arrConfig['Value'] = realpath($dockercfg["DOCKER_APP_UNRAID_PATH"]);
            $arrConfig['Display'] = 'hidden';
            $arrConfig['Name'] = 'unRAID Share Path';
            $boolFound = true;
          }
        }
        if (!$boolFound) {
          $xml['Config'][] = [
            'Name'        => 'unRAID Share Path',
            'Target'      => '/unraid',
            'Default'     => realpath($dockercfg["DOCKER_APP_UNRAID_PATH"]),
            'Value'       => realpath($dockercfg["DOCKER_APP_UNRAID_PATH"]),
            'Mode'        => 'rw',
            'Description' => '',
            'Type'        => 'Path',
            'Display'     => 'hidden',
            'Required'    => 'false',
            'Mask'        => 'false'
          ];
        }
      }
    }
    $xml['Description'] = str_replace(['[', ']'], ['<', '>'], $xml['Overview']);
    echo "<script>var Settings=".json_encode($xml).";</script>";
  }
}

$showAdditionalInfo = '';
?>
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.ui.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.switchbutton.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.filetree.css">
<link rel="stylesheet" type="text/css" href="/plugins/dynamix.docker.manager/styles/style-<?=$display['theme'];?>.css">
<style>
  body{-webkit-overflow-scrolling:touch;}
  .fileTree{width:240px;height:150px;overflow:scroll;position:absolute;z-index:100;display:none;margin-bottom: 100px;}
  #TemplateSelect{width:255px;}
  input.textTemplate,textarea.textTemplate{width:555px;}
  option.list{padding:0 0 0 7px;font-size:11px;}
  optgroup.bold{font-weight:bold;font-size:12px;margin-top:5px;}
  optgroup.title{background-color:#625D5D;color:#FFFFFF;text-align:center;margin-top:10px;}
  .textPath{width:270px;}

  table {
    margin-top: 0;
  }
  table tr {
    vertical-align:top;
    line-height:24px;
  }
  table tr td:nth-child(odd) {
    width: 150px;
    text-align: right;
    padding-right: 10px;
    white-space: nowrap;
  }
  table tr td:nth-child(even) {
    width: 80px;
  }
  table tr td:last-child {
    width: inherit;
  }

  .show{display:block;}
  .inline_help{font-size:12px;font-weight: normal;}
  .desc{padding:6px;line-height:15px;width:inherit;}
  .toggleMode{cursor:pointer;color:#a3a3a3;letter-spacing:0;padding:0;padding-right:10px;font-family:arimo;font-size:12px;line-height:1.3em;font-weight:bold;margin:0;}
  .toggleMode:hover,.toggleMode:focus,.toggleMode:active,.toggleMode .active{color:#625D5D;}
  .basic{display:table-row;}
  .advanced{display:none;}
  .required:after {content: " * ";color: #E80000}

  .switch-wrapper {
    display: inline-block;
    position: relative;
    top: 3px;
    vertical-align: middle;
  }
  .switch-button-label.off {
    color: inherit;
  }
  .spacer{padding-right: 20px}

  .label-warning, .label-success, .label-important {
    padding: 1px 4px 2px;
    -webkit-border-radius: 3px;
    -moz-border-radius: 3px;
    border-radius: 3px;
    font-size: 10.998px;
    font-weight: bold;
    line-height: 14px;
    color: #ffffff;
    text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.25);
    white-space: nowrap;
    vertical-align: middle;
  }
  .label-warning{background-color:#f89406;}
  .label-success{background-color:#468847;}
  .label-important{background-color:#b94a48;}
</style>
<script src="/webGui/javascript/jquery.switchbutton.js"></script>
<script src="/webGui/javascript/jquery.filetree.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/dynamix.vm.manager.js"></script>
<script type="text/javascript">
  var this_tab = $('input[name$="tabs"]').length;
  $(function() {
    var content= "<div class='switch-wrapper'><input type='checkbox' class='advanced-switch'></div>";
    <?if (!$tabbed):?>
    $("#docker_tabbed").html(content);
    <?else:?>
    var last = $('input[name$="tabs"]').length;
    var elementId = "normalAdvanced";
    $('.tabs').append("<span id='"+elementId+"' class='status vhshift' style='display: none;'>"+content+"&nbsp;</span>");
    if ($('#tab'+this_tab).is(':checked')) {
      $('#'+elementId).show();
    }
    $('#tab'+this_tab).bind({click:function(){$('#'+elementId).show();}});
    for (var x=1; x<=last; x++) if(x != this_tab) $('#tab'+x).bind({click:function(){$('#'+elementId).hide();}});
      <?endif;?>
    $('.advanced-switch').switchButton({ labels_placement: "left", on_label: 'Advanced View', off_label: 'Basic View'});
    $('.advanced-switch').change(function () {
      var status = $(this).is(':checked');
      toggleRows('advanced,.hidden', status, 'basic');
    });
  });

  var confNum = 0;

  if (!Array.prototype.forEach) {
    Array.prototype.forEach = function(fn, scope) {
      for (var i = 0, len = this.length; i < len; ++i) {
        fn.call(scope, this[i], i, this);
      }
    };
  }

  if (!String.prototype.format) {
    String.prototype.format = function() {
      var args = arguments;
      return this.replace(/{(\d+)}/g, function(match, number) {
        return typeof args[number] != 'undefined' ? args[number] : match;
      });
    };
  }

  // Create config nodes using templateDisplayConfig
  function makeConfig(opts) {
    confNum += 1;
    newConfig = $("#templateDisplayConfig").html();
    newConfig = newConfig.format(opts.Name,
                                 opts.Target,
                                 opts.Default,
                                 opts.Mode,
                                 opts.Description,
                                 opts.Type,
                                 opts.Display,
                                 opts.Required,
                                 opts.Mask,
                                 opts.Value,
                                 opts.Buttons,
                                 (opts.Required == "true") ? "required" : ""
                                 );
    newConfig = "<div id='ConfigNum"+opts.Number+"' class='"+opts.Display+"'>"+newConfig+"</div>";
    newConfig = $($.parseHTML(newConfig));
    value     = newConfig.find("input[name='confValue[]']");
    if (opts.Type == "Path") {
      value.attr("onclick", "openFileBrowser(this,$(this).val(),'',true,false);");
    } else if (opts.Type == "Device") {
      value.attr("onclick", "openFileBrowser(this,$(this).val(),'',false,true);")
    } else if (opts.Type == "Variable" && opts.Default.split("|").length > 1) {
      var valueOpts = opts.Default.split("|");
      var newValue = "<select name='confValue[]' class='textPath' default='"+valueOpts[0]+"'>";
      for (var i = 0; i < valueOpts.length; i++) {
        newValue += "<option value='"+valueOpts[i]+"' "+(opts.Value == valueOpts[i] ? "selected" : "")+">"+valueOpts[i]+"</option>";
      }
      newValue += "</select>";
      value.replaceWith(newValue);
    } else if (opts.Type == "Port") {
      value.addClass("numbersOnly");
    }
    if (opts.Mask == "true") {
      value.prop("type", "password");
    }
    return newConfig.prop('outerHTML');
  }

  function getVal(el, name) {
    var el = $(el).find("*[name="+name+"]");
    if (el.length) {
      return ( $(el).attr('type') == 'checkbox' ) ? ($(el).is(':checked') ? "on" : "off") : $(el).val();
    } else {
      return "";
    }
  }

  function addConfigPopup() {
    var title = 'Add Configuration';
    var popup = $( "#dialogAddConfig" );

    // Load popup the popup with the template info
    popup.html($("#templatePopupConfig").html());

    // Add switchButton to checkboxes
    popup.find(".switch").switchButton({labels_placement:"right",on_label:'YES',off_label:'NO'});
    popup.find(".switch-button-background").css("margin-top", "6px");

    // Load Mode field if needed
    toggleMode(popup.find("*[name=Type]:first"));

    // Start Dialog section
    popup.dialog({
      title: title,
      resizable: false,
      width: 600,
      modal: true,
      show : {effect: 'fade' , duration: 250},
      hide : {effect: 'fade' , duration: 250},
      buttons: {
        "Add": function() {
          $(this).dialog("close");
          confNum += 1;
          var Opts = Object;
          var Element = this;
          ["Name","Target","Default","Mode","Description","Type","Display","Required","Mask","Value"].forEach(function(e){
            Opts[e] = getVal(Element, e);
          });
          if ( ! Opts["Name"] ){
            Opts["Name"] = makeName(Opts["Type"]);
          }
          Opts.Description = (Opts.Description.length) ? Opts.Description : "Container "+Opts.Type+": "+Opts.Target;
          if (Opts.Required == "true") {
            Opts.Buttons     = "<span class='advanced'><button type='button' onclick='editConfigPopup("+confNum+")'> Edit</button> ";
            Opts.Buttons    += "<button type='button' onclick='removeConfig("+confNum+");'> Remove</button></span>";
          } else {
            Opts.Buttons     = "<button type='button' onclick='editConfigPopup("+confNum+")'> Edit</button> ";
            Opts.Buttons    += "<button type='button' onclick='removeConfig("+confNum+");'> Remove</button>";
          }
          Opts.Number      = confNum;
          newConf = makeConfig(Opts);
          $("#configLocation").append(newConf);
          reloadTriggers();
        },
        Cancel: function() {
          $(this).dialog("close");
        }
      }
    });
    $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
    $(".ui-dialog .ui-dialog-title").css('text-align','center').css( 'width', "100%");
    $(".ui-dialog .ui-dialog-content").css('padding-top','15px').css('vertical-align','bottom');
    $(".ui-button-text").css('padding','0px 5px');
  }

  function editConfigPopup(num) {
    var title = 'Edit Configuration';
    var popup = $("#dialogAddConfig");

    // Load popup the popup with the template info
    popup.html($("#templatePopupConfig").html());

    // Load existing config info
    var config = $("#ConfigNum" + num);
    config.find("input").each(function(){
      var name = $(this).attr("name").replace("conf", "").replace("[]", "");
      popup.find("*[name='"+name+"']").val($(this).val());
    });

    // Hide passwords if needed
    if (popup.find("*[name='Mask']").val() == "true") {
      popup.find("*[name='Value']").prop("type", "password");
    }

    // Load Mode field if needed
    var mode = config.find("input[name='confMode[]']").val();
    toggleMode(popup.find("*[name=Type]:first"));
    popup.find("*[name=Mode]:first").val(mode);

    // Add switchButton to checkboxes
    popup.find(".switch").switchButton({labels_placement:"right",on_label:'YES',off_label:'NO'});

    // Start Dialog section
    popup.find(".switch-button-background").css("margin-top", "6px");
    popup.dialog({
      title: title,
      resizable: false,
      width: 600,
      modal: true,
      show : {effect: 'fade' , duration: 250},
      hide : {effect: 'fade' , duration: 250},
      buttons: {
        "Save": function() {
          $(this).dialog("close");
          var Opts = Object;
          var Element = this;
          ["Name","Target","Default","Mode","Description","Type","Display","Required","Mask","Value"].forEach(function(e){
            Opts[e] = getVal(Element, e);
          });
          Opts.Description = (Opts.Description.length) ? Opts.Description : "Container "+Opts.Type+": "+Opts.Target;
          if (Opts.Required == "true") {
            Opts.Buttons     = "<span class='advanced'><button type='button' onclick='editConfigPopup("+num+")'> Edit</button> ";
            Opts.Buttons    += "<button type='button' onclick='removeConfig("+num+");'> Remove</button></span>";
          } else {
            Opts.Buttons     = "<button type='button' onclick='editConfigPopup("+num+")'> Edit</button> ";
            Opts.Buttons    += "<button type='button' onclick='removeConfig("+num+");'> Remove</button>";
          }
          Opts.Number      = confNum;
          newConf = makeConfig(Opts);
          config.removeClass("always advanced hidden").addClass(Opts.Display);
          $("#ConfigNum" + num).html(newConf);
          reloadTriggers();
        },
        Cancel: function() {
          $(this).dialog("close");
        }
      }
    });
    $(".ui-dialog .ui-dialog-titlebar").addClass('menu');
    $(".ui-dialog .ui-dialog-title").css('text-align','center').css( 'width', "100%");
    $(".ui-dialog .ui-dialog-content").css('padding-top','15px').css('vertical-align','bottom');
    $(".ui-button-text").css('padding','0px 5px');
    $('.desc_readmore').readmore({maxHeight:10});
  }

  function removeConfig(num) {
    $('#ConfigNum' + num).fadeOut("fast", function() { $(this).remove(); });
  }

  function makeName(type) {
    i = $("#configLocation input[name^='confType'][value='"+type+"']").length + 1;
    return type + " "+i;
  }

  function toggleMode(el) {
    var mode       = $(el).parent().siblings('#Mode');
    var valueDiv   = $(el).parent().siblings('#Value');
    var defaultDiv = $(el).parent().siblings('#Default');
    var targetDiv  = $(el).parent().siblings('#Target');

    var value      = valueDiv.find('input[name=Value]');
    var target     = targetDiv.find('input[name=Target]');

    value.unbind();
    target.unbind();

    valueDiv.css('display', '');
    defaultDiv.css('display', '');
    targetDiv.css('display', '');
    mode.html('');

    var index = $(el)[0].selectedIndex;
    if (index == 0) {
      // Path
      mode.html("<dt>Mode</dt><dd><select name='Mode'><option value='rw'>Read/Write</option><option value='ro'>Read Only</option></select></dd>");
      value.bind("click", function(){openFileBrowser(this,$(this).val(), 'sh', true, false);});
    } else if (index == 1) {
      // Port
      mode.html("<dt>Mode</dt><dd><select name='Mode'><option value='tcp'>TCP</option><option value='udp'>UDP</option></select></dd>");
      value.addClass("numbersOnly");
      target.addClass("numbersOnly");
    } else if (index == 3) {
      // Device
      targetDiv.css('display', 'none');
      defaultDiv.css('display', 'none');
      value.bind("click", function(){openFileBrowser(this,$(this).val(), '', true, true);});
    }
    reloadTriggers();
  }

  function loadTemplate(el) {
    var template = $(el).val();
    if (template.length) {
      $('#formTemplate').find("input[name='xmlTemplate']").val(template);
      $('#formTemplate').submit();
    }
  }

  function rmTemplate(tmpl) {
    var name = tmpl.split(/[\/]+/).pop();
    swal({title:"Are you sure?",text:"Remove template: "+name,type:"warning",showCancelButton:true},function(){$("#rmTemplate").val(tmpl);$("#formTemplate").submit();});
  }

  function openFileBrowser(el, root, filter, on_folders, on_files, close_on_select) {
    if (on_folders === undefined) on_folders = true;
    if (on_files   === undefined) on_files = true;
    if (!filter && !on_files) filter = 'HIDE_FILES_FILTER';
    if (!root.trim()) root = "/mnt/user/";
    p = $(el);
    // Skip is fileTree is already open
    if (p.next().hasClass('fileTree')) return null;
    // create a random id
    var r = Math.floor((Math.random()*1000)+1);
    // Add a new span and load fileTree
    p.after("<span id='fileTree"+r+"' class='textarea fileTree'></span>");
    var ft = $('#fileTree'+r);
    ft.fileTree({
      root: root,
      filter: filter,
      allowBrowsing: true
    },
    function(file){if(on_files){p.val(file);if(close_on_select){ft.slideUp('fast',function (){ft.remove();});}}},
    function(folder){if(on_folders){p.val(folder);if(close_on_select){$(ft).slideUp('fast',function (){$(ft).remove();});}}}
    );
    // Format fileTree according to parent position, height and width
    ft.css({'left':p.position().left,'top':( p.position().top + p.outerHeight() ),'width':(p.width()) });
    // close if click elsewhere
    $(document).mouseup(function(e){if(!ft.is(e.target) && ft.has(e.target).length === 0){ft.slideUp('fast',function (){$(ft).remove();});}});
    // close if parent changed
    p.bind("keydown", function(){ft.slideUp('fast', function (){$(ft).remove();});});
    // Open fileTree
    ft.slideDown('fast');
  }

  function resetField(el) {
    var target = $(el).prev();
    reset = target.attr("default");
    if (reset.length) {
      target.val(reset);
    }
  }
</script>
<div id="docker_tabbed" style="display: inline; float: right; margin: -47px 0px;"></div>
<div id="dialogAddConfig" style="display: none"></div>
<form method="GET" id="formTemplate">
  <input type="hidden" id="xmlTemplate" name="xmlTemplate" value="" />
  <input type="hidden" id="rmTemplate" name="rmTemplate" value="" />
</form>

<div id="canvas" style="z-index:1;margin-top:-21px;">
  <form method="POST" autocomplete="off">
    <table>
      <? if ($xmlType == "edit"):
      if ($DockerClient->doesContainerExist($templateName)): echo "<input type='hidden' name='existingContainer' value='${templateName}'>\n"; endif;
      else:?>
      <tr>
        <td>Template:</td>
        <td>
          <select id="TemplateSelect" size="1" onchange="loadTemplate(this);">
            <option value="">Select a template</option>
            <?
            $rmadd = '';
            $all_templates = [];
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
                if ($selected && ($key == "default")) $showAdditionalInfo = 'class="advanced"';
                if (strlen($selected) && $key == 'user' ){ $rmadd = $value['path']; }
                printf("\t\t\t\t\t\t<option class=\"list\" value=\"%s:%s\" {$selected} >%s</option>\n", htmlspecialchars($key), htmlspecialchars($value['path']), htmlspecialchars($value['name']));
              }
              printf("\t\t\t\t\t</optgroup>\n");
            }
            ?>
          </select>
          <? if (!empty($rmadd)) {
            echo "<a onclick=\"rmTemplate('".addslashes($rmadd)."');\" style=\"cursor:pointer;\"><img src=\"/plugins/dynamix.docker.manager/images/remove.png\" title=\"".htmlspecialchars($rmadd)."\" width=\"30px\"></a>";
          }?>
        </td>
      </tr>
      <tr>
        <td colspan="2" class="inline_help">
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
      <tr <?=$showAdditionalInfo?>>
        <td>Name:</td>
        <td><input type="text" name="contName" class="textPath" required></td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>Give the container a name or leave it as default.</p>
          </blockquote>
        </td>
      </tr>
      <tr id="Overview" class="basic">
        <td>Overview:</td>
        <td><div style="color: #3B5998; width:50%; line-height: 16px; padding-top: 4px;" name="contDescription"></div></td>
      </tr>
      <tr id="Overview" class="advanced">
        <td>Overview:</td>
        <td><textarea name="contOverview" rows="10" cols="71" class="textTemplate"></textarea></td>
      </tr>
      <tr>
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>A description for the application container.  Supports basic HTML mark-up.</p>
          </blockquote>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td>Repository:</td>
        <td><input type="text" name="contRepository" class="textPath" required></td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>The repository for the application on the Docker Registry.  Format of authorname/appname.  Optionally you can add a : after appname and request a specific version for the container image.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Categories:</td>
        <td><input type="text" name="contCategory" class="textPath"></td>
      </tr>
      <tr class="advanced">
        <td>Support Thread:</td>
        <td><input type="text" name="contSupport" class="textPath"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>Link to a support thread on Lime-Technology's forum.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Docker Hub URL:</td>
        <td><input type="text" name="contRegistry" class="textPath"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>The path to the container's repository location on the Docker Hub.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Icon URL:</td>
        <td><input type="text" name="contIcon" class="textPath"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>Link to the icon image for your application (only displayed on dashboard if Show Dashboard apps under Display Settings is set to Icons).</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>WebUI:</td>
        <td><input type="text" name="contWebUI" class="textPath"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>When you click on an application icon from the Docker Containers page, the WebUI option will link to the path in this field.  Use [IP} to identify the IP of your host and [PORT:####] replacing the #'s for your port.</p>
          </blockquote>
        </td>
      </tr>
      <tr class="advanced">
        <td>Extra Parameters:</td>
        <td><input type="text" name="contExtraParams" class="textPath"></td>
      </tr>
      <tr class="advanced">
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>If you wish to append additional commands to your Docker container at run-time, you can specify them here.  For example, if you wish to pin an application to live on a specific CPU core, you can enter "--cpuset=0" in this field.  Change 0 to the core # on your system (starting with 0).  You can pin multiple cores by separation with a comma or a range of cores by separation with a dash.  For all possible Docker run-time commands, see here: <a href="https://docs.docker.com/reference/run/" target="_blank">https://docs.docker.com/reference/run/</a></p>
          </blockquote>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td>Network Type:</td>
        <td>
          <select name="contNetwork" class="narrow">
            <option value="bridge">Bridge</option>
            <option value="host">Host</option>
            <option value="none">None</option>
          </select>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>If the Bridge type is selected, the application’s network access will be restricted to only communicating on the ports specified in the port mappings section.  If the Host type is selected, the application will be given access to communicate using any port on the host that isn’t already mapped to another in-use application/service.  Generally speaking, it is recommended to leave this setting to its default value as specified per application template.</p>
            <p>IMPORTANT NOTE:  If adjusting port mappings, do not modify the settings for the Container port as only the Host port can be adjusted.</p>
          </blockquote>
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td>Privileged:</td>
        <td style="line-height: 16px; vertical-align: middle;"><input type="checkbox" name="contPrivileged" class="switch-on-off">
        </td>
      </tr>
      <tr <?=$showAdditionalInfo?>>
        <td colspan="2" class="inline_help">
          <blockquote class="inline_help">
            <p>For containers that require the use of host-device access directly or need full exposure to host capabilities, this option will need to be selected.  For more information, see this link:  <a href="https://docs.docker.com/reference/run/#runtime-privilege-linux-capabilities-and-lxc-configuration" target="_blank">https://docs.docker.com/reference/run/#runtime-privilege-linux-capabilities-and-lxc-configuration</a></p>
          </blockquote>
        </td>
      </tr>
    </table>
    <div id="configLocation"></div>
    <table>
      <tr>
        <td>&nbsp;</td>
        <td><a href="javascript:addConfigPopup();" style="font-size: 1.2em"><i class="fa fa-plus"></i> Add another Path, Port or Variable</a></td>
      </tr>
    </table>
    <br>
    <table>
      <tr>
        <td>&nbsp;</td>
        <td>
          <input type="submit" value="<?= ($xmlType != 'edit') ? 'Create' : 'Save' ?>">
          <!--button class="advanced" type="submit" name="dryRun" value="true" onclick="$('*[required]').prop('required', null);">Dry Run</button-->
          <input type="button" value="Cancel" onclick="done()">
        </td>
      </tr>
    </table>
    <br><br><br>
  </form>
</div>

<?
#        ██╗███████╗    ████████╗███████╗███╗   ███╗██████╗ ██╗      █████╗ ████████╗███████╗███████╗
#        ██║██╔════╝    ╚══██╔══╝██╔════╝████╗ ████║██╔══██╗██║     ██╔══██╗╚══██╔══╝██╔════╝██╔════╝
#        ██║███████╗       ██║   █████╗  ██╔████╔██║██████╔╝██║     ███████║   ██║   █████╗  ███████╗
#   ██   ██║╚════██║       ██║   ██╔══╝  ██║╚██╔╝██║██╔═══╝ ██║     ██╔══██║   ██║   ██╔══╝  ╚════██║
#   ╚█████╔╝███████║       ██║   ███████╗██║ ╚═╝ ██║██║     ███████╗██║  ██║   ██║   ███████╗███████║
#    ╚════╝ ╚══════╝       ╚═╝   ╚══════╝╚═╝     ╚═╝╚═╝     ╚══════╝╚═╝  ╚═╝   ╚═╝   ╚══════╝╚══════╝
?>
<div id="templatePopupConfig" style="display: none">
  <dl>
    <dt>Config Type:</dt>
    <dd>
      <select name="Type" class="narrow" onchange="toggleMode(this);">
        <option value="Path">Path</option>
        <option value="Port">Port</option>
        <option value="Variable">Variable</option>
        <option value="Device">Device</option>
      </select>
    </dd>
    <dt>Name:</dt>
    <dd><input type="text" name="Name" class="textPath"></dd>
    <div id="Target">
      <dt>Target:</dt>
      <dd><input type="text" name="Target" class="textPath"></dd>
    </div>
    <div id="Value">
      <dt>Value:</dt>
      <dd><input type="text" name="Value" class="textPath"></dd>
    </div>
    <div id="Default" class="advanced">
      <dt>Default Value:</dt>
      <dd><input type="text" name="Default" class="textPath"></dd>
    </div>
    <div id="Mode"></div>
    <dt>Description:</dt>
    <dd>
      <textarea name="Description" rows="6" style="width: 304px;"></textarea>
    </dd>
    <div class="advanced">
      <dt>Display:</dt>
      <dd>
        <select name="Display" class="narrow">
          <option value="always" selected>Always</option>
          <option value="advanced">Advanced</option>
          <option value="hidden">Hidden</option>
        </select>
      </dd>
      <dt>Required:</dt>
      <dd>
        <select name="Required" class="narrow">
          <option value="false" selected>No</option>
          <option value="true">Yes</option>
        </select>
      </dd>
      <div id="Mask">
        <dt>Password Mask:</dt>
        <dd>
          <select name="Mask" class="narrow">
            <option value="false" selected>No</option>
            <option value="true">Yes</option>
          </select>
        </dd>
      </div>
    </div>

  </dl>
</div>

<div id="templateDisplayConfig" style="display: none;">
  <input type="hidden" name="confName[]" value="{0}">
  <input type="hidden" name="confTarget[]" value="{1}">
  <input type="hidden" name="confDefault[]" value="{2}">
  <input type="hidden" name="confMode[]" value="{3}">
  <input type="hidden" name="confDescription[]" value="{4}">
  <input type="hidden" name="confType[]" value="{5}">
  <input type="hidden" name="confDisplay[]" value="{6}">
  <input type="hidden" name="confRequired[]" value="{7}">
  <input type="hidden" name="confMask[]" value="{8}">
  <table>
    <tr>
      <td style="vertical-align: top; min-width: 150px; white-space: nowrap; padding-top: 17px;" class="{11}">{0}:</td>
      <td style="width: 100%">
        <input type="text" class="textPath" name="confValue[]" default="{2}" value="{9}" autocomplete="off" {11} > <button type="button" onclick="resetField(this);">Default</button>
        {10}
        <div style='color: #C98C21;line-height: 1.4em'>{4}</div>
      </td>
    </tr>
<!--     <tr class='advanced'>
      <td style="padding-top: 0px;">
        <div style="color: #3B5998;">
          <b>Type: </b>{5}<span class="spacer"></span>
          <b>Display: </b>{6}<span class="spacer"></span>
          <b>Required: </b>{7}<span class="spacer"></span>
          <b>Password: </b>{8}<span class="spacer"></span>
          <b>Mode: </b>{3}<span class="spacer"></span>
        </div>
      </td>
    </tr> -->
  </table>
</div>


<script type="text/javascript">
  function reloadTriggers() {
    $(".basic").toggle(!$(".advanced-switch:first").is(":checked"));
    $(".advanced").toggle($(".advanced-switch:first").is(":checked"));
    $(".hidden").toggle($(".advanced-switch:first").is(":checked"));
    $(".numbersOnly").keypress(function(e){if(e.which != 45 && e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)){return false;}});
  }
  $(function() {
    // Load container info on page load
    if (typeof Settings != 'undefined') {
      for (var key in Settings) {
        if (Settings.hasOwnProperty(key)) {
          var target = $('#canvas').find('*[name=cont'+key+']:first');
          if (target.length) {
            var value = Settings[key];
            if (target.attr("type") == 'checkbox') {
              target.prop('checked', (value == 'true'));
            } else if ($(target).prop('nodeName') == 'DIV') {
              target.html(value);
            } else {
              target.val(value);
            }
          }
        }
      }

      // Remove empty description
      if (!Settings.Description.length) {
        $('#canvas').find('#Overview:first').hide();
      }

      // Load config info
      for (var i = 0; i < Settings.Config.length; i++) {
        confNum += 1;
        Opts             = Settings.Config[i];
        Opts.Description = (Opts.Description.length) ? Opts.Description : "Container "+Opts.Type+": "+Opts.Target;
        if (Opts.Required == "true") {
          Opts.Buttons     = "<span class='advanced'><button type='button' onclick='editConfigPopup("+confNum+")'> Edit</button> ";
          Opts.Buttons    += "<button type='button' onclick='removeConfig("+confNum+");'> Remove</button></span>";
        } else {
          Opts.Buttons     = "<button type='button' onclick='editConfigPopup("+confNum+")'> Edit</button> ";
          Opts.Buttons    += "<button type='button' onclick='removeConfig("+confNum+");'> Remove</button>";
        }
        Opts.Number      = confNum;
        newConf = makeConfig(Opts);
        $("#configLocation").append(newConf);
        reloadTriggers();
      }
    } else {
      $('#canvas').find('#Overview:first').hide();
    }

    // Add switchButton
    $('.switch-on-off').each(function(){var checked = $(this).is(":checked");$(this).switchButton({labels_placement: "right", checked:checked});});
  });
</script>
<?END:?>
