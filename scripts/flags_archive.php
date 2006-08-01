<?php
$dir = "/home/forum/png/";
$out = "./flags.out";

$fp = fopen($out, "wb");
foreach (glob($dir."*.png") as $v) {
	$str = file_get_contents($v);
	fwrite($fp, str_pad(strlen($str), 10, 0, STR_PAD_LEFT).$str);
}
fclose($fp);