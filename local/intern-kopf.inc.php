<?php
/*
	Zugzwang Project
	Interner Bereich: Seitenkopf

	(c) 2006 Gustaf Mossakowski, <gustaf@koenige.org>
*/

//$page['url'] = str_replace($basis_url, '', $_SERVER['REQUEST_URI']);
$page['url'] = $_SERVER['REQUEST_URI'];
$page['deep'] = str_repeat('../', (substr_count('/'.$page['url'], '/') -2));
if (!$page['deep']) $page['deep'] = './';

header('Content-Type: text/html;charset=utf-8');
ini_set('error_reporting', E_ALL);

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/1999/REC-html401-19991224/loose.dtd">
<html lang="<?php echo $zz_conf['language'];?>">
  <head>
	<title><?php echo $zz_conf['title'].' ('.$zz_conf['project'].')'; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $page['deep']; ?>_layout/intern/zzform.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $page['deep']; ?>_layout/intern/zzform-colours.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $page['deep']; ?>_layout/intern/zzform-extra.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $page['deep']; ?>_layout/intern/zzform-print.css" media="print">
  </head>
  <body>
<ul id="menu">
<li><strong><a href="./">Menü</a></strong></li>
<li><a href="seiten">Seiten</a></li>
<li><a href="projekte">Projekte</a></li>
<li><a href="dateien">Dateien</a></li>
<li><a href="menus">Navigationsmenüs</a></li>
<li style="float: right; margin-top: -1em;"><a href="/login/?logout">Logout</a></li>
</ul>
<div class="text">
