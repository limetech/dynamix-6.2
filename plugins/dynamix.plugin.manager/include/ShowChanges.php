<?PHP
/* Copyright 2015, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<!DOCTYPE HTML>
<html>
<head>
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-white.css">
</head>
<body style="margin:14px 10px">
<?
require_once 'webGui/include/Markdown.php';

$file = $_GET['file'];
if (file_exists($file)) echo Markdown(file_get_contents($file)); else echo Markdown("*No release notes available!*");
?>
<br><center><input type="button" value="Done" onclick="top.Shadowbox.close()"></center>
</body>
</html>
