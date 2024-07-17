<?php
/**
* This file is periodically polled via AJAX (from index.php) to determine and return FFmpeg conversion progress.
* Conversion progress is gleaned from the log file associated with a given FFmpeg process.
*/
use MP3Converter\lib\Config;

// Autoload class files
include 'inc/autoload.php';

// Initialize variables
$newLogLength = 0;
$progress = 0;
$conversionSuccess = 2;
$error = 2;

if (isset($_POST['uniqueId'], $_POST['logLength'], $_POST['mp3File']))
{
	$uniqueId = $_POST['uniqueId'];
	$logLength = $_POST['logLength'];
	$mp3File = urldecode($_POST['mp3File']);
	$logFile = realpath(Config::_LOGSDIR . $uniqueId .".txt");
	// If the FFmpeg log file exists...
	if (is_file($logFile))
	{
		$count = 0;
		// Check and help ensure that log file has been updated since the last AJAX request
		while (filesize($logFile) == $logLength && $count < 500)
		{
			$count++;
			clearstatcache();
			time_nanosleep(0, 10000000);
		}
		// Get contents of log file
		$log = file_get_contents($logFile);
		$file_size = filesize($logFile);
		if (preg_match('/(Duration: )(\d\d):(\d\d):(\d\d\.\d\d)/i', $log, $matches) == 1)
		{
			// Calculate total duration of converted MP3 file
			$totalTime = ((int)$matches[2] * 60 * 60) + ((int)$matches[3] * 60) + (float)$matches[4];
			// Retrieve all conversion progress updates to log file
			$numTimes = preg_match_all('/(time=)(.+?)(\s)/i', $log, $times);
			if ($numTimes > 0)
			{
				// Read the last progress update and get the duration of video that has been converted so far
				$lastTime = end($times[2]);
				if (preg_match('/(\d\d):(\d\d):(\d\d\.\d\d)/', $lastTime, $timeParts) == 1)
				{
					$lastTime = ((int)$timeParts[1] * 60 * 60) + ((int)$timeParts[2] * 60) + (float)$timeParts[3];
				}
				$currentTime = (float)$lastTime;
				// Determine conversion progress by dividing duration converted by total duration
				$progress = round(($currentTime / $totalTime) * 100);
				if ($progress < 100 && preg_match('/muxing overhead/i', $log) != 1)
				{
					// If conversion is not complete, set new length of log file
					$newLogLength = $file_size;
				}
				else
				{
					// If conversion is complete, delete log and video files, check for existence of MP3, and announce conversion success
					$progress = 100;
					unlink($logFile);
					if (is_file(realpath(Config::_TEMPVIDDIR . $uniqueId .'.mkv')))
					{
						unlink(realpath(Config::_TEMPVIDDIR . $uniqueId .'.mkv'));
					}
					if (is_file(realpath(Config::_SONGFILEDIR . $mp3File)))
					{
						$conversionSuccess = 1;  // Conversion success!
					}
				}
			}
			else
			{
				$error = 1;  // There are no progress updates yet!
			}
		}
		else
		{
			$error = 1;  // FFmpeg does not know total duration yet!
		}
	}
	else
	{
		$error = 1;  // FFmpeg log file does not exist!
	}
}
// Return FFmpeg conversion progress, status, and error code to AJAX code in index.php
echo $newLogLength . "|" . $progress . "|" . $conversionSuccess . "|" . $error;

?>