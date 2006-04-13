<?php

include_once '../../inc/konfiguration.inc.php';

$level = '../..';
$h1 = 'Hilfe: Markdown';

include_once '../../inc/head.inc.php';
include_once '../../inc/markdown.php';

?>
<style type="text/css">

.text {max-width: 45em;}
.text p, .text ul, .text h3, .text h4 {margin-left: 2em; line-height: 1.3em;}
.text h1 {font-size: 130%; margin-left: 0;}
.text h2 {font-size: 120%; font-weight: normal; border-top: 1px solid <?php echo $css_farben[2]; ?>;
	margin-left: 0; margin-top: 1.5em;}
.text h3 {font-size: 110%; margin: 1em 0 0 2em;}
.text {padding: .5em 1em;}
.text pre {margin-left: 4em;}
.text p {margin: 0 0 .5em 2em;}
</style>
<div class="text">
<?php 

$markdown_hilfe = implode("", file('markdown.txt'));
echo markdown($markdown_hilfe);

?>
</div>
<?php 

include_once '../../inc/foot.inc.php';

?>