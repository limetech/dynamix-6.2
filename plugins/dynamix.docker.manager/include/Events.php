<?PHP
require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");
$DockerClient = new DockerClient();

$container = $_POST['container'];
$image = $_POST['image'];

switch ($_POST['action']) {
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
    if ($image) echo json_encode(array('success' => $DockerClient->removeImage($image) ));
    break;
}

$container = $_GET['container'];
$since = $_GET['since'];
$title = $_GET['title'];

switch ($_GET['action']) {
  case 'log':
    if ($container) {
      $echo = function($s){$s=addslashes(substr(trim($s),8));echo "<script>addLog('".$s."');</script>";@flush();};
      if (!$since) {
        readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");
        echo "<script>document.title = '$title';</script>";
        $tail = 350;
      } else {
        $tail = null;
      }
      $DockerClient->getContainetLog($container, $echo, $tail, $since);
      echo "<script>setTimeout(\"loadLog('${container}','".time()."')\",2000);</script>";
      @flush();
    }
    break;

}
?>

