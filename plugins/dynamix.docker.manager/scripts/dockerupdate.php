#!/usr/bin/php
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
exec("pgrep docker",$pid);
if (count($pid)==1) exit(0);

require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");
$DockerClient = new DockerClient();
$DockerTemplates = new DockerTemplates();

foreach ($argv as $arg) {
  switch ($arg) {
  case '-v'   : $DockerTemplates->verbose = true; break;
  case 'check': $check = true; break;}
}

if (!isset($check)) {
  echo " Updating templates... ";
  $DockerTemplates->downloadTemplates();
  echo " Updating info... ";
  $DockerTemplates->getAllInfo(true);
  echo " Done.";
} else {
  require_once("/usr/local/emhttp/webGui/include/Wrappers.php");
  $notify = "/usr/local/emhttp/webGui/scripts/notify";
  $unraid = parse_plugin_cfg("dynamix",true);
  $server = strtoupper($var['NAME']);
  $output = $unraid['notify']['docker_notify'];

  $list = $DockerClient->getDockerContainers();
  $info = $DockerTemplates->getAllInfo(true);
  foreach ($list as $ct) {
    $name = $ct['Name'];
    $image = $ct['Image'];
    if ($info[$name]['updated'] == "false") {
      $updateStatus = (is_file($dockerManPaths['update-status'])) ? json_decode(file_get_contents($dockerManPaths['update-status']), TRUE) : array();
      $new = str_replace('sha256:', '', $updateStatus[$image]['remote']);
      $new = substr($new, 0, 4).'..'.substr($new, -4, 4);

      exec("$notify -e ".escapeshellarg("Docker - $name [$new]")." -s ".escapeshellarg("Notice [$server] - Docker update $new")." -d ".escapeshellarg("A new version of $name is available")." -i ".escapeshellarg("normal $output")." -x");
    }
  }
}
exit(0);
?>
