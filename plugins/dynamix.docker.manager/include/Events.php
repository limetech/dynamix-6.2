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
require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");
$DockerClient = new DockerClient();

$_REQUEST = array_merge($_GET, $_POST);

$action = array_key_exists('action', $_REQUEST) ? $_REQUEST['action'] : '';
$container = array_key_exists('container', $_REQUEST) ? $_REQUEST['container'] : '';

switch ($action) {

	case 'start':
		if ($container) echo json_encode(array('success' => $DockerClient->startContainer($container) ));
		break;

	case 'stop':
		if ($container) echo json_encode(array('success' => $DockerClient->stopContainer($container) ));
		break;

	case 'restart':
		if ($container) echo json_encode(array('success' => $DockerClient->restartContainer($container) ));
		break;

	case 'remove_container':
		if ($container) echo json_encode(array('success' => $DockerClient->removeContainer($container) ));
		break;

	case 'remove_image':
		$image = array_key_exists('image', $_REQUEST) ? $_REQUEST['image'] : '';
		if ($image) echo json_encode([ 'success' => $DockerClient->removeImage($image) ]);
		break;

	case 'log':
		if ($container) {
			$since = array_key_exists('since', $_REQUEST) ? $_REQUEST['since'] : '';
			$title = array_key_exists('title', $_REQUEST) ? $_REQUEST['title'] : '';
			if (!$since) {
				readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
				echo "<script>document.title = '$title';</script>";
				$tail = 350;
			} else {
				$tail = null;
			}
			$echo = function($s){$s=addslashes(substr(trim($s),8));echo "<script>addLog('".$s."');</script>";@flush();};
			$DockerClient->getContainerLog($container, $echo, $tail, $since);
			echo "<script>setTimeout(\"loadLog('".addslashes($container)."','".time()."')\",2000);</script>";
			@flush();
		}
		break;

}
