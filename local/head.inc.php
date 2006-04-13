<?php  if (substr($_SERVER['REQUEST_URI'], 0, 5) == '/inc/') exit; ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
        "http://www.w3.org/TR/1999/REC-html401-19991224/loose.dtd">
<html lang="en">
  <head>
	<title><?php echo $h1.' ('.$project.')'; ?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $level; ?>/layout/intern.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $level; ?>/layout/intern-print.css" media="print">
  </head>
  <body>
<menu>
<li><strong><a href="<?php echo $level; ?>/admin/">Index</a></strong></li>
<li><a href="<?php echo $level; ?>/admin/termine.php">Terminkalender</a></li>
<li><a href="<?php echo $level; ?>/admin/ausgabe.php">Ausgabeeinstellungen</a></li>
</menu>
</div>
<div class="text">

