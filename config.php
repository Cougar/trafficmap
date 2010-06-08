<?php

//
// $Id$
//

//
// variables from browser:
//
// map - tki filename without .tki extension
// type = [ "png" | "map" ]
//

//
// optional module to supply overlib pages and traffic load
//
#$statsmodule = "dummy";
$statsmodule = "kqview";

//
// minimum size of image
//
$g_x_min = 50;
$g_y_min = 50;

//
// border width
//
$g_xborder = 80;
$g_yborder = 80;

//
// space between links if there are more than one link between nodes
//
$links_space = 10;

//
// where are TkInEd map files
//
$tkidir = './maps';

//
// where to keep cache file of HTML page. comment out to disable caching
//
$cachedir = '/tmp';

//
// directody of icon files
//
$icondir = './icons';

//
// colormap
//
$scalecolors = array(
	array(-1,     0,   0,   0),	# link down -> black
	array(0.5,    255, 255, 255),	# almost no traffic -> white
	array(10,   140,   0, 255),
	array(25,    32,  32, 255),
	array(40,     0, 192, 255),
	array(55,     0, 240,   0),
	array(65,   240, 240,   0),
	array(80,   255, 64,   0),
	array(95,  255,   0,   0)	# 95% best effort (5% network control)
);

//
// width of link arrow
//
$linkwidths = array(
	array(   155000000, 1),	# STM-1
	array(  1000000000, 2),	# GE
	array(  2500000000, 3),	# STM-16
	array( 10000000000, 4),	# 10G
	array(100000000000, 9),	# more than 100 Gbps - DEBUG
);

?>
