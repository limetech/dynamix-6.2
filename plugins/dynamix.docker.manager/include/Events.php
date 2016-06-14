<?PHP
/* Copyright 2005-2016, Lime Technology
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
require_once '/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php';
$DockerClient = new DockerClient();

$_REQUEST = array_merge($_GET, $_POST);

$action = array_key_exists('action', $_REQUEST) ? $_REQUEST['action'] : '';
$container = array_key_exists('container', $_REQUEST) ? $_REQUEST['container'] : '';
$image = array_key_exists('image', $_REQUEST) ? $_REQUEST['image'] : '';

$arrResponse = ['error' => 'Missing parameters'];

switch ($action) {

	case 'start':
		if ($container) $arrResponse = ['success' => $DockerClient->startContainer($container)];
		break;

	case 'stop':
		if ($container) $arrResponse = ['success' => $DockerClient->stopContainer($container)];
		break;

	case 'restart':
		if ($container) $arrResponse = ['success' => $DockerClient->restartContainer($container)];
		break;

	case 'remove_container':
		if ($container) $arrResponse = ['success' => $DockerClient->removeContainer($container)];
		break;

	case 'remove_image':
		if ($image) $arrResponse = ['success' => $DockerClient->removeImage($image)];
		break;

	case 'remove_all':
		if ($container && $image) {
			// first: try to remove container
			$ret = $DockerClient->removeContainer($container);
			if ($ret === true) {
				// next: try to remove image
				$arrResponse = ['success' => $DockerClient->removeImage($image)];
			} else {
				// error: container failed to remove
				$arrResponse = ['success' => $ret];
			}
		}
		break;

	case 'log':
		if ($container) {
			$since = array_key_exists('since', $_REQUEST) ? $_REQUEST['since'] : '';
			$title = array_key_exists('title', $_REQUEST) ? $_REQUEST['title'] : '';
			require_once '/usr/local/emhttp/webGui/include/ColorCoding.php';
			if (!$since) {
				readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
				echo "<script>document.title = '$title';</script>";
				echo "<script>addLog('".addslashes("<p style='text-align:center'><span class='error label'>Error</span><span class='warn label'>Warning</span><span class='system label'>System</span><span class='array label'>Array</span><span class='login label'>Login</span></p>")."');</script>";
				$tail = 350;
			} else {
				$tail = null;
			}
			$echo = function($s) use ($match) {
				$line = substr(trim($s), 8);
				$span = "span";
				foreach ($match as $type) {
					foreach ($type['text'] as $text) {
						if (preg_match("/$text/i",$line)) {
							$span = "span class='{$type['class']}'";
							break 2;
						}
					}
				}

				echo "<script>addLog('".addslashes("<$span>".htmlentities($line)."</span>")."');</script>";
				@flush();
			};
			$DockerClient->getContainerLog($container, $echo, $tail, $since);
			echo '<script>setTimeout("loadLog(\''.addslashes($container).'\',\''.time().'\')", 2000);</script>';
			@flush();
			exit;
		}
		break;

	default:
		$arrResponse = ['error' => 'Unknown action \'' . $action . '\''];
		break;
}

header('Content-Type: application/json');
die(json_encode($arrResponse));