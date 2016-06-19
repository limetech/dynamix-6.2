#!/usr/bin/php
<?PHP
/* Copyright 2015-2016, Lime Technology
 * Copyright 2015-2016, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
$cfgfile = "/boot/config/docker.cfg";
$cfg_defaults = [
    "DOCKER_ENABLED" => "no",
    "DOCKER_IMAGE_FILE" => "/mnt/user/system/docker/docker.img",
    "DOCKER_IMAGE_SIZE" => "20",
    "DOCKER_APP_CONFIG_PATH" => "/mnt/user/appdata/",
    "DOCKER_APP_UNRAID_PATH" => ""
];

$cfg_new = $cfg_defaults;
if (file_exists($cfgfile)) {
    $cfg_old = parse_ini_file($cfgfile);
    if (!empty($cfg_old)) {
        $cfg_new = array_merge($cfg_defaults, $cfg_old);
        if (empty(array_diff($cfg_new, $cfg_old)))
            unset($cfg_new);
    }
}
if ($cfg_new) {
    foreach ($cfg_new as $key => $value) $tmp .= "$key=\"$value\"\n";
    file_put_contents($cfgfile, $tmp);
}
?>
