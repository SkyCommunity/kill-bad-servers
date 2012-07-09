<?php

/**
 * Copyright (c) 2009 MekDrop
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 **/

function get_ppid($id) {
  $ret = shell_exec("ps -p $id -o %P");
  $ret = explode("\n", $ret);
  $ret = intval($ret[1]);
  while ($ret > 1) {
     $ret2 = get_ppid($ret);
     if ($ret2 < 2) return $ret;
     $ret = $ret2;
  }
}


$ret = shell_exec("netstat -l");
$ret = explode("\n", $ret);
unset($ret[0]);
unset($ret[1]);

$servers = array();
$notneeded = false;
foreach ($ret as $key => $value) {
    $ret[$key] = explode(" ", $value);
    foreach ($ret[$key] as $k2 => $v2) {
	if ($v2 == '') unset($ret[$key][$k2]);
    }
    $ret[$key] = array_values($ret[$key]);
    if (strtolower($ret[$key][0]) == "active" && strtolower($ret[$key][1]) == "unix" && strtolower($ret[$key][2]) == "domain") {
        $notneeded = true;
    } elseif (strtolower($ret[$key][0]) == "proto") {
	$notneeded = true;
    }
    if ((count($ret[$key]) < 1) || $notneeded) {
	unset($ret[$key]);
        continue;
    }
    $port = explode(':',$ret[$key][3]);
    if ($port[0] == '*') continue;
    $port = $port[1];
    if (intval($port).''!="$port") continue;
    if (!isset($servers[$ret[$key][0]])) {
	$servers[$ret[$key][0]] = array();
    }
    $servers[$ret[$key][0]][] = $ret[$key][3];
}
unset($ret);

$server_type = (isset($_REQUEST['server_type']) && !empty($_REQUEST['server_tlype']))?$_REQUEST['server_type']:'cs';
$flags = (isset($_REQUEST['flags']) && !empty($_REQUEST['flags']))?$_REQUEST['flags']:'';

$command_line = "./qstat -u -xml";

/*switch (strtolower($flags)) {
    case 'p':
	$command_line .= ' -P';
    break;
    case 'r':
        $command_line .= ' -R';
    break;
    case 'pr':
    case 'rp':
	$command_line .= ' -R -P';
    break;
}*/

switch ($server_type) {
    case 'cs':
    case 'cs1.6':
	$command_line .= ' -default a2s ' . implode(" ", $servers['udp']). ' +localhost:27000-27050';
    break;
}

$xml = shell_exec($command_line);

require_once "class.xmltoarray.php";

$xmlObj    = new XmlToArray($xml); 
$arrayData = $xmlObj->createArray();

//mail("killed.process@mekdrop.name", "Killed process: array data nfo", var_export($arrayData, true)."\n\n\n".$ppid);

$badservers = array();
foreach ($arrayData['qstat']['server'] as $i => $server) {
   $server['name'] = strtolower($server['name']);
   if (strstr($server['name'], 'skygames')) {
      // doing nothing
//      $badservers[] = array_merge(explode(':', $server['hostname']), array($server['gametype'], $server['name']));
   } elseif (strstr($server['name'], 'skynet')) {
      // doing nothing
   } else {
      $badservers[] = array_merge(explode(':', $server['hostname']), array($server['gametype'], $server['name']));
   }
}

if (count($badservers) < 1) exit();

//mail("killed.process@mekdrop.name", "Killed process: server nfo", var_export($badservers, true)."\n\n\n".$ppid);

$command_line = 'lsof';
foreach ($badservers as $server) {
   $command_line .= ' -i:'. $server[1];
}

$ret = shell_exec($command_line);
$ret = explode("\n", $ret);
unset($ret[0]);
foreach ($ret as $key => $value) {
    $ret[$key] = explode(" ", $value);
    foreach ($ret[$key] as $k2 => $v2) {
	if ($v2 == '') unset($ret[$key][$k2]);
    }
    $ret[$key] = array_values($ret[$key]);
    $ret2 = intval($ret[$key][1]);
    if ($ret2 == 0) {
	unset($ret[$key]);
        continue;
    }
    $ppid = get_ppid($ret2);
    echo "$ret2 -> $ppid\n";
    shell_exec('kill '.$ppid);
    shell_exec('kill -8 '.$ppid);
    shell_exec('kill -9 '.$ppid);
    shell_exec('kill '.$ret2);
    shell_exec('kill -8 '.$ret2);
    shell_exec('kill -9 '.$ret2);
   // mail("killed.process@mekdrop.name", "Killed process: skycommunity server", var_export($ret, true)."\n\n\n".$ppid);
}

//var_dump($ret);

//Displaying the Array 
//echo "<pre>"; 
//print_r($arrayData); 
//echo "</pre>";

?>