<?php include "library.php" ?>
<!-- $Id$ -->
<html>
<head>
<?php // include "popup.php" ?>
<title>TrafficMap</title>
</head>
<body bgcolor="#ffffff">
<p>
<?php
if (is_dir("maps")) {
	print "<h2>List of all known maps</h2>\n<ul>\n";
	$fpdir = opendir("maps");
	$i = 0;
	while ($file = readdir($fpdir)) {
		if (! preg_match("/\.tki$/", $file))
			continue;
		$files[$i] = "$file";
		$i++;
	}
	closedir($fpdir);
	sort($files);
	for ($i = 0; $i < count($files); $i++) {
		$file = $files[$i];
		if (strpos($file, ".") > 0)
			$name = substr($file, 0, strpos($file, "."));
		else
			$name = $file;
		print "<li><a href=trafficmap.php?type=map&map=$name>$name</a>";
		print "&nbsp;<font size=-2><a href=trafficmap.php?type=png&map=$name>(img)</a>";
		print "&nbsp<a href=maps/$file>(source)</a></font>\n";
	}
	print "</ul>\n<p>\n";
}
?>
</body>
</html>
