<?php 

//	for backwards compatibility, to be removed ASAP
	if (!empty($zz_conf['edit_only'])) $zz_conf['access'] = 'edit_only';
	if (!empty($zz_conf['add_only'])) $zz_conf['access'] = 'add_only';
	if (!empty($zz_conf['show'])) $zz_conf['access'] = 'show';


?>