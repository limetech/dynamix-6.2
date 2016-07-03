<?PHP
/* Copyright 2005-2016, Lime Technology
 * Copyright 2012-2016, Bergware International.
 * Copyright 2012, Andrew Hamer-Adams, http://www.pixeleyes.co.nz.
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
$var = parse_ini_file('state/var.ini');
?>
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-white.css">

<script>
// server uptime & update period
var uptime = <?=strtok(exec("cat /proc/uptime"),' ')?>;

function add(value, label, last) {
  return parseInt(value)+' '+label+(parseInt(value)!=1?'s':'')+(!last?', ':'');
}
function two(value, last) {
  return (parseInt(value)>9?'':'0')+parseInt(value)+(!last?':':'');
}
function updateTime() {
  document.getElementById('uptime').innerHTML = add(uptime/86400,'day')+two(uptime/3600%24)+two(uptime/60%60)+two(uptime%60,true);
  uptime++;
  setTimeout(updateTime, 1000);
}
</script>

<body onLoad="updateTime()">
<div style="margin-top:20px;font-size:12px;line-height:30px;color:#303030;margin-left:40px;">
<div><span style="width:90px;display:inline-block"><strong>Model:</strong></span>
<?
echo empty($var['SYS_MODEL']) ? 'N/A' : "{$var['SYS_MODEL']}";
?>
</div>
<div><span style="width:90px;display:inline-block"><strong>M/B:</strong></span>
<?
echo exec("dmidecode -q -t 2|awk -F: '/^\tManufacturer:/{m=$2;}; /^\tProduct Name:/{p=$2;} END{print m\" -\"p}'");
?>
</div>
<div><span style="width:90px; display:inline-block"><strong>CPU:</strong></span>
<?
function write($number) {
  $words = array('zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty');
  return $number<=count($words) ? $words[$number] : $number;
}
$cpu = explode('#',exec("dmidecode -q -t 4|awk -F: '/^\tVersion:/{v=$2;}; /^\tCurrent Speed:/{s=$2;} END{print v\"#\"s}'"));
$cpumodel = str_ireplace(array("Processor","(C)","(R)","(TM)"),array("","&#169;","&#174;","&#8482;"),$cpu[0]);
if (strpos($cpumodel,'@')===false) {
  $cpuspeed = explode(' ',$cpu[1]);
  if ($cpuspeed[0]>=1000 && $cpuspeed[1]=='MHz') {
    $cpuspeed[0] /= 1000;
    $cpuspeed[1] = 'GHz';
  }
  echo "$cpumodel @ {$cpuspeed[0]}{$cpuspeed[1]}";
} else {
  echo $cpumodel;
}
?>
</div>
<div><span style="width:90px; display:inline-block"><strong>HVM:</strong></span>
<?
  // Check for Intel VT-x (vmx) or AMD-V (svm) cpu virtualzation support
  // If either kvm_intel or kvm_amd are loaded then Intel VT-x (vmx) or AMD-V (svm) cpu virtualzation support was found
  $strLoadedModules = shell_exec("/etc/rc.d/rc.libvirt test");

  // Check for Intel VT-x (vmx) or AMD-V (svm) cpu virtualzation support
  $strCPUInfo = file_get_contents('/proc/cpuinfo');

  if (!empty($strLoadedModules)) {
    // Yah! CPU and motherboard supported and enabled in BIOS
    ?>Enabled<?
  } else {
    echo '<a href="http://lime-technology.com/wiki/index.php/UnRAID_Manual_6#Determining_HVM.2FIOMMU_Hardware_Support" target="_blank">';
    if (strpos($strCPUInfo, 'vmx') === false && strpos($strCPUInfo, 'svm') === false) {
      // CPU doesn't support virtualzation
      ?>Not Available<?
    } else {
      // Motherboard either doesn't support virtualzation or BIOS has it disabled
      ?>Disabled<?
    }
    echo '</a>';
  }
?>
</div>
<div><span style="width:90px; display:inline-block"><strong>IOMMU:</strong></span>
<?
  // Check for any IOMMU Groups
  $iommu_groups = shell_exec("find /sys/kernel/iommu_groups/ -type l");

  if (!empty($iommu_groups)) {
    // Yah! CPU and motherboard supported and enabled in BIOS
    ?>Enabled<?
  } else {
    echo '<a href="http://lime-technology.com/wiki/index.php/UnRAID_Manual_6#Determining_HVM.2FIOMMU_Hardware_Support" target="_blank">';
    if (strpos($strCPUInfo, 'vmx') === false && strpos($strCPUInfo, 'svm') === false) {
      // CPU doesn't support virtualzation so iommu would be impossible
      ?>Not Available<?
    } else {
      // Motherboard either doesn't support iommu or BIOS has it disabled
      ?>Disabled<?
    }
    echo '</a>';
  }
?>
</div>
<div><span style="width:90px; display:inline-block"><strong>Cache:</strong></span>
<?
$cache = explode('#',exec("dmidecode -q -t 7|awk -F: '/^\tSocket Designation:/{c=c$2\";\";}; /^\tInstalled Size:/{s=s$2\";\";} END{print c\"#\"s}'"));
$socket = array_map('trim',explode(';',$cache[0]));
$volume = array_map('trim',explode(';',$cache[1]));
$name = array();
$size = "";
for ($i=0; $i<count($socket); $i++) {
  if ($volume[$i] && $volume[$i]!='0 kB' && !in_array($socket[$i],$name)) {
    if ($size) $size .= ', ';
    $size .= $volume[$i];
    $name[] = $socket[$i];
  }
}
echo $size;
?>
</div>
<div><span style="width:90px; display:inline-block"><strong>Memory:</strong></span>
<?
// Memory Device (16) will get us each ram chip. By matching on MB it'll filter out Flash/Bios chips
// Sum up all the Memory Devices to get the amount of system memory installed. Convert MB to GB
$memory_installed = exec("dmidecode -t 17 | awk -F: '/^\tSize: [0-9]+ MB$/{t+=$2} /^\tSize: [0-9]+ GB$/{t+=$2*1024} END{print t}'")/1024;
// Physical Memory Array (16) usually one of these for a desktop-class motherboard but higher-end xeon motherboards
// might have two or more of these.  The trick is to filter out any Flash/Bios types by matching on GB
// Sum up all the Physical Memory Arrays to get the motherboard's total memory capacity
$memory_maximum = exec("dmidecode -t 16 | awk -F: '/^\tMaximum Capacity: [0-9]+ GB$/{t+=$2} END{print t}'");
$star = "";
// If maximum < installed then roundup maximum to the next power of 2 size of installed. E.g. 6 -> 8 or 12 -> 16
if ($memory_maximum < $memory_installed) {$memory_maximum = pow(2,ceil(log($memory_installed)/log(2))); $star = "*";}
echo "$memory_installed GB (max. installable capacity $memory_maximum GB)$star";
?>
</div>
<div><span style="width:90px; display:inline-block"><strong>Network:</strong></span>
<?
exec("ls /sys/class/net|grep -Po '^(bond|eth)\d+$'",$sPorts);
$i = 0;
foreach ($sPorts as $port) {
  $mtu = file_get_contents("/sys/class/net/$port/mtu");
  if ($i++) echo "<br><span style='width:94px; display:inline-block'>&nbsp;</span>";
  if ($port=='bond0') {
    echo "$port: ".exec("grep -Pom1 '^Bonding Mode: \K.+' /proc/net/bonding/bond0").", mtu $mtu";
  } else {
    unset($info);
    exec("ethtool $port|grep -Po '^\s+(Speed|Duplex|Link\sdetected): \K[^U\\n]+'",$info);
    echo (array_pop($info)=='yes' && $info[0]) ? "$port: ".str_replace(['M','G'],[' M',' G'],$info[0]).", ".strtolower($info[1])." duplex, mtu $mtu" : "$port: not connected";
  }
}
?>
</div>
<div><span style="width:90px;display:inline-block"><strong>Kernel:</strong></span>
<?$kernel = exec("uname -srm");
  echo $kernel;
?></div>
<div><span style="width:90px; display:inline-block"><strong>OpenSSL:</strong></span>
<?$openssl_ver = exec("openssl version|cut -d' ' -f2");
  echo $openssl_ver;
?></div>
<div><span style="width:94px; display:inline-block"><strong>Uptime:</strong></span><span id="uptime"></span></div>
<center><br>
<input type="button" value="Close" onclick="top.Shadowbox.close()">
<?if ($_GET['more']):?>
<a href="<?=$_GET['more']?>" class="button" target="_parent">More</a>
<?endif;?>
</center>
</body>
