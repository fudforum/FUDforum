<?php 
	define('admin_form', 1);

	include_once "GLOBALS.php";
	
	fud_use('db.inc');
	fud_use('static/adm.inc');
	list($ses, $usr) = initadm();
	
	header("Location: admglobal.php?"._rsid); 
?>