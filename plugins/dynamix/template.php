<?PHP
/* Copyright 2015, Lime Technology
 * Copyright 2015, Bergware International.
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
require_once('include/Helpers.php');
require_once('include/PageBuilder.php');

// Extract the 'querystring'
// variables provided by emhttp:
//   path=<path>   page path, e.g., path=Main/Disk
//   prev=<path>   prev path, e.g., prev=Main (used to deterine if page was refreshed)
extract($_GET);

// Define some paths
$docroot = $_SERVER['DOCUMENT_ROOT'];

// The current "task" is the first element of the path
$task = strtok($path, '/');

// Get the webGui configuration preferences
extract(parse_plugin_cfg("dynamix",true));

// Read emhttp status
$var     = parse_ini_file('state/var.ini');
$sec     = parse_ini_file('state/sec.ini',true);
$devs    = parse_ini_file('state/devs.ini',true);
$disks   = parse_ini_file('state/disks.ini',true);
$users   = parse_ini_file('state/users.ini',true);
$shares  = parse_ini_file('state/shares.ini',true);
$sec_nfs = parse_ini_file('state/sec_nfs.ini',true);
$sec_afp = parse_ini_file('state/sec_afp.ini',true);

$site = array();
$base = 'dynamix';
// Build the webGui pages first
build_pages("$base/*.page");
// Build the plugins pages
foreach (glob('plugins/*', GLOB_ONLYDIR+GLOB_NOSORT) as $plugin) if ($plugin != $base) build_pages("$plugin/*.page");
// Here's the page we're rendering
$myPage = $site[basename($path)];
$pageroot = "{$docroot}/".dirname($myPage['file']);

// Giddyup
require_once('include/DefaultPageLayout.php');
?>
