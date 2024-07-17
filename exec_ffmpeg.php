<?php
/**
* This file asynchronously runs the FFmpeg command that performs the MP3 conversion.
*/
use MP3Converter\lib\Config;

// Autoload class files
include 'inc/autoload.php';

if (isset($_COOKIE['PHPSESSID']))
{
	session_start();
	session_id($_COOKIE['PHPSESSID']);

	if (isset($_POST['cmd'], $_POST['token']))
	{
		$cmd = urldecode($_POST['cmd']);
		$token = urldecode($_POST['token']);
		if (isset($_SESSION['execFFmpegToken']) && $token == $_SESSION['execFFmpegToken'])
		{
			session_write_close();
			exec($cmd);
		}
	}
}

?>