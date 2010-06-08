<?php

//
// $Id$
//

if (!class_exists('STATS')) {

//
// KQview DB access
//
$kq_db = 'kqview';
$kq_host = 'mysql.example.com';
$kq_user = 'kqview-ro-user';
$kq_pw = 'kqview-ro-password';

$kqview_url = 'https://kqview.example.com';
$img_width = 497;
$img_height = 179;

class STATS
{

function STATS()
{
	global $kq_db, $kq_host, $kq_user, $kq_pw;

	$link = mysql_connect($kq_host, $kq_user, $kq_pw)
		or die('Could not connect MySQL: ' . mysql_error());
	mysql_select_db($kq_db) or die('Could not select database');
}

function getolcaption($name, $caption)
{
	$load = $this->getloads($name);
	for ($i = 0; $i < count($load); $i++) {
		$load[$i] = sprintf("%d", $load[$i]);
		if ($load[$i] == -1)
			$load[$i] = "DOWN";
	}
	$caption .= sprintf("max in/out load: %s/%s", $load[0], $load[1]);
	return($caption);
}

function getolbody($name)
{
	global $tki_attribute, $kqview_url, $img_width, $img_height;

	$olbody = '';
	$olbody .= '<table cellspacing=0 cellpadding=0><tr>';
	if (isset($tki_attribute[$name]['subgraphs'])) {
		$subgraphs = split(' ', $tki_attribute[$name]['subgraphs']);
		for ($i = 0; $i < count($subgraphs); $i++) {
			if (($i > 0) && ((count($subgraphs) < 4) || (!($i % 2))))
				$olbody .= '</tr></tr>';
			$olbody .= '<td><img width=' . $img_width . ' height=' . $img_height . ' src=' . $kqview_url . '/kqview/user/plot?target=' . $subgraphs[$i] . (isset($_GET['plot']) ? '&plot=' . $_GET['plot'] : '') . '&days=1&size=400x100></td>';
		}
	} else if (isset($tki_attribute[$name]['target'])){
		$olbody .= '<td><img width=' . $img_width . ' height=' . $img_height . ' src=' . $kqview_url . '/kqview/user/plot?target=' . $tki_attribute[$name]['target'] . (isset($_GET['plot']) ? '&plot=' . $_GET['plot'] : '') . '&days=1&size=400x100></td>';
	}
	$olbody .= '</tr></table>';
	return ($olbody);
}

function getollink($name)
{
	global $tki_attribute, $kqview_url;
	if (isset($tki_attribute[$name]['target']))
		return($kqview_url . '/kqview/user/browse?target=' . $tki_attribute[$name]['target'] . (isset($_GET['plot']) ? '&plot=' . $_GET['plot'] : ''));
	return ('');
}

function getloads($name) {
	global $tki_attribute;

	if (!(isset($tki_attribute[$name]['target']) || isset($tki_attribute[$name]['subgraphs'])) || !isset($tki_attribute[$name]['speed']))
		return (array(0, 0));

	$maxin = 0;
	$maxout = 0;

	if (isset($tki_attribute[$name]['subgraphs'])) {
		$subgraphs = split(' ', $tki_attribute[$name]['subgraphs']);
		for ($i = 0; $i < count($subgraphs); $i++) {
			$data = $subgraphs[$i];

			$query = "SELECT hotspots_if.max_in,hotspots_if.max_out,targets.status FROM hotspots_if,targets WHERE hotspots_if.targetid = '$data' AND targets.targetid = '$data'";
			$result = mysql_query($query) or die('Query failed: ' . mysql_error());
			$row = mysql_fetch_array($result, MYSQL_ASSOC);
			if ($row['status'] != 'up') {
				$tki_attribute[$name]['statusdown'] = 1;
			}
			if (isset($tki_attribute[$name]['target'])) {
				$subi = $row['max_in'] / ($tki_attribute[$name]['speed'] / count($subgraphs)) * 100;
				$subo = $row['max_out'] / ($tki_attribute[$name]['speed'] / count($subgraphs)) * 100;
				if ($subi > $maxin) {
					$maxin = $subi;
				}
				if ($subo > $maxout) {
					$maxout = $subo;
				}
			} else {
				$maxin += $row['max_in'] / ($tki_attribute[$name]['speed']) * 100;
				$maxout += $row['max_out'] / ($tki_attribute[$name]['speed']) * 100;
			}
		}
	} else if (isset($tki_attribute[$name]['target'])) {
		$data = $tki_attribute[$name]['target'];
		$query = "SELECT hotspots_if.max_in,hotspots_if.max_out,targets.status FROM hotspots_if,targets WHERE hotspots_if.targetid = '$data' AND targets.targetid = '$data'";
		$result = mysql_query($query) or die('Query failed: ' . mysql_error());
		$row = mysql_fetch_array($result, MYSQL_ASSOC);
		if ($row['status'] != 'up') {
			return (array(-1, -1));
		} else {
			$maxin = $row['max_in'] / $tki_attribute[$name]['speed'] * 100;
			$maxout = $row['max_out'] / $tki_attribute[$name]['speed'] * 100;
		}
	}
	return (array($maxin, $maxout));
}

} // end of class
} // end of if
?>
