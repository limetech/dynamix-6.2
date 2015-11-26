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

## BETA 11
$dockerManPaths      = array(
	'plugin'            => '/usr/local/emhttp/plugins/dynamix.docker.manager',
	'autostart-file'    => '/var/lib/docker/unraid-autostart',
	'template-repos'    => '/boot/config/plugins/dockerMan/template-repos',
	'templates-user'    => '/boot/config/plugins/dockerMan/templates-user',
	'templates-storage' => '/boot/config/plugins/dockerMan/templates',
	'images-ram'        => '/usr/local/emhttp/state/plugins/dynamix.docker.manager/images',
	'images-storage'    => '/boot/config/plugins/dockerMan/images',
	'webui-info'        => '/usr/local/emhttp/state/plugins/dynamix.docker.manager/docker.json',
	'update-status'     => '/var/lib/docker/unraid-update-status.json',
	);

//## BETA 9
//	$dockerManPaths      = array(
//	'plugin'            => '/usr/local/emhttp/plugins/dockerMan',
//	'autostart-file'    => '/var/lib/docker/unraid-autostart',
//	'template-repos'    => '/boot/config/plugins/dockerMan/template-repos',
//	'templates-user'    => '/boot/config/plugins/dockerMan/templates-user',
//	'templates-storage' => '/boot/config/plugins/dockerMan/templates',
//	'images-ram'        => '/usr/local/emhttp/state/plugins/dockerMan/images',
//	'images-storage'    => '/boot/config/plugins/dockerMan/images',
//	'webui-info'        => '/usr/local/emhttp/state/plugins/dockerMan/docker.json',
//	);

//## BETA 8
//	$dockerManPaths     = array(
//	'plugin'            => '/usr/local/emhttp/plugins/dockerMan',
//	'autostart-file'    => '/var/lib/docker/unraid-autostart',
//	'template-repos'    => '/boot/config/plugins/dockerMan/template-repos',
//	'templates-user'    => '/var/lib/docker/unraid-templates',
//	'templates-storage' => '/boot/config/plugins/dockerMan/templates',
//	'images-ram'        => '/usr/local/emhttp/state/plugins/dockerMan/images',
//	'images-storage'    => '/boot/config/plugins/dockerMan/images',
//	'webui-info'        => '/usr/local/emhttp/state/plugins/dockerMan/docker.json',
//	);

#load emhttp variables if needed.
if (! isset($var)){
	if (! is_file("/usr/local/emhttp/state/var.ini")) shell_exec("wget -qO /dev/null localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')");
	$var = @parse_ini_file("/usr/local/emhttp/state/var.ini");
}

$docker_cfg_defaults = [
	"DOCKER_ENABLED" => "no",
	"DOCKER_OPTS" => "--storage-driver=btrfs",
	"DOCKER_IMAGE_SIZE" => "20",
	"DOCKER_IMAGE_FILE" => "/mnt/user/system/docker/docker.img",
	"DOCKER_APP_CONFIG_PATH" => "/mnt/user/appdata/",
	"DOCKER_APP_UNRAID_PATH" => ""
];
$dockercfg = $docker_cfg_defaults;

// Docker configuration file - create if needed
$docker_cfgfile = "/boot/config/docker.cfg";
if (!file_exists($docker_cfgfile)) {
	$tmp = '';
	foreach ($docker_cfg_defaults as $key => $value) $tmp .= "$key=\"$value\"\n";
	file_put_contents($docker_cfgfile, $tmp);
} else {
	$docker_cfg_existing = parse_ini_file($docker_cfgfile);
	if (!empty($docker_cfg_existing)) {
		$dockercfg = array_merge($docker_cfg_defaults, $docker_cfg_existing);
	}
}

######################################
##   	  DOCKERTEMPLATES CLASS       ##
######################################

class DockerTemplates {

	public $verbose = FALSE;

	private function debug($m) {
		if($this->verbose) echo $m."\n";
	}

	public function download_url($url, $path = "", $bg = FALSE){
		exec("curl --max-time 60 --silent --insecure --location --fail ".($path ? " -o " . escapeshellarg($path) : "")." " . escapeshellarg($url) . " ".($bg ? ">/dev/null 2>&1 &" : "2>/dev/null"), $out, $exit_code );
		return ($exit_code === 0 ) ? implode("\n", $out) : FALSE;
	}

	public function listDir($root, $ext=NULL) {
		$iter = new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator($root,
						RecursiveDirectoryIterator::SKIP_DOTS),
						RecursiveIteratorIterator::SELF_FIRST,
						RecursiveIteratorIterator::CATCH_GET_CHILD);
		$paths = array();
		foreach ($iter as $path => $fileinfo) {
			$fext = $fileinfo->getExtension();
			if ($ext && ( $ext != $fext )) continue;
			if ( $fileinfo->isFile()) $paths[] = array('path' => $path, 'prefix' => basename(dirname($path)), 'name' => $fileinfo->getBasename(".$fext"));
		}
	return $paths;
	}


	public function getTemplates($type) {
		global $dockerManPaths;
		$tmpls = array();
		$dirs = array();
		if ($type == "all"){
			$dirs[] = $dockerManPaths['templates-user'];
			$dirs[] = $dockerManPaths['templates-storage'];

		} else if ($type == "user"){
			$dirs[] = $dockerManPaths['templates-user'];

		} else if ($type == "default"){
			$dirs[] = $dockerManPaths['templates-storage'];
		} else {
			$dirs[] = $type;
		}
		foreach ($dirs as $dir) {
			if (! is_dir( $dir)) @mkdir( $dir, 0770, true);
			$tmpls = array_merge($tmpls, $this->listDir($dir, "xml"));
		}
		return $tmpls;
	}


	private function removeDir($path){
		if (is_dir($path) === true) {
			$files = array_diff(scandir($path), array('.', '..'));
			foreach ($files as $file) {
				$this->removeDir(realpath($path) . '/' . $file);
			}
			return rmdir($path);
		} else if (is_file($path) === true) {
			return unlink($path);
		}
		return false;
	}


	public function downloadTemplates($Dest=NULL, $Urls=NULL){
		global $dockerManPaths;
		$Dest = ($Dest) ? $Dest : $dockerManPaths['templates-storage'];
		$Urls = ($Urls) ? $Urls : $dockerManPaths['template-repos'];
		$repotemplates = array();
		$output = "";
		$tmp_dir = "/tmp/tmp-".mt_rand();
			if (!file_exists($dockerManPaths['template-repos'])) {
			@mkdir(dirname($dockerManPaths['template-repos']), 0777, true);
			@file_put_contents($dockerManPaths['template-repos'], "https://github.com/limetech/docker-templates");
		}
		$urls = @file($Urls, FILE_IGNORE_NEW_LINES);
		if ( ! is_array($urls)) return false;
		$this->debug("\nURLs:\n   " . implode("\n   ", $urls));
		foreach ($urls as $url) {
			$api_regexes = array(
				0 => '%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)/(.*)$%i',
				1 => '%/.*github.com/([^/]*)/([^/]*)/tree/([^/]*)$%i',
				2 => '%/.*github.com/([^/]*)/(.*).git%i',
				3 => '%/.*github.com/([^/]*)/(.*)%i',
				);
			for ($i=0; $i < count($api_regexes); $i++) {
				if ( preg_match($api_regexes[$i], $url, $matches) ){
					$github_api['user']   = ( isset( $matches[1] )) ? $matches[1] : "";
					$github_api['repo']   = ( isset( $matches[2] )) ? $matches[2] : "";
					$github_api['branch'] = ( isset( $matches[3] )) ? $matches[3] : "master";
					$github_api['path']   = ( isset( $matches[4] )) ? $matches[4] : "";
					$github_api['url']    = sprintf("https://github.com/%s/%s/archive/%s.tar.gz", $github_api['user'], $github_api['repo'], $github_api['branch']);
					break;
				}
			}
			if ( $this->download_url($github_api['url'], "$tmp_dir.tar.gz") === FALSE) {
				$this->debug("\n Download ". $github_api['url'] ." has failed.");
				return NULL;
			} else {
				@mkdir($tmp_dir, 0777, TRUE);
				shell_exec("tar -zxf $tmp_dir.tar.gz --strip=1 -C $tmp_dir/ 2>&1");
				unlink("$tmp_dir.tar.gz");
			}
			$tmplsStor = array();
			$templates = $this->getTemplates($tmp_dir);
			$this->debug("\n Templates found in ". $github_api['url']);
			foreach ($templates as $template) {
				$storPath = sprintf("%s/%s", $Dest, str_replace($tmp_dir."/", "", $template['path']) );
				$tmplsStor[] = $storPath;
				if (! is_dir( dirname( $storPath ))) @mkdir( dirname( $storPath ), 0777, true);
				if ( is_file($storPath) ){
					if ( sha1_file( $template['path'] )  ===  sha1_file( $storPath )) {
						$this->debug("   Skipped: ".$template['prefix'].'/'.$template['name']);
						continue;
					} else {
						@copy($template['path'], $storPath);
						$this->debug("   Updated: ".$template['prefix'].'/'.$template['name']);
					}
				} else {
					@copy($template['path'], $storPath);
					$this->debug("   Added: ".$template['prefix'].'/'.$template['name']);
				}
			}
			$repotemplates = array_merge($repotemplates, $tmplsStor);
			$output[$url] = $tmplsStor;
			$this->removeDir($tmp_dir);
		}
		// Delete any templates not in the repos
		foreach ($this->listDir($Dest, "xml") as $arrLocalTemplate) {
			if (!in_array($arrLocalTemplate['path'], $repotemplates)) {
				unlink($arrLocalTemplate['path']);
				$this->debug("   Removed: ".$arrLocalTemplate['prefix'].'/'.$arrLocalTemplate['name']."\n");
				// Any other files left in this template folder? if not delete the folder too
				$files = array_diff(scandir(dirname($arrLocalTemplate['path'])), array('.', '..'));
				if (empty($files)) {
					rmdir(dirname($arrLocalTemplate['path']));
					$this->debug("   Removed: ".$arrLocalTemplate['prefix']);
				}
			}
		}
		return $output;
	}


	public function getTemplateValue($Repository, $field, $scope = "all"){
		$tmpls = $this->getTemplates($scope);

		foreach ($tmpls as $file) {
			$doc = new DOMDocument();
			$doc->load($file['path']);
			$TemplateRepository = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
			if (! preg_match("/:[\w]*$/i", $TemplateRepository)) {
				$Repo = preg_replace("/:[\w]*$/i", "", $Repository);
			}else{
				$Repo = $Repository;
			}

			if ( $Repo == $TemplateRepository ) {
				$TemplateField = $doc->getElementsByTagName( $field )->item(0)->nodeValue;
				return trim($TemplateField);
				break;
			}
		}
		return NULL;
	}


	public function getUserTemplate($Container){
		foreach ($this->getTemplates("user") as $file) {
			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->load( $file['path'] );
			$Name = $doc->getElementsByTagName( "Name" )->item(0)->nodeValue;
			if ($Name == $Container){
				return $file['path'];
			}
		}
		return FALSE;
	}


	public function getControlURL($name){
		global $var;
		$DockerClient = new DockerClient();
		$IP         = $var["IPADDR"];
		$Repository = "";

		foreach ($DockerClient->getDockerContainers() as $ct) {
			if ($ct['Name'] == $name) {
				$Repository = preg_replace("/:[\w]*$/i", "", $ct['Image']);
				$Ports = $ct["Ports"];
				break;
			}
		}

		$WebUI = $this->getTemplateValue($Repository, "WebUI");

		if (preg_match("%\[IP\]%", $WebUI)) {
			$WebUI = preg_replace("%\[IP\]%", $IP, $WebUI);
			preg_match("%\[PORT:(\d+)\]%", $WebUI, $matches);
			$ConfigPort = $matches[1];
			if ($ct["NetworkMode"] == "bridge"){
				foreach ($Ports as $key){
					if ($key["PrivatePort"] == $ConfigPort){
						$ConfigPort = $key["PublicPort"];
					}
				}
			}
			$WebUI = preg_replace("%\[PORT:\d+\]%", $ConfigPort, $WebUI);
		}
		return $WebUI;
	}


	public function removeInfo($container, $image){
		global $dockerManPaths;
		$image = ($image && count(preg_split("#[:\/]#", $image)) < 3) ? "${image}:latest" : $image;
		$dockerIni = $dockerManPaths['webui-info'];
		if (! is_dir( dirname( $dockerIni ))) @mkdir( dirname( $dockerIni ), 0770, true);
		$info = (is_file($dockerIni)) ? json_decode(file_get_contents($dockerIni), TRUE) : array();
		if (! count($info) ) $info = array();

		if (isset($info[$container])) unset($info[$container]);
		file_put_contents($dockerIni, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

		$update_file = $dockerManPaths['update-status'];
		$updateStatus = (is_file($update_file)) ? json_decode(file_get_contents($update_file), TRUE) : array();
		if (isset($updateStatus[$image])) unset($updateStatus[$image]);
		file_put_contents($update_file, json_encode($updateStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}


	public function getAllInfo($reload = FALSE){
		global $dockerManPaths;
		$DockerClient = new DockerClient();
		$DockerUpdate = new DockerUpdate();
		$DockerUpdate->verbose = $this->verbose;
		$new_info     = array();

		$dockerIni = $dockerManPaths['webui-info'];
		if (! is_dir( dirname( $dockerIni ))) @mkdir( dirname( $dockerIni ), 0770, true);
		$info = (is_file($dockerIni)) ? json_decode(file_get_contents($dockerIni), TRUE) : array();
		if (! count($info) ) $info = array();

		$containers   = $DockerClient->getDockerContainers();
		if (! count($containers) ) $containers = array();

		$autostart_file = $dockerManPaths['autostart-file'];
		$allAutoStart = @file($autostart_file, FILE_IGNORE_NEW_LINES);
		if ($allAutoStart===FALSE) $allAutoStart = array();

		foreach ($containers as $ct) {
			$name           = $ct['Name'];
			$image          = $ct['Image'];
			$tmp            = ( count($info[$name]) ) ?  $info[$name] : array() ;

			$tmp['running'] = $ct['Running'];
			$tmp['autostart'] = in_array($name, $allAutoStart);

			if (! $tmp['icon'] || ! $tmp['banner'] || $reload) {
				$img = $this->getBannerIcon( $image );
				$tmp['banner'] = ( $img['banner'] ) ? $img['banner'] : null;
				$tmp['icon']   = ( $img['icon'] )   ? $img['icon'] : null;
			}
			if (! $tmp['url'] || $reload) {
				$WebUI      = $this->getControlURL($name);
				$tmp['url'] = ($WebUI) ? $WebUI : null;
			}

			$Registry = $this->getTemplateValue($image, "Registry");
			$tmp['registry'] = ( $Registry ) ? $Registry : null;

			if (! $tmp['updated'] || $reload) {
				if ($reload) $DockerUpdate->reloadUpdateStatus($image);
				$vs = $DockerUpdate->getUpdateStatus($image);
				$tmp['updated'] = ($vs === NULL) ? null : ( ($vs === TRUE) ? 'true' : 'false' );
			}

			if (! $tmp['template'] || $reload){
				$tmp['template'] = $this->getUserTemplate($name);
			}

			$this->debug("\n$name");foreach ($tmp as $c => $d) $this->debug(sprintf("   %-10s: %s", $c, $d));
			$new_info[$name] = $tmp;
		}
		file_put_contents($dockerIni, json_encode($new_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		return $new_info;
	}


	public function getBannerIcon($Repository){
		global $dockerManPaths;
		$out = array();
		$Images = array();

		$Images        = array('banner' => $this->getTemplateValue($Repository, "Banner"),
		                       'icon' => $this->getTemplateValue($Repository, "Icon") );

		foreach ($Images as $type => $imgUrl) {
			preg_match_all("/(.*?):([\w]*$)/i", $Repository, $matches);
			$tempPath    = sprintf("%s/%s-%s-%s.png", $dockerManPaths[ 'images-ram' ], preg_replace('%\/|\\\%', '-', $matches[1][0]), $matches[2][0], $type);
			$storagePath = sprintf("%s/%s-%s-%s.png", $dockerManPaths[ 'images-storage' ], preg_replace('%\/|\\\%', '-', $matches[1][0]), $matches[2][0], $type);
			if (! is_dir( dirname( $tempPath ))) @mkdir( dirname( $tempPath ), 0770, true);
			if (! is_dir( dirname( $storagePath ))) @mkdir( dirname( $storagePath ), 0770, true);
			if (! is_file( $tempPath )) {
				if ( is_file( $storagePath )){
					@copy($storagePath, $tempPath);
				} else {
					$this->download_url($imgUrl, $storagePath);
					@copy($storagePath, $tempPath);
				}
			}
			$out[ $type ] = ( is_file( $tempPath ) ) ? str_replace('/usr/local/emhttp', '', $tempPath) : "";
		}
		return $out;
	}
}


######################################
##   	  DOCKERUPDATE CLASS        ##
######################################
class DockerUpdate{
	public $verbose = false;

	private function debug($m) {
		if($this->verbose) echo $m."\n";
	}


  public function download_url($url, $path = "", $bg = FALSE){
    exec("curl --max-time 30 --silent --insecure --location --fail ".($path ? " -o " . escapeshellarg($path) : "")." " . escapeshellarg($url) . " ".($bg ? ">/dev/null 2>&1 &" : "2>/dev/null"), $out, $exit_code );
    return ($exit_code === 0 ) ? implode("\n", $out) : FALSE;
  }


	public function getRemoteVersion($image){
		$apiUrl     = vsprintf("http://index.docker.io/v1/repositories/%s/%s/tags/%s", preg_split("#[:\/]#", $image));
		$this->debug("API URL: $apiUrl");
		$apiContent = $this->download_url($apiUrl);
		return ( $apiContent === FALSE ) ? NULL : substr(json_decode($apiContent, TRUE)[0]['id'],0,8);
	}


	public function getLocalVersion($image){
		$DockerClient = new DockerClient();
		return substr($DockerClient->getImageID($image), 0, 8);
	}


	public function getUpdateStatus($image) {
		global $dockerManPaths;
		$DockerClient = new DockerClient();
		$update_file  = $dockerManPaths['update-status'];
		$updateStatus = (is_file($update_file)) ? json_decode(file_get_contents($update_file), TRUE) : array();
		// Add :latest tag to image if it's absent
		$image = ($image && count(preg_split("#[:\/]#", $image)) < 3) ? "${image}:latest" : $image;
		if(isset($updateStatus[$image])) {
			if ($updateStatus[$image]['local'] && $updateStatus[$image]['remote']) {
				return ($updateStatus[$image]['local'] == $updateStatus[$image]['remote']) ? true : false;
			} else {
				return null;
			}
		} else {
			return null;
		}
	}


	public function reloadUpdateStatus($image = null) {
		global $dockerManPaths;
		$DockerClient = new DockerClient();
		$update_file  = $dockerManPaths['update-status'];
		$updateStatus = (is_file($update_file)) ? json_decode(file_get_contents($update_file), TRUE) : array();

		// Add :latest tag to image if it's absent
		$image = ($image && count(preg_split("#[:\/]#", $image)) < 3) ? "${image}:latest" : $image;
		$images = ($image) ? array($image) : array_map(function($ar){return $ar['Tags'][0];}, $DockerClient->getDockerImages());
		foreach ($images as $img) {
			$localVersion  = $this->getLocalVersion($img);
			$remoteVersion = $this->getRemoteVersion($img);
			$status        = ($localVersion && $remoteVersion) ? (($remoteVersion == $localVersion) ? "true" : "false") : "undef";
			$updateStatus[$img] = array('local'  => $localVersion,
			                            'remote' => $remoteVersion,
			                            'status' => $status);
			$this->debug("Update status: Image='${img}', Local='${localVersion}', Remote='${remoteVersion}'");
		}
		file_put_contents($update_file, json_encode($updateStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}


	public function syncVersions($container) {
		global $dockerManPaths;
		$update_file              = $dockerManPaths['update-status'];
		$updateStatus             = (is_file($update_file)) ? json_decode(file_get_contents($update_file), TRUE) : array();
		$updateStatus[$container] = 'true';
		file_put_contents($update_file, json_encode($updateStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}
}


######################################
##   	  DOCKERCLIENT CLASS        ##
######################################
class DockerClient {

	private $allContainersCache = null;


	private $allImagesCache = null;


	private function build_sorter($key) {
		return function ($a, $b) use ($key) {
			return strnatcmp(strtolower($a[$key]), strtolower($b[$key]));
		};
	}


	private function humanTiming ($time){
		$time = time() - $time; // to get the time since that moment
		$tokens = array (31536000 => 'year',
		                 2592000  => 'month',
		                 604800   => 'week',
		                 86400    => 'day',
		                 3600     => 'hour',
		                 60       => 'minute',
		                 1        => 'second'
		                 );
		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'')." ago";
		}
	}


	private function unchunk($result) {
		return preg_replace_callback(
			'/(?:(?:\r\n|\n)|^)([0-9A-F]+)(?:\r\n|\n){1,2}(.*?)'
			.'((?:\r\n|\n)(?:[0-9A-F]+(?:\r\n|\n))|$)/si',
			create_function('$matches','return hexdec($matches[1]) == strlen($matches[2]) ? $matches[2] :$matches[0];'), $result);
	}


	private function formatBytes($size){
		if ($size == 0){ return "0 B";}
		$base = log($size) / log(1024);
		$suffix = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		return round(pow(1024, $base - floor($base)), 1) ." ". $suffix[floor($base)];
	}


	public function getDockerJSON($url, $method = "GET", &$code = null, $callback = null, $unchunk = false){
		$fp = stream_socket_client('unix:///var/run/docker.sock', $errno, $errstr);

		if ($fp === false) {
			echo "Couldn't create socket: [$errno] $errstr";
			return NULL;
		}
		$protocol = ($unchunk) ? "HTTP/1.0" : "HTTP/1.1";
		$out="${method} {$url} ${protocol}\r\nConnection: Close\r\n\r\n";
		fwrite($fp, $out);
		// Strip headers out
		$headers = '';
		while (($line = fgets($fp)) !== false) {
			if (! is_bool(strpos($line, "HTTP/1"))) {
				$code = vsprintf('%2$s',preg_split("#\s+#", $line));
			}
			$headers .= $line;
			if (rtrim($line) == '') {
				break;
			}
		}
		$data = array();
		while (($line = fgets($fp)) !== false) {
			if (is_array($j = json_decode($line, true))) {
				$data = array_merge($data, $j);
			}
			if ($callback) $callback($line);
		}
		fclose($fp);
		return $data;
	}


	public function createDockerContainer() {
		return false;

	}


	public function getInfo(){
		$info = $this->getDockerJSON("/info");
		$version = $this->getDockerJSON("/version");
		return array_merge($info, $version);
	}


	public function getContainetLog($id, $callback, $tail = null, $since = null) {
		$this->getDockerJSON("/containers/${id}/logs?stderr=1&stdout=1&tail=${tail}&since=${since}", "GET", $code, $callback, true);
	}


	public function getContainerDetails($id){
		$json = $this->getDockerJSON("/containers/{$id}/json");
		return $json;
	}


	public function startContainer($id){
		$this->getDockerJSON("/containers/${id}/start", "POST", $code);
		$codes = array("204" => "No error",
		               "304" => "Container already started",
		               "404" => "No such container",
		               "500" => "Server error");
		return ($code == "204") ? true : $codes[$code];
	}


	public function stopContainer($id){
		$this->getDockerJSON("/containers/${id}/stop", "POST", $code);
		$codes = array("204" => "No error.",
		               "304" => "Container already started.",
		               "404" => "No such container.",
		               "500" => "Server error.");
		return ($code == "204") ? true : $codes[$code];
	}


	public function restartContainer($id){
		$json = $this->getDockerJSON("/containers/${id}/restart", "POST", $code);
		$codes = array("204" => "No error.",
		               "404" => "No such container.",
		               "500" => "Server error.");
		return ($code == "204") ? true : $codes[$code];
	}


	public function removeContainer($id){
		$json = $this->getDockerJSON("/containers/{$id}?force=1", "DELETE", $code);
		$codes = array("204" => "No error.",
		               "400" => "Bad parameter.",
		               "404" => "No such container.",
		               "500" => "Server error.");
		return ($code == "204") ? true : $codes[$code];
	}


	public function pullImage($image, $callback = null) {
		return $this->getDockerJSON("/images/create?fromImage=$image", "POST", $code, $callback);
	}


	public function removeImage($id){
		$json = $this->getDockerJSON("/images/{$id}?force=1", "DELETE", $code);
		$codes = array("200" => "No error.",
		               "404" => "No such image.",
		               "409" => "Conflict: image used by container ".$this->usedBy($id)[0].".",
		               "500" => "Server error.");
		return ($code == "200") ? true : $codes[$code];
	}


	private function getImageDetails($id){
		$json = $this->getDockerJSON("/images/$id/json");
		return $json;
	}


	public function getDockerContainers(){
		// Return cached values
		if (is_array($this->allContainersCache)){
			return $this->allContainersCache;
		}

		$containers = array();
		$json = $this->getDockerJSON("/containers/json?all=1");

		if (! $json ){ return $containers; }

		foreach($json as $obj){
			$c = array();
			$status  = $obj['Status'] ? $obj['Status'] : "None";
			preg_match("/\b^Up\b/", $status, $matches);
			$running = $matches ? TRUE : FALSE;
			$details = $this->getContainerDetails($obj['Id']);
			// echo "<pre>".print_r($obj,TRUE)."</pre>";

			// Docker 1.7 uses full image ID when there aren't tags, so lets crop it
			$Image            = (strlen($obj['Image']) == 64) ? substr($obj['Image'],0,12) : $obj['Image'];
			// Docker 1.7 doesn't automatically append the tag 'latest', so we do that now if there's no tag
			$c["Image"]       = ($Image && count(preg_split("#[:\/]#", $Image)) < 3) ? "${Image}:latest" : $Image;
			$c["ImageId"]     = substr($details["Image"],0,12);
			$c["Name"]        = substr($details['Name'], 1);
			$c["Status"]      = $status;
			$c["Running"]     = $running;
			$c["Cmd"]         = $obj['Command'];
			$c["Id"]          = substr($obj['Id'],0,12);
			$c['Volumes']     = $details["HostConfig"]['Binds'];
			$c["Created"]     = $this->humanTiming($obj['Created']);
			$c["NetworkMode"] = $details['HostConfig']['NetworkMode'];
			$c["BaseImage"]   = isset($obj["Labels"]["BASEIMAGE"]) ? $obj["Labels"]["BASEIMAGE"] : false;

			$Ports = $details['HostConfig']['PortBindings'];
			$Ports = (count ( $Ports )) ? $Ports : array();
			$c["Ports"] = array();
			if ($c["NetworkMode"] != 'host'){
				foreach ($Ports as $port => $value) {
					list($PrivatePort, $Type) = explode("/", $port);
					$PublicPort   = $value[0]['HostPort'];
					$c["Ports"][] = array('PrivatePort' => $PrivatePort,
					                      'PublicPort'  => $PublicPort,
					                      'Type'        => $Type );
				}
			}

			$containers[] = $c;
		}
		usort($containers, $this->build_sorter('Name'));
		$this->allContainersCache = $containers;

		return $containers;
	}


	public function getContainerID($Container){
		$allContainers = $this->getDockerContainers();
		foreach ($allContainers as $ct) {
			preg_match("%" . preg_quote($Container, "%") ."%", $ct["Name"], $matches);
			if( $matches){
				return $ct["Id"];
			}
		}
		return NULL;
	}


	public function getImageID($Image){
		$allImages = $this->getDockerImages();
		foreach ($allImages as $img) {
			preg_match("%" . preg_quote($Image, "%") ."%", $img["Tags"][0], $matches);
			if( $matches){
				return $img["Id"];
			}
		}
		return NULL;
	}


	private function usedBy($imageId){
		$out = array();
		$Containers = $this->getDockerContainers();
		$Containers = ( count( $Containers )) ? $Containers : array();
		foreach ($Containers as $ct) {
			if ($ct["ImageId"] == $imageId){
				$out[] = $ct["Name"];
			}
		}
		return $out;
	}


	public function getDockerImages(){
		// Return cached values
		if (is_array($this->allImagesCache)){
			return $this->allImagesCache;
		}
		$images = array();
		$c = array();
		$json = $this->getDockerJSON("/images/json?all=0");

		if (! $json){ return $images; }

		foreach($json as $obj){
			$c = array();
			$tags = array();
			foreach($obj['RepoTags'] as $t){
				$tags[] = htmlentities($t);
			}
				// echo "<pre>".print_r($obj,TRUE)."</pre>";

			$c["Created"]      = $this->humanTiming($obj['Created']);//date('Y-m-d H:i:s', $obj['Created']);
			$c["Id"]           = substr($obj['Id'],0,12);
			$c["ParentId"]     = substr($obj['ParentId'],0,12);
			$c["Size"]         = $this->formatBytes($obj['Size']);
			$c["VirtualSize"]  = $this->formatBytes($obj['VirtualSize']);
			$c["Tags"]         = $tags;
			$c["Repository"]   = vsprintf('%1$s/%2$s',preg_split("#[:\/]#", $tags[0]));
			$c["usedBy"]       = $this->usedBy($c["Id"]);

			$images[$c["Id"]]  = $c;
		}
		$this->allImagesCache = $images;
		return $images;
	}
}
?>
