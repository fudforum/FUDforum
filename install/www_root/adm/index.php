<?php 
	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('adm.inc', true);
	list($ses, $usr) = initadm();
	
	header("Location: admglobal.php?"._rsidl); 
?>