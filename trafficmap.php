<?php
#
#    Trafficmap
#
#    Display TkInEd file with link loads
#
#    $Id$
#
#    Copyright (C) 2007,2008 Cougar <cougar@random.ee>
#                              http://www.version6.net/
#
#    This program is free software; you can redistribute it and/or modify
#    it under the terms of the GNU General Public License as published by
#    the Free Software Foundation; either version 2 of the License, or
#    (at your option) any later version.
#
#    This program is distributed in the hope that it will be useful,
#    but WITHOUT ANY WARRANTY; without even the implied warranty of
#    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#    GNU General Public License for more details.
#
#    You should have received a copy of the GNU General Public License
#    along with this program; if not, write to the Free Software
#    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#

require "config.php";
require "library.php";

if (isset($cache_filename)
    && file_exists($cache_filename)
    && (filemtime($cache_filename) > filemtime($tki_filename))
    && (floor(filemtime($cache_filename) / 300) == floor(time() / 300))) {
	Header("Cache-Control: no-cache, must-revalidate");
	Header("Pragma: no-cache");
	if ($_GET['type'] == "map") {
		Header("Content-type: text/html");
		echo "<!-- start cached copy -->\n";
	} else if ($_GET['type'] == "png") {
		Header("Content-type: image/png");
	} else {
		die ("Unknown map type");
	}
	$fp = fopen($cache_filename, 'r');
	$content = fread($fp, filesize($cache_filename));
	echo $content;
	exit;
}

$img = '';

$htmlbody = '';

/*
 * read_tki
 */

$fp = fopen($tki_filename, "r") or die ("Cannot open $tki_filename");
while(! feof($fp) ) {
	$line = ltrim(Chop(fgets($fp, 4096)));
	if (preg_match("/^#/", $line) || preg_match("/^$/", $line)) {
		continue;
	} else if (preg_match("/^exec tkined /", $line)) {
		continue;
	} else if (preg_match("/^ined page /", $line)) {
		continue;
	} else if (preg_match("/^set (\S+) \[ ined -noupdate create TEXT \{(.+)\} \]$/", $line, $matches)) {
		$tki_type[$matches[1]] = "TEXT";
		$tki_label[$matches[1]] = $matches[2];
	} else if (preg_match("/^set (\S+) \[ ined -noupdate create TEXT (\S+) \]$/", $line, $matches)) {
		$tki_type[$matches[1]] = "TEXT";
		$tki_label[$matches[1]] = $matches[2];
	} else if (preg_match("/^set (\S+) \[ ined -noupdate create NODE \]$/", $line, $matches)) {
		$tki_type[$matches[1]] = "NODE";
	} else if (preg_match("/^set (\S+) \[ ined -noupdate create LINK [\$](\S+) [\$](\S+)  \]$/", $line, $matches)) {
		if ($tki_type[$matches[2]] != "NODE") {
			error_log("ERROR: src ". $matches[2] . " not defined for " . $matches[1] . " yet");
		}
		if ($tki_type[$matches[3]] != "NODE") {
			error_log("ERROR: dst ". $matches[3] . " not defined for " . $matches[1] . " yet");
		}
		$tki_type[$matches[1]] = "LINK";
		$tki_node1[$matches[1]] = $matches[2];
		$tki_node2[$matches[1]] = $matches[3];
		if (! isset($tki_linkcount[$matches[2]][$matches[3]]))
			$tki_linkcount[$matches[2]][$matches[3]] = 0;
		$tki_linkcount[$matches[2]][$matches[3]]++;
		if (! isset($tki_linkcount[$matches[3]][$matches[2]]))
			$tki_linkcount[$matches[3]][$matches[2]] = 0;
		$tki_linkcount[$matches[3]][$matches[2]]++;
	} else if (preg_match("/^set (\S+) \[ ined -noupdate create NETWORK ([\d\.]+) ([\d\.]+) ([\d\.]+) ([\d\.]+) \s*\]$/", $line, $matches)) {
		$tki_type[$matches[1]] = "NETWORK";
		$tki_x2[$matches[1]] = $matches[4];
		$tki_y2[$matches[1]] = $matches[5];
	} else if (preg_match("/^ined -noupdate move [\$](\S+) ([\d\.]+) ([\d\.]+)$/", $line, $matches)) {
		$object = $matches[1];
		$object_x = $matches[2];
		$object_y = $matches[3];
		$tki_posx[$object] = $object_x;
		$tki_posy[$object] = $object_y;
		if (preg_match("/^reference/", $object)) {
			continue;
		}
		if ($object_x < $g_xmin)
			$g_xmin = $object_x;
		if (isset($tki_x2[$object]) && (($object_x + $tki_x2[$object]) < $g_xmin))
			$g_xmin = $object_x + $tki_x2[$object];
		if ($object_x > $g_xmax)
			$g_xmax = $object_x;
		if (isset($tki_x2[$object]) && (($object_x + $tki_x2[$object]) > $g_xmax))
			$g_xmax = $object_x + $tki_x2[$object];
		if ($object_y < $g_ymin)
			$g_ymin = $object_y;
		if ($object_y > $g_ymax)
			$g_ymax = $object_y;
	} else if (preg_match("/^ined -noupdate font [\$](\S+) (.+)$/", $line, $matches)) {
		$tki_font[$matches[1]] = $matches[2];
	} else if (preg_match("/^ined -noupdate color [\$](\S+) (.+)$/", $line, $matches)) {
		$tki_color[$matches[1]] = $matches[2];
	} else if (preg_match("/^ined -noupdate icon [\$](\S+) (.+)$/", $line, $matches)) {
		$tki_icon[$matches[1]] = $matches[2];
	} else if (preg_match("/^ined -noupdate name [\$](\S+) (.+)$/", $line, $matches)) {
		if (preg_match("/^\{(.*)\}$/", $matches[2], $matches2))
			$matches[2] = $matches2[1];
		$tki_name[$matches[1]] = $matches[2];
		$tki_attribute[$matches[1]]['name'] = $matches[2];
	} else if (preg_match("/^ined -noupdate address [\$](\S+) (.+)$/", $line, $matches)) {
		$tki_address[$matches[1]] = $matches[2];
		$tki_attribute[$matches[1]]['address'] = $matches[2];
	} else if (preg_match("/^ined -noupdate attribute [\$](\S+) (begin|end) (.+)$/", $line, $matches)) {
		if (preg_match("/^\{.* (.*)\}$/", $matches[3], $matches2))
			$matches[3] = $matches2[1];
		$tki_attribute[$matches[1]][$matches[2]] = $matches[3];
	} else if (preg_match("/^ined -noupdate attribute [\$](\S+) (\S+) (.+)$/", $line, $matches)) {
		if ($matches[2] == "ignore") {
			unset($tki_type[$matches[1]]);
			unset($tki_label[$matches[1]]);
			unset($tki_posx[$matches[1]]);
			unset($tki_posy[$matches[1]]);
			unset($tki_name[$matches[1]]);
			unset($tki_attribute[$matches[1]]);
			continue;
		}
		if (preg_match("/^\{(.*)\}$/", $matches[3], $matches2))
			$matches[3] = $matches2[1];
		$tki_attribute[$matches[1]][$matches[2]] = $matches[3];
	} else if (preg_match("/^ined -noupdate label [\$](\S+) (.+)$/", $line, $matches)) {
		$tki_label[$matches[1]] = $matches[2];
	} else if (preg_match("/^set group/", $line)) {
		continue;
	} else if (preg_match("/^set interpreter/", $line)) {
		continue;
	} else if (preg_match("/^ined send /", $line)) {
		continue;
	} else if (preg_match("/^ined -noupdate move /", $line)) {
		continue;
	} else if (preg_match("/^ined -noupdate expand /", $line)) {
		continue;
	} else if (preg_match("/^set text/", $line)) {
		continue;
	} else {
//		echo "<h1>illegal line: [$line]</h1>\n";
	}
}

if (isset($tki_attribute['reference0']['ICONSCALE']))
 	$iconscale = $tki_attribute['reference0']['ICONSCALE'];

if (isset($tki_attribute['reference0']['MAPSCALE'])) {
 	$mapscale = $tki_attribute['reference0']['MAPSCALE'];
 	$iconscale *=$mapscale;
}

if ($_GET['type'] == "png") {
	/*
	 * create_image
	 */




	$g_x = ($g_xmax - $g_xmin) * $mapscale + 2 * $g_xborder;
	if ($g_x < $g_x_min)
		$g_x = $g_x_min;
	$g_y = ($g_ymax - $g_ymin) * $mapscale + 2 * $g_yborder;
	if ($g_y < $g_y_min)
		$g_y = $g_y_min;

	$img = @ImageCreateTrueColor($g_x, $g_y)
		or die ("Cannot Initialize new GD image stream");

#	ImageAlphaBlending($img, 1);
	ImageAntiAlias($img, 1);
#	ImageInterlace($img, 1);

	$c_white = ImageColorAllocate($img, 255, 255, 255);
	$c_black = ImageColorAllocate($img, 0, 0, 0);
	ImageFill($img, 0, 0, $c_white);

	/*
	 * read_rgb_table
	 */
	$fp = fopen("rgb.txt", "r") or die ("cannot read rgb.txt");
	while(! feof($fp) ) {
		$line = ltrim(Chop(fgets($fp, 4096)));
		if (! preg_match("/^[0-9]+ +[0-9]+ +[0-9]+ +/", $line))
			continue;
		list ($color_r, $color_g, $color_b, $color_name) = split ('[ 	]+', $line);
		$color_name = strtolower($color_name);
		$rgb_r[$color_name] = $color_r;
		$rgb_g[$color_name] = $color_g;
		$rgb_b[$color_name] = $color_b;
	}
	fclose($fp);

} else if ($_GET['type'] == "map") {
	$htmlbody .= '<!-- $Id$ -->
<HTML>
<HEAD>
<TITLE>TrafficMap</TITLE>
<LINK REL="shortcut icon" HREF="trafficmap.ico">
</HEAD>
<BODY BGCOLOR=#FFFFFF LEFTMARGIN=0 TOPMARGIN=0 MARGINWIDTH=0 MARGINHEIGHT=0>
<DIV id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></DIV>
<SCRIPT language="JavaScript" src="overlib_mini.js"><!-- overLIB (c) Erik Bosrup --></SCRIPT>
<IMG SRC="' . $_SERVER['PHP_SELF'] . '?type=png&map=' . $sterile_map . '" BORDER=0 ALT="" USEMAP="#trafficmap_' . $sterile_map . '">
<BR><BR><BR><BR><BR><BR><BR><BR><BR><BR>
<BR><BR><BR><BR><BR><BR><BR><BR><BR><BR>
<BR><BR><BR><BR><BR><BR><BR><BR><BR><BR>
<BR><BR><BR><BR><BR><BR><BR><BR><BR><BR>
<MAP NAME="trafficmap_' . $sterile_map . '">
';
}

	if (class_exists('STATS'))
		$stats = new STATS;

	$types = $tki_type;
	while (list($name, ) = each ($types)) {
		if ($tki_type[$name] != "LINK")
			continue;
		$node1 = $tki_node1[$name];
		$node2 = $tki_node2[$name];

		if ($tki_type[$node1] == "" || $tki_type[$node2] == "") {
			continue;
		}

		$linkcount = $tki_linkcount[$node1][$node2] + 0;
		if (! isset($drawedlinks[$node1][$node2]))
			$drawedlinks[$node1][$node2] = 0;
		$drawedlinks[$node1][$node2] ++;
		if (! isset($drawedlinks[$node2][$node1]))
			$drawedlinks[$node2][$node1] = 0;
		$drawedlinks[$node2][$node1] ++;
		$drawinglink = $drawedlinks[$node1][$node2];
		$x1 = X($tki_posx[$node1]);
		$x2 = X($tki_posx[$node2]);
		$y1 = Y($tki_posy[$node1]);
		$y2 = Y($tki_posy[$node2]);

		if ($tki_type[$node1] == "NETWORK") {
			if ($tki_x2[$node1] > 0)
				$x1 = $x2;
			if ($tki_y2[$node1] > 0)
				$y1 = $y2;

			$xn1 = X($tki_posx[$node1]);
			$xn2 = X($tki_posx[$node1]);
			$yn1 = Y($tki_posy[$node1]);
			$yn2 = Y($tki_posy[$node1]);
			if ($tki_x2[$node1] > 0)
				$xn2 += $tki_x2[$node1];
			if ($tki_y2[$node1] > 0)
				$yn2 += $tki_y2[$node1];
			if ($x1 < $xn1)
				$x1 = $xn1;
			if ($x1 > $xn2)
				$x1 = $xn2;
		}
		if ($tki_type[$node2] == "NETWORK") {
			if ($tki_x2[$node2] > 0)
				$x2 = $x1;
			if ($tki_y2[$node2] > 0)
				$y2 = $y1;

			$xn1 = X($tki_posx[$node2]);
			$xn2 = X($tki_posx[$node2]);
			$yn1 = Y($tki_posy[$node2]);
			$yn2 = Y($tki_posy[$node2]);
			if ($tki_x2[$node2] > 0)
				$xn2 += $tki_x2[$node2];
			if ($tki_y2[$node2] > 0)
				$yn2 += $tki_y2[$node2];
			if ($x2 < $xn1)
				$x2 = $xn1;
			if ($x2 > $xn2)
				$x2 = $xn2;

		}

		$width = 3;
		if (isset($tki_attribute[$name]['speed'])) {
			$width = ifspeed2width($tki_attribute[$name]['speed']);
		}
#		$linksshown[$tki_attribute[$name]['speed']]++;
#		if (isset($tki_attribute[$name]['linkcount']))
#			$width *= $tki_attribute[$name]['linkcount'] * 0.75;

		# put links closer if possible to increase number of links
		# between two nodes (up 10 in Metroo setup)
		$tmp_links_space = $links_space;
		if (! isset($tki_attribute[$node1]['virtual']) && ! isset($tki_attribute[$node2]['virtual'])) {
			$tmp_links_space = $links_space / 2;
		}

		if ($_GET['type'] == 'png') {
			if (isset($stats)) {
				$load = $stats->getloads($name);
			} else {
				$load[0] = 0;
				$load[1] = 0;
			}
			plot_link_png($x1, $y1, $x2, $y2, $load[0], $load[1], $width, $linkcount, $drawinglink, $name, $tmp_links_space);
		} else if ($_GET['type'] == 'map') {
			plot_link_map($x1, $y1, $x2, $y2, $width, $linkcount, $drawinglink, $name, $tmp_links_space);
		}
	}

	$types = $tki_type;

if ($_GET['type'] == "png") {
	while (list($name, ) = each ($types)) {
		if ($tki_type[$name] != "NETWORK")
			continue;
		if (isset($tki_color[$name]))
			$color = ImageColorAllocate($img, $rgb_r[$tki_color[$name]], $rgb_g[$tki_color[$name]], $rgb_b[$tki_color[$name]]);
		else
#			$color = ImageColorAllocate($img, 0, 0, 0);
			$color = ImageColorAllocate($img, 255, 255, 255);

		$x1 = X($tki_posx[$name]);
		$x2 = X($tki_posx[$name]);
		$y1 = Y($tki_posy[$name]);
		$y2 = Y($tki_posy[$name]);
		if ($tki_x2[$name] > 0)
			$x2 += $tki_x2[$name];
		if ($tki_y2[$name] > 0)
			$y2 += $tki_y2[$name];

		ImageLine($img, $x1, $y1, $x2, $y2, $color);
		ImageLine($img, $x1 - 1, $y1, $x2 - 1, $y2, $color);
		ImageLine($img, $x1 + 1, $y1, $x2 + 1, $y2, $color);
		ImageLine($img, $x1, $y1 - 1, $x2, $y2 - 1, $color);
		ImageLine($img, $x1, $y1 + 1, $x2, $y2 + 1, $color);

		if ((isset($tki_label[$name])) && (isset($tki_attribute[$name][$tki_label[$name]]))) {
#			$color = ImageColorAllocate($img, 0, 0, 0);
			$color = ImageColorAllocate($img, 255, 255, 255);
			plot_bg_center_string($img, 2, $x1 + ($x2 - $x1) / 2, $y2 + 1, $tki_attribute[$name][$tki_label[$name]], $color, $c_white);
		}
	}

	$unknownimg = @ImageCreateFromPNG("unknown.png");
	$unknownw = ImageSX($unknownimg);
	$unknownh = ImageSY($unknownimg);

	$types = $tki_type;
	$labelcolor = ImageColorAllocate($img, 0, 0, 0);
#	$labelcolor = ImageColorAllocate($img, 255, 255, 255);
	while (list($name, ) = each ($types)) {
		if ($tki_type[$name] != "NODE")
			continue;
		if (isset($tki_attribute[$name]['virtual']))
			continue;
		$filename = get_icon_filename("png", $tki_icon[$name], "cisco");
		$img1 = @ImageCreateFromPNG($filename);

		$w = ImageSX($img1);
		$h = ImageSY($img1);
#		if (isset($tki_color[$name])) {
#			ImageColorDeAllocate($img1, 1);
#			ImageColorAllocate($img1, $rgb_r[$tki_color[$name]], $rgb_g[$tki_color[$name]], $rgb_b[$tki_color[$name]]);
#		}
#		ImageCopy($img, $img1, X($tki_posx[$name] - $w/2), Y($tki_posy[$name] - $h/2), 0, 0, $w, $h);

ImageCopyResampled($img, $img1, X($tki_posx[$name]) - $w*$iconscale/2, Y($tki_posy[$name]) - $h*$iconscale/2, 0, 0, $w*$iconscale, $h*$iconscale, $w, $h);
		if (isset($tki_attribute[$name]['SNMP']) && ($tki_attribute[$name]['SNMP'] == "NO")) {
			ImageCopy($img, $unknownimg, X($tki_posx[$name]) - $unknownw/2, Y($tki_posy[$name]) - $unknownh/2, 0, 0, $unknownw, $unknownh);
		}
		if ((isset($tki_label[$name])) && (isset($tki_attribute[$name][$tki_label[$name]]))) {
			if (isset($label)) {
				plot_bg_center_string($img, 2, X($tki_posx[$name]), Y($tki_posy[$name]) + $h*$iconscale/2 + 1, $tki_attribute[$name][$label], $labelcolor, $c_white);
			} else {
				plot_bg_center_string($img, 2, X($tki_posx[$name]), Y($tki_posy[$name]) + $h*$iconscale/2 + 1, $tki_attribute[$name][$tki_label[$name]], $labelcolor, $c_white);
			}
		}
	}
	plot_bg_right_string($img, 2, $g_x, $g_y - 14, date(DATE_ISO8601, time()), $labelcolor, $c_white);
}

if (0) {
$tmpimg = imagecreatetruecolor(800, 100);
$text = 'Test image, link loads are random !!!';
$font = 'DejaVuSans.ttf';

$fontsize = 24;
$x = 10;
$y = 50;
$space = 10;
$box = imagettfbbox ($fontsize, 0, $font, $text);
$box[0] += $x - $space;
$box[1] += $y + $space;
$box[2] += $x + $space + 1;
$box[3] += $y + $space;
$box[4] += $x + $space + 1;
$box[5] += $y - $space;
$box[6] += $x - $space;
$box[7] += $y - $space;
$black = imagecolorallocate($tmpimg,0,0,0);
$white = imagecolorallocate($tmpimg,255,255,255);
imagefilledPolygon($tmpimg, $box, 4, $white);
imagePolygon($tmpimg, $box, 4, $black);
$box2 = imagettftext($tmpimg, $fontsize, 0, $x, $y, $black, $font, $text);
ImageCopyMerge($img, $tmpimg, 20, 100, $box[6], $box[7] , $box[2] - $box[0] + 1, $box[1] - $box[5] + 1, 80);
} # if (0)

Header("Cache-Control: no-cache, must-revalidate");
Header("Pragma: no-cache");
if ($_GET['type'] == "png") {
	Header("Content-type: image/png");
	ImagePng($img, NULL, 9);
	if (isset($cache_filename)) {	# cache image file
		ImagePng($img, $cache_filename , 9);
	}
	ImageDestroy($img);
} else if ($_GET['type'] == "map") {
	Header("Content-type: text/html");
	$htmlbody .= '</MAP>
</BODY>
</HTML>
';
	echo $htmlbody;

	if (isset($cache_filename)) {	# cache HTML file
		$fp = fopen($cache_filename, 'w+');
		ftruncate($fp, 0);
		fwrite($fp, $htmlbody);
		fclose($fp);
	}
} else {
	die ("Unknown map type");
}
?>
