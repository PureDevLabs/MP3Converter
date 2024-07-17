<?php
	/**
	* This file declares various global (and configurable) constant/variable values.
	*/
	namespace MP3Converter\lib;

	// Config Class
	class Config
	{
		// Public Fields
		public $_audioQualities = array(
			// Array of audio bitrates (i.e., qualities) available for FFmpeg conversions. Values are in kbps units.
			// To skip conversion of SoundCloud downloads (if downloaded files are already MP3), one of these values must be "128"!!
			'low' => 64,
			'medium' => 128,
			'high' => 320
		);
		public static $_authorizedDomains = array(
			// List of domain (and subdomain!) names, separated by commas, that are authorized to run this software.
			// Do NOT prepend domains with 'http://' or 'https://'!!
			// "www" IS a subdomain! If you want "www.yoursite.com" as well as "yoursite.com" to work, then you must include both below!
			// Leave this array empty to allow all domain names to access the software. (NOT RECOMMENDED!!)
			'access.is.forbidden'
		);

		// Constants
		const _SITENAME = 'YourConversionSite.com';
		const _TEMPVIDDIR = 'store/videos/';  // Directory where videos are temporarily stored
		const _SONGFILEDIR = 'store/mp3/';  // Directory where converted MP3 files are stored
		const _FFMPEG = '/usr/bin/ffmpeg';  // Location of FFmpeg on your server. On Linux, this is the absolute path of FFmpeg binary file. (Note: PHP must have permission to access this directory!)
		const _NODEJS = '/usr/bin/node';  // Location of Node.js on your server. On Linux, this is the absolute path of Node.js binary file. (Note: PHP must have permission to access this directory!)
		const _LOGSDIR = 'store/logs/';  // Directory where FFmpeg log files are stored
		const _VOLUME = '256';  // 256 is normal, 512 is roughly 1.5x louder, 768 is 2x louder, 1024 is 2.5x louder
		const _ENABLE_CONCURRENCY_CONTROL = true;  // Set value to 'true' to prevent possible errors when two users simultaneously download & convert the same video. Note: Enabling this feature will use up more server disk space.
		const _PHP_VERSION = 5.6;  // A valid PHP version number (type "float" or "double"), using format "X.X"
	}

?>