<?php

//
// $Id$
//

$g_xmin = 100000;
$g_xmax = 0;
$g_ymin = 100000;
$g_ymax = 0;

$iconscale = 1;
$mapscale = 1;

$map = $_GET['map'];
$sterile_map = preg_replace( "/[^\w\.-]+/", "_", $map);
$type = $_GET['type'];
$sterile_type = preg_replace( "/[^\w\.-]+/", "_", $type);
$tki_filename = $tkidir . '/' . $sterile_map . '.tki';
$cache_filename = $cachedir . '/trafficmap_' . $sterile_map . '_' . $sterile_type . '.cache';

if (isset($statsmodule) && file_exists('./class_stats_' . $statsmodule . '.php')) {
	require_once './class_stats_' . $statsmodule . '.php';
}

function X($x) {
	global $g_xmin, $g_xborder, $mapscale;
	return((($x - $g_xmin) * $mapscale) + $g_xborder);
}

function Y($y) {
	global $g_ymin, $g_yborder, $mapscale;
	return((($y - $g_ymin) * $mapscale) + $g_yborder);
}

function middle($x1, $x2)
{
	return ($x1 + ($x2 - $x1) / 2);
}

function newx ($a, $b, $x, $y)
{
	return round(cos(atan2($y, $x) + atan2($b, $a)) * sqrt($x * $x + $y * $y));
}

function newy ($a, $b, $x, $y)
{
	return round(sin(atan2($y, $x) + atan2($b, $a)) * sqrt($x * $x + $y * $y));
}

function get_load_rgb($load)
{
	global $scalecolors;
	for ($i = 0; $i < count($scalecolors) - 1; $i++) {
		if ($load <= $scalecolors[$i][0])
			break;
	}
	return (array($scalecolors[$i][1], $scalecolors[$i][2], $scalecolors[$i][3]));
}

function ifspeed2width($speed)
{
	global $linkwidths, $mapscale;
	for ($i = 0; $i < count($linkwidths); $i++) {
		if ($speed <= $linkwidths[$i][0])
			break;
	}
	return ($linkwidths[$i][1] * $mapscale);
}

function plot_link_png($x1, $y1, $x2, $y2, $loadin, $loadout, $width, $linkcount, $drawinglink, $name, $links_space)
{
	global $img;
	$c = calculate_multiple_line ($x1, $y1, $x2, $y2, $linkcount, $drawinglink, $links_space);
	$rgb = get_load_rgb($loadin);
	$icolor = ImageColorAllocate($img, $rgb[0], $rgb[1], $rgb[2]);
	$rgb = get_load_rgb($loadout);
	$ocolor = ImageColorAllocate($img, $rgb[0], $rgb[1], $rgb[2]);
	draw_arrow($img, $c['x1'], $c['y1'], middle($c['x1'], $c['x2']), middle($c['y1'], $c['y2']), $ocolor, $width);
	draw_arrow($img, $c['x2'], $c['y2'], middle($c['x1'], $c['x2']), middle($c['y1'], $c['y2']), $icolor, $width);

	global $tki_node2, $tki_name, $tki_attribute;
	global $c_white, $c_black;
	$node2 = $tki_node2[$name];
	if (($tki_name[$node2] == "HIDDEN") && isset($tki_attribute[$name]['ifaliasB'])) {
		$text = $tki_attribute[$name]['ifaliasB'];
		$pixelw = strlen($text) * 6;
		$xsize = $pixelw + 3;
		if ($xsize < 15)
			$xsize = 15;	# minimum height
		if (($xsize % 2) == 0)
			$xsize++;
		$xsize += 8;		# pad with 4 pixels
		$ysize = $xsize;
		$middle = ($xsize - 1) / 2;
		$tmpim = ImageCreate($xsize, $ysize);
#		if (isset($tki_attribute[$name]['maplink'])) {
		$fgcolor = ImageColorAllocate($tmpim, 0, 0, 0);
		if (isset($tki_attribute[$name]['statusdown'])) {
			$bgcolor = ImageColorAllocate($tmpim, 255, 0, 0);
		} else {
			$bgcolor = ImageColorAllocate($tmpim, 255, 255, 255);
		}
#		$transparent = ImageColorAllocate($tmpim, 0, 254, 254);
#		ImageColorTransparent($tmpim, $transparent);
#		ImageFill($tmpim, 0, 0, $transparent);
		ImageFilledRectangle($tmpim, 0 + 4, $middle - 7, $xsize - 1 - 4, $middle + 7, $bgcolor);
		ImageRectangle($tmpim, 0 + 4, $middle - 7, $xsize - 1 - 4, $middle + 7, $fgcolor);
		ImageString($tmpim, 2, 2 + 4, $middle - 7, $text, $fgcolor);
		$rotate = -1;
		if ($c['x1'] == $c['x2'])
			$rotate = 90;
		if ($c['y1'] == $c['y2'])
			$rotate = 0;
		if ($rotate == 0) {
			ImageCopy($img, $tmpim, $c['x2'] - $middle, $c['y2'] - 7, 0 + 4, $middle - 7, $xsize - 2 * 4, 15);
		} else {
			$rotim = imagerotate($tmpim, $rotate, $transparent);
#			ImageColorTransparent($rotim, $transparent);
			ImageDestroy($tmpim);
			$tmpim = $rotim;
			if ($rotate == 90) {
				ImageCopy($img, $tmpim, $c['x2'] - 7, $c['y2'] - $middle, $middle - 7, 0 + 4, 15, $ysize - 2 * 4);
			} else {
# TODO: only 0 and 90 degrees are supported right now
#				ImageCopy($img, $tmpim, $c['x2'] - $middle, $c['y2'] - $middle, 0, 0, $xsize, $ysize);
			}
		}
		ImageDestroy($tmpim);
	}
}

function plot_link_map($x1, $y1, $x2, $y2, $width, $linkcount, $drawinglink, $name, $links_space)
{
	$c = calculate_multiple_line ($x1, $y1, $x2, $y2, $linkcount, $drawinglink, $links_space);
	overlib_arrow($c['x1'], $c['y1'], middle($c['x1'], $c['x2']), middle($c['y1'], $c['y2']), $width, $name);
	overlib_arrow($c['x2'], $c['y2'], middle($c['x1'], $c['x2']), middle($c['y1'], $c['y2']), $width, $name);
}

function plot_bg_center_string($img, $size, $xc, $y, $text, $color, $c_white)
{
	$pixelw = strlen($text) * 6;
	$x = $xc - floor($pixelw / 2);
	plot_bg_string($img, $size, $x, $y, $text, $color, $c_white);
}

function plot_bg_string($img, $size, $x, $y, $text, $color, $c_white)
{
	ImageString($img, $size, $x - 1, $y - 1, $text, $c_white);
	ImageString($img, $size, $x - 1, $y + 0, $text, $c_white);
	ImageString($img, $size, $x - 1, $y + 1, $text, $c_white);
	ImageString($img, $size, $x + 0, $y - 1, $text, $c_white);
	ImageString($img, $size, $x + 0, $y + 1, $text, $c_white);
	ImageString($img, $size, $x + 1, $y - 1, $text, $c_white);
	ImageString($img, $size, $x + 1, $y + 0, $text, $c_white);
	ImageString($img, $size, $x + 1, $y + 1, $text, $c_white);
	ImageString($img, $size, $x, $y, $text, $color);
}

function get_icon_filename($ext, $name, $default)
{
	global $icondir;
	$sterile_name = preg_replace( "/[^\w\.-]+/", "_", $name);
	if (strpos($name, ".") > 0)
		$name2 = substr($sterile_name, 0, strpos($name, "."));
	else
		$name2 = $sterile_name;

	# try 100x100 icons first
	if (is_readable("$icondir/" . $name2 . "_100.$ext"))
		return("$icondir/" . $name2 . "_100.$ext");

	if (is_readable("$icondir/" . $name2 . ".$ext"))
		return("$icondir/" . $name2 . ".$ext");

	if ($default != "")
		return(get_icon_filename($ext, $default, ""));
	exit;
}

function calculate_line_move($x1, $y1, $x2, $y2, $delta)
{
	$xlen = $x2 - $x1;
	$ylen = $y2 - $y1;
	$dlen = sqrt($xlen * $xlen + $ylen * $ylen);
	if ($dlen == 0) {
		$d[0] = 0;
		$d[1] = 0;
	} else {
		$x3 = ($ylen / $dlen) * $delta;
		$y3 = ($xlen / $dlen) * $delta;
		$d[0] = $x3;
		$d[1] = -$y3;
	}
	# this moves reverse direction links to right side
	if (($x2 > $x1) || (($x2 == $x1) && ($y2 > $y1))) {
		$d[0] = - $d[0];
		$d[1] = - $d[1];
	}
	return ($d);
}

function calculate_multiple_line ($x1, $y1, $x2, $y2, $linkcount, $drawinglink, $links_space)
{
	global $mapscale;

	$d[0] = 0;
	$d[1] = 0;

	if ($linkcount > 1) {
		if (($linkcount % 2) == 0) {	# even
			$d = calculate_line_move($x1, $y1, $x2, $y2, floor(($drawinglink - 0.5) / 2) * $links_space * $mapscale + ($links_space * $mapscale / 2));
			if ((($drawinglink / 2) % 2) == 0) {
				$d[0] = - $d[0];
				$d[1] = - $d[1];
			}
		} else {			# odd
			if ($drawinglink > 1) {
				$d = calculate_line_move($x1, $y1, $x2, $y2, floor($drawinglink / 2) * $links_space * $mapscale);
				if (($drawinglink % 2) == 0) {
					$d[0] = - $d[0];
					$d[1] = - $d[1];
				}
			}
		}
	}
	$c['x1'] = $x1 + $d[0];
	$c['x2'] = $x2 + $d[0]; 
	$c['y1'] = $y1 + $d[1];
	$c['y2'] = $y2 + $d[1];
	return($c);
}

function get_arrow_points($x1, $y1, $x2, $y2, $w)
{
	$points[0]=$x1 + newx($x2-$x1, $y2-$y1, 0, $w);
	$points[1]=$y1 + newy($x2-$x1, $y2-$y1, 0, $w);
	$points[2]=$x2 + newx($x2-$x1, $y2-$y1, -4*$w, $w);
	$points[3]=$y2 + newy($x2-$x1, $y2-$y1, -4*$w, $w);
	$points[4]=$x2 + newx($x2-$x1, $y2-$y1, -4*$w, 2*$w);
	$points[5]=$y2 + newy($x2-$x1, $y2-$y1, -4*$w, 2*$w);
	$points[6]=$x2;
	$points[7]=$y2;
	$points[8]=$x2 + newx($x2-$x1, $y2-$y1, -4*$w, -2*$w);
	$points[9]=$y2 + newy($x2-$x1, $y2-$y1, -4*$w, -2*$w);
	$points[10]=$x2 + newx($x2-$x1, $y2-$y1, -4*$w, -$w);
	$points[11]=$y2 + newy($x2-$x1, $y2-$y1, -4*$w, -$w);
	$points[12]=$x1 + newx($x2-$x1, $y2-$y1, 0, -$w);
	$points[13]=$y1 + newy($x2-$x1, $y2-$y1, 0, -$w);
	return $points;
}

function draw_arrow($img, $x1, $y1, $x2, $y2, $color, $w)
{
	global $c_white;
	
	$points = get_arrow_points($x1, $y1, $x2, $y2, $w);
	$num_points = count($points) / 2;
	imagefilledPolygon($img, $points, $num_points, $color);
# Antialias works fine with new PHP and GD
#	# make antialias effect
#	imagepolygon($img, $points, $num_points, $c_white);
	if ($color == $c_white) {
		$color = ImageColorAllocate($img, 0, 0, 0);
	}
	imagepolygon($img, $points, $num_points, $color);
}

function overlib_arrow($x1, $y1, $x2, $y2, $w, $name)
{
	global $tki_attribute, $tki_node1, $tki_node2;
	global $htmlbody;
	global $stats;

	$points = get_arrow_points($x1, $y1, $x2, $y2, $w);

	$olbody = '';
	$ollink = '';
	if (isset($stats)) {
		$olbody = $stats->getolbody($name);
		$ollink = $stats->getollink($name);
	}

	$caption = "$name: ";
	$caption = $stats->getolcaption($name, $caption);
	if (isset($tki_attribute[$tki_node1[$name]]['name'])) {
		$caption .= "<br>A:";
		if ($tki_attribute[$tki_node1[$name]]['name'] == 'HIDDEN') {
			if (isset($tki_attribute[$name]['ifaliasA'])) {
				$caption .= " " . $tki_attribute[$name]['ifaliasA'];
			}
		} else {
			$caption .= $tki_attribute[$tki_node1[$name]]['name'];
			if (isset($tki_attribute[$name]['portA'])) {
				$caption .= " " . $tki_attribute[$name]['portA'];
			}
			if (isset($tki_attribute[$name]['ifaliasA'])) {
				$caption .= " (" . $tki_attribute[$name]['ifaliasA'] . ")";
			}
		}
	}
	if (isset($tki_attribute[$tki_node2[$name]]['name'])) {
		$caption .= "<br>B:";
		if ($tki_attribute[$tki_node2[$name]]['name'] == 'HIDDEN') {
			if (isset($tki_attribute[$name]['ifaliasB'])) {
				$caption .= " " . $tki_attribute[$name]['ifaliasB'];
			}
		} else {
			$caption .= $tki_attribute[$tki_node2[$name]]['name'];
			if (isset($tki_attribute[$name]['portB'])) {
				$caption .= " " . $tki_attribute[$name]['portB'];
			}
			if (isset($tki_attribute[$name]['ifaliasB'])) {
				$caption .= " (" . $tki_attribute[$name]['ifaliasB'] . ")";
			}
		}
	}

	if (($caption == ' - ') && ($olbody == '') && ($ollink == ''))
		return;


	$htmlbody .= '<AREA SHAPE="poly" COORDS="' . implode(",", $points) . '"';
	$htmlbody .= ' ONMOUSEOVER="return overlib(\'' . $olbody . '\', CAPTION, \'' . $caption . '\', CENTER, FIXX, 120, OFFSETY, 32);" onmouseout="return nd();"';
	if ($ollink != '')
		$htmlbody .= ' HREF="' . $ollink . '"';
	$htmlbody .= '>' . "\n";
}

?>
