<?php
	/**
	* This file and class contains all logic pertaining to the actual download and MP3 conversion of videos.
	*/
	namespace MP3Converter\lib;

	// Conversion Class
	class VideoConverter extends Config
	{
		// Private Fields
		private $_convertedFileQuality = '';
		private $_songFileName = '';
		private $_flvUrls = array();
		private $_tempVidFileName;
		private $_uniqueID = '';
		private $_percentVidDownloaded = 0;
		private $_curlResource;
		private $_currentVidHost = '';
		private $_vidInfo = array(
			'title' => '?????',
			'thumb_preview' => 'http://img.youtube.com/vi/oops/1.jpg'
		);
		private $_extractor = null;
		private $_videoHosts = array();
		private $_skipConversion = false;

		#region Public Methods
		/**
		* Instantiate class, set session token, register available extractors, and initialize class variables.
		*
		* @param string $videoPageUrl The user-supplied video page URL
		* @param string $mp3Quality The bitrate (quality) of MP3 chosen by user
		* @return void
		*/
		function __construct($videoPageUrl, $mp3Quality)
		{
			if (isset($_SESSION))
			{
				$this->_uniqueID = (!isset($_SESSION[parent::_SITENAME])) ? time() . "_" . uniqid('', true) : $_SESSION[parent::_SITENAME];
				$_SESSION[parent::_SITENAME] = (!isset($_SESSION[parent::_SITENAME])) ? $this->_uniqueID : $_SESSION[parent::_SITENAME];
				$_SESSION['execFFmpegToken'] = (!isset($_SESSION['execFFmpegToken'])) ? uniqid($this->_uniqueID, true) : $_SESSION['execFFmpegToken'];
				$this->RegisterExtractors();
				if (!empty($videoPageUrl) && !empty($mp3Quality))
				{
					$this->SetCurrentVidHost($videoPageUrl);
					$this->SetConvertedFileQuality($mp3Quality);
					$this->SetExtractor($this->GetCurrentVidHost());
					$extractor = $this->GetExtractor();
					if (!is_null($extractor))
					{
						$this->SetVidInfo($extractor->RetrieveVidInfo($videoPageUrl));
					}
				}
			}
			else
			{
				die('Error!: Session must be started in the calling file to use this class.');
			}
		}

		/**
		* Prepare and initiate the download of video.
		*
		* @return bool Download success or failure
		*/
		function DownloadVideo()
		{
			$extractor = $this->GetExtractor();
			if (!is_null($extractor))
			{
				$this->SetConvertedFileName();
				$this->SetVidSourceUrls();
				if ($this->GetConvertedFileName() != '' && count($this->GetVidSourceUrls()) > 0)
				{
					return $this->SaveVideo($this->GetVidSourceUrls());
				}
			}
			return false;
		}

		/**
		* Generate the FFmpeg command and send it to exec_ffmpeg.php to be executed.
		*
		* @return void
		*/
		function GenerateMP3()
		{
			$audioQuality = $this->GetConvertedFileQuality();
			$qualities = $this->GetAudioQualities();
			$quality = (in_array($audioQuality, $qualities)) ? $audioQuality : $qualities['medium'];
			$exec_string = parent::_FFMPEG . ' -i ' . escapeshellarg($this->GetTempVidFileName()) . ' -vol ' . parent::_VOLUME . ' -y -acodec libmp3lame -ab ' . escapeshellarg($quality . 'k') . ' ' . escapeshellarg($this->GetConvertedFileName()) . ' 2> ' . parent::_LOGSDIR . $this->GetUniqueID() . '.txt';
			if (!is_dir(realpath(parent::_LOGSDIR))) mkdir(parent::_LOGSDIR, 0777);
			if (is_file(realpath(parent::_LOGSDIR . $this->GetUniqueID() . ".txt"))) unlink(realpath(parent::_LOGSDIR . $this->GetUniqueID() . ".txt"));  // If previous conversion was abandoned, remove corresponding log file with same file name, if it exists, to prevent subsequent conversion failure!
			$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443;
			if (!$isHttps && isset($_SERVER['HTTP_CF_VISITOR']))
			{
				$cfJson = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
				if (json_last_error() == JSON_ERROR_NONE)
				{
					$isHttps = !empty($cfJson) && current($cfJson) == 'https';
				}
			}
			$protocol = ($isHttps) ? "https://" : "http://";
			$ffmpegExecUrl = preg_replace('/(([^\/]+?)(\.php))$/', "exec_ffmpeg.php", $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
			$postData = "cmd=" . urlencode($exec_string) . "&token=" . urlencode($_SESSION['execFFmpegToken']);
			$strCookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $ffmpegExecUrl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_COOKIE, $strCookie);
			curl_exec($ch);
			curl_close($ch);
		}

		/**
		* Prepare MP3 file name and prompt user for MP3 download.
		*
		* @param string $file MP3 file name
		* @return void
		*/
		function DownloadMP3($file)
		{
			$filepath = parent::_SONGFILEDIR . urldecode($file);
			if ($this->ValidateDownloadFileName($filepath))
			{
				$filename = urldecode($file);
				if (parent::_ENABLE_CONCURRENCY_CONTROL)
				{
					$filename = preg_replace('/((_uuid-)(\w{13})(\.mp3))$/', "$4", $filename);
				}
				header('Content-Type: audio/mpeg3');
				header('Content-Length: ' . filesize($filepath));
				header('Content-Disposition: attachment; filename="'.$filename.'"');
				ob_clean();
				flush();
				readfile($filepath);
				die();
			}
			else
			{
				$redirect = explode("?", $_SERVER['REQUEST_URI']);
				header('Location: ' . $redirect[0]);
			}
		}

		/**
		* Extract video ID.
		*
		* @param string $vidUrl Video URL
		* @return string Video ID
		*/
		function ExtractVideoId($vidUrl)
		{
			$id = '';
			$url = trim($vidUrl);
			$urlQueryStr = parse_url($url, PHP_URL_QUERY);
			if ($urlQueryStr !== false && !empty($urlQueryStr))
			{
				parse_str($urlQueryStr, $params);
				if (isset($params['v']) && !empty($params['v']))
				{
					$id = $params['v'];
				}
				else
				{
					$url = preg_replace('/(\?' . preg_quote($urlQueryStr, '/') . ')$/', "", $url);
					$id = trim(strrchr(trim($url, '/'), '/'), '/');
				}
			}
			else
			{
				$id = trim(strrchr(trim($url, '/'), '/'), '/');
			}
			return $id;
		}

		/**
		* Flush output buffer at various points during download/conversion process.
		*
		* @return void
		*/
		function FlushBuffer()
		{
			if (ob_get_length() > 0) ob_end_flush();
			if (ob_get_length() > 0) ob_flush();
			flush();
		}
		#endregion

		#region Private "Helper" Methods
		/**
		* Find and load all available video/audio site extractors.
		*
		* @return void
		*/
		private function RegisterExtractors()
		{
			$hosts = array();
			$iterator = new \DirectoryIterator(__DIR__ . DIRECTORY_SEPARATOR . 'extractors' . DIRECTORY_SEPARATOR);
			while ($iterator->valid())
			{
				$fname = $iterator->getFilename();
				if ($fname != '.' && $fname != '..' && $fname != "Extractor.php")
				{
					$extractorName = __NAMESPACE__ . '\\extractors\\' . current(explode(".", $fname));
					if (class_exists($extractorName))
					{
						$extractor = new $extractorName($this);
						$hosts[$extractorName] = $extractor->GetParams();
					}
				}
				$iterator->next();
			}
			if (!empty($hosts))
			{
				ksort($hosts);
				$count = 0;
				foreach ($hosts as $host)
				{
					$this->_videoHosts[++$count] = $host;
				}
			}
		}
		
		/**
		* Validate MP3 file name to prevent unauthorized downloads.
		*
		* @param string $filepath MP3 file path
		* @return bool Validation success or failure
		*/		
		private function ValidateDownloadFileName($filepath)
		{
			$isValid = false;
			$fullFilepath = realpath($filepath);
			if ($fullFilepath !== false && $fullFilepath != $filepath && is_file($fullFilepath))
			{
				$appRoot = preg_replace('/([^' . preg_quote(DIRECTORY_SEPARATOR, "/") . ']+)$/', "", $_SERVER['PHP_SELF']);
				$pathBase = rtrim(realpath($_SERVER['DOCUMENT_ROOT']), DIRECTORY_SEPARATOR) . $appRoot;
				$safePath = preg_replace('/^(' . preg_quote($pathBase, '/') . ')/', "", $fullFilepath);
				if ($safePath != $fullFilepath && preg_match('/^(' . preg_quote(preg_replace('/\//', DIRECTORY_SEPARATOR, parent::_SONGFILEDIR), '/') . ')/', $safePath) == 1)
				{
					$fileExt = pathinfo($fullFilepath, PATHINFO_EXTENSION);
					$isValid = $fileExt == "mp3";
				}
			}
			return $isValid;
		}		

		/**
		* cURL callback function that updates download progress.
		*
		* @param resource $curlResource cURL resource handle
		* @param int $downloadSize Total number of bytes expected to be downloaded
		* @param int $downloaded Number of bytes downloaded so far
		* @param int $uploadSize Total number of bytes expected to be uploaded
		* @param int $uploaded Number of bytes uploaded so far
		* @return void
		*/
		private function UpdateVideoDownloadProgress($curlResource, $downloadSize, $downloaded, $uploadSize, $uploaded)
		{
			$httpCode = curl_getinfo($curlResource, CURLINFO_HTTP_CODE);
			if ($httpCode == "200" && $downloadSize > 0)
			{
				$percent = round($downloaded / $downloadSize, 2) * 100;
				if ($percent > $this->_percentVidDownloaded)
				{
					$this->_percentVidDownloaded++;
					$this->OutputDownloadProgress($percent, true);
				}
			}
		}

		/**
		* cURL callback function that updates download progress for PHP 5.4 and below.
		* Deprecated - May be removed in future versions!
		*
		* @param int $downloadSize Total number of bytes expected to be downloaded
		* @param int $downloaded Number of bytes downloaded so far
		* @param int $uploadSize Total number of bytes expected to be uploaded
		* @param int $uploaded Number of bytes uploaded so far
		* @return void
		*/
		private function LegacyUpdateVideoDownloadProgress($downloadSize, $downloaded, $uploadSize, $uploaded)
		{
			$this->UpdateVideoDownloadProgress($this->_curlResource, $downloadSize, $downloaded, $uploadSize, $uploaded);
		}
		
		/**
		* Output download progress/status to progress bar.
		*
		* @param int $percent Current download progress as a percentage
		* @param bool $isRealTime Show real-time progress with percent downloaded or only an animated bar
		* @return void
		*/		
		private function OutputDownloadProgress($percent, $isRealTime)
		{
			echo '<script type="text/javascript">mpc_updateVideoDownloadProgress("'. $percent .'", ' . (($isRealTime) ? 'true' : 'false') . ');</script>';
			$this->FlushBuffer();
		}		

		/**
		* Save video to "videos" directory.
		*
		* @param array $urls Direct links to available videos on remote server
		* @return bool Download success or failure
		*/		
		private function SaveVideo(array $urls)
		{
			$extractor = $this->GetExtractor();
			$vidHost = $this->GetCurrentVidHost();
			$vidHosts = $this->GetVideoHosts();
			$vidInfo = $this->GetVidInfo();
			$this->_skipConversion = $skipConversion = $vidHost == 'SoundCloud' && $this->GetConvertedFileQuality() == '128';
			if (!$skipConversion) $this->SetTempVidFileName();
			$filename = (!$skipConversion) ? $this->GetTempVidFileName() : $this->GetConvertedFileName();
			$success = false;
			$vidCount = -1;
			while (!$success && ++$vidCount < count($urls))
			{
				$this->_percentVidDownloaded = 0;
				$isPlaylist = preg_match('/^((\.m3u8)(.*))$/', strrchr($urls[$vidCount], ".")) == 1;
				$ffmpegOutput = array();
				if ($isPlaylist)
				{
					$useNative = false;
					array_walk($vidHosts, function($vh, $key) use(&$useNative, $vidHost) {if ($vh['name'] == $vidHost) $useNative = isset($vh['enable_native_playlist_download']) && $vh['enable_native_playlist_download'];});
					$dloadVars = compact('extractor', 'vidInfo', 'urls', 'vidCount', 'filename', 'ffmpegOutput');
					$ffmpegOutput = ($useNative) ? $this->DownloadPlaylistNative($dloadVars) : $this->DownloadPlaylist($dloadVars);
				}
				else
				{
					$file = fopen($filename, 'w');
					$progressFunction = (parent::_PHP_VERSION >= 5.5) ? 'UpdateVideoDownloadProgress' : 'LegacyUpdateVideoDownloadProgress';
					$this->_curlResource = $ch = curl_init();
					curl_setopt($ch, CURLOPT_FILE, $file);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_setopt($ch, CURLOPT_URL, $urls[$vidCount]);
					curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
					curl_setopt($ch, CURLOPT_NOPROGRESS, false);
					curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($this, $progressFunction));
					curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096000);
					curl_setopt($ch, CURLOPT_USERAGENT, $extractor->GetMainUserAgent());					
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					if ($extractor->GetIpVersion() != -1)
					{
						curl_setopt($ch, CURLOPT_IPRESOLVE, constant("CURL_IPRESOLVE_V" . (string)$extractor->GetIpVersion()));
					}
					curl_setopt($ch, CURLOPT_HTTPHEADER, $extractor->MakeDownloadReqHeaders());					
					curl_exec($ch);
					if (curl_errno($ch) == 0)
					{
						$curlInfo = curl_getinfo($ch);
						if (($vidHost == "Dailymotion" || $vidHost == "SoundCloud" || $vidHost == "YouTube") && $curlInfo['http_code'] == '302')
						{
							if (isset($curlInfo['redirect_url']) && !empty($curlInfo['redirect_url']))
							{
								$urls[$vidCount] = $curlInfo['redirect_url'];
								$vidCount--;
							}
						}
						if (method_exists($extractor, 'GetCypherUsed') && $extractor->GetCypherUsed() && $curlInfo['http_code'] == '403')
						{
							$itag = $extractor->ExtractItagFromUrl($urls[$vidCount]);
							if (!empty($itag))
							{
								$signature = $extractor->GetSignature($itag);
								$extractor->UpdateSoftwareXml(compact('signature'));
							}
						}
					}
					curl_close($ch);
					fclose($file);				
				}				
				if (is_file($filename))
				{
					if (!filesize($filename) || filesize($filename) < 10000 || ($isPlaylist && (empty($ffmpegOutput) || preg_match('/muxing overhead/i', end($ffmpegOutput)) != 1)))
					{
						unlink($filename);
						$ffmpegOutput = array();
					}
					else
					{
						$success = true;
					}
				}
			}
			return $success;
		}
		
		/**
		* Download M3U8 playlist, enabling realtime download progress if possible.
		*
		* @param array $vars Download-related variables
		* @return array FFmpeg command line output
		*/			
		private function DownloadPlaylist(array $vars)
		{
			extract($vars);
			$vHost = $this->GetCurrentVidHost();
			$extractor = $this->GetExtractor();
			$cmd = parent::_FFMPEG . ' -user_agent "' . $extractor->GetMainUserAgent() . '" -headers "Cookie: ' . $extractor->ExtractCookies() . '\r\n" -i ' . escapeshellarg($urls[$vidCount]) . ' -bsf:a ' . ((strrchr($filename, ".") == ".mp3" || $vHost == "SoundCloud") ? 'mp3decomp' : 'aac_adtstoasc') . ' -c copy -y ' . escapeshellarg($filename) . ' 2>&1';
			//die($cmd);
			if (isset($vidInfo['duration']))
			{
				$descriptorspec = array(
					0 => array("pipe", "r"), 
					1 => array("pipe", "w"), 
					2 => array("pipe", "a")
				);
				$pipes = array();
				$process = proc_open($cmd, $descriptorspec, $pipes, null, null);
				if (is_resource($process)) 
				{
					$processInfo = false;
					do 
					{
						$cmdOutputLine = trim(fgets($pipes[1]));
						echo $cmdOutputLine . "<br>";
						if (preg_match('/(time=)(.+?)(\s)/i', $cmdOutputLine, $times) == 1)
						{										
							if (preg_match('/(\d\d):(\d\d):(\d\d\.\d\d)/', $times[2], $lastTime) == 1)
							{
								$lastTime = ((int)$lastTime[1] * 60 * 60) + ((int)$lastTime[2] * 60) + (float)$lastTime[3];
								$progress = round(($lastTime / (float)$vidInfo['duration']) * 100);
								$progress = ($progress > 100) ? 100 : $progress;
								$this->OutputDownloadProgress($progress, true);
							}
						}
						if (!empty($cmdOutputLine)) $ffmpegOutput[] = $cmdOutputLine;
						$processInfo = proc_get_status($process);
					} 
					while ($processInfo !== false && $processInfo['running']);
				}
				fclose($pipes[0]);
				fclose($pipes[1]);
				fclose($pipes[2]);
				proc_close($process);								
			}
			else
			{
				$this->OutputDownloadProgress(100, false);
				exec($cmd, $ffmpegOutput);
			}	
			return $ffmpegOutput;
		}

		/**
		* Download M3U8 playlist "without" FFmpeg, using FFmpeg only for stream copy.
		*
		* @param array $vars Download-related variables
		* @return array FFmpeg command line output
		*/
		private function DownloadPlaylistNative(array $vars)
		{
			extract($vars);
			$reqHeaders = (isset($extractor->_reqHeaders) && !empty($extractor->_reqHeaders)) ? $extractor->_reqHeaders : "";			
			$this->OutputDownloadProgress(100, false);
			$context = stream_context_create(array(
				'http' => array(
					'method' => "GET",
					'header' => $reqHeaders
				)
			));
			$m3u8Url = $urls[$vidCount];
			$m3u8file = file_get_contents($m3u8Url, false, $context);
			if ($m3u8file !== false && !empty($m3u8file))
			{
				$m3u8Lines = preg_split('/\n|\r/', $m3u8file, -1, PREG_SPLIT_NO_EMPTY);
				$uriPattern = '/URI="([^"]+)"/i';
				$m3u8Container = preg_grep($uriPattern, $m3u8Lines);
				$m3u8Container = ($m3u8Container !== false && !empty($m3u8Container) && preg_match($uriPattern, current($m3u8Container), $uriMatch) == 1) ? array($uriMatch[1]) : array();
				$m3u8Lines = array_merge($m3u8Container, (array)preg_grep('/^(\#)/', $m3u8Lines, PREG_GREP_INVERT));
				if (!empty($m3u8Lines))
				{
					ini_set('memory_limit', '-1');
					$videoContent = '';
					foreach ($m3u8Lines as $m3u8Line)
					{
						$urlPrefix = (string)parse_url($m3u8Url, PHP_URL_SCHEME) . "://" . (string)parse_url($m3u8Url, PHP_URL_HOST);
						$m3u8Line = $urlPrefix . "/" . trim($m3u8Line, "/");
						$tsFileContent = file_get_contents($m3u8Line, false, $context);
						if ($tsFileContent === false || empty($tsFileContent))
						{
							$videoContent = '';
							break;
						}
						$videoContent .= $tsFileContent;
					}
					if (!empty($videoContent))
					{
						$tmpfname = tempnam($extractor->GetStoreDir(), "m3u8");
						if ($tmpfname !== false)
						{
							$bytes = file_put_contents($tmpfname, $videoContent);
							if ($bytes !== false && $bytes > 0)
							{
								$cmd = Config::_FFMPEG . ' -i ' . escapeshellarg($tmpfname) . ' -c copy -y -f mp4 -bsf:a aac_adtstoasc ' . escapeshellarg($filename) . ' 2>&1';
								exec($cmd, $ffmpegOutput);
							}
							unlink($tmpfname);
						}
					}
				}
			}
			return $ffmpegOutput;
		}
		#endregion

		#region Properties
		/**
		* Getter and setter methods for MP3 file name.
		*/
		public function GetConvertedFileName()
		{
			return $this->_songFileName;
		}
		private function SetConvertedFileName()
		{
			$videoInfo = $this->GetVidInfo();
			$trackName = $videoInfo['title'];
			if (!empty($trackName))
			{
				if (!is_dir(realpath(parent::_SONGFILEDIR))) mkdir(parent::_SONGFILEDIR, 0777);
				$fname = parent::_SONGFILEDIR . preg_replace('/\s+/', '_', preg_replace('#/#', '', preg_replace('/\\\\|\/|\?|%|\*|:|\||"|<|>|\]|\[|\(|\)|\.|&|\^|\$|#|@|\!|`|~|=|\+|,|;|\'|\{|\}/', '', $trackName)));
				$fname .= (parent::_ENABLE_CONCURRENCY_CONTROL) ? uniqid('_uuid-') : '';
				$this->_songFileName = $fname . '.mp3';
			}
		}

		/**
		* Getter and setter methods for available video links (for a given video).
		*/
		public function GetVidSourceUrls()
		{
			return $this->_vidSourceUrls;
		}
		private function SetVidSourceUrls()
		{
			$extractor = $this->GetExtractor();
			$this->_vidSourceUrls = $extractor->ExtractVidSourceUrls();
		}

		/**
		* Getter and setter methods for local (temporary) video file name.
		*/
		private function GetTempVidFileName()
		{
			return $this->_tempVidFileName;
		}
		private function SetTempVidFileName()
		{
			if (!is_dir(realpath(parent::_TEMPVIDDIR))) mkdir(parent::_TEMPVIDDIR, 0777);
			$this->_tempVidFileName = parent::_TEMPVIDDIR . $this->GetUniqueID() .'.mkv';
		}

		/**
		* Getter and setter methods for current video/audio site name.
		*/
		public function GetCurrentVidHost()
		{
			return $this->_currentVidHost;
		}
		public function SetCurrentVidHost($videoUrl)
		{
			$vidHosts = $this->GetVideoHosts();
			foreach ($vidHosts as $host)
			{
				foreach ($host['url_root'] as $urlRoot)
				{
					$wildcardRegex = (parent::_PHP_VERSION >= 7.3) ? '/\\\#wildcard\\\#/' : '/#wildcard#/';
					$rootUrlPattern = preg_replace($wildcardRegex, "[^\\\\/]+", preg_quote($urlRoot, '/'));
					$rootUrlPattern = ($host['allow_https_urls']) ? preg_replace('/^(http)/', "https?", $rootUrlPattern) : $rootUrlPattern;
					if (preg_match('/^('.$rootUrlPattern.')/i', $videoUrl) == 1)
					{
						$this->_currentVidHost = $host['name'];
						break 2;
					}
				}
			}
		}

		/**
		* Getter and setter methods for general info related to the requested video.
		*/
		public function GetVidInfo()
		{
			return $this->_vidInfo;
		}
		public function SetVidInfo(array $vidInfo)
		{
			$this->_vidInfo = (!empty($vidInfo)) ? $vidInfo : $this->_vidInfo;
		}

		/**
		* Getter and setter methods for current site "extractor".
		*/
		public function GetExtractor()
		{
			return $this->_extractor;
		}
		private function SetExtractor($vidHostName)
		{
			$className = __NAMESPACE__ . '\\extractors\\' . $vidHostName;
			$this->_extractor = (class_exists($className)) ? new $className($this) : null;
		}

		/**
		* Getter and setter methods for converted file (MP3) quality.
		*/
		public function GetConvertedFileQuality()
		{
			return $this->_convertedFileQuality;
		}
		private function SetConvertedFileQuality($quality)
		{
			$this->_convertedFileQuality = $quality;
		}

		/**
		* Getter method that retrieves all available audio (MP3) qualities in Config class.
		*/
		public function GetAudioQualities()
		{
			return $this->_audioQualities;
		}

		/**
		* Getter method that retrieves the unique ID used for temporary and log file names.
		*/
		public function GetUniqueID()
		{
			return $this->_uniqueID;
		}

		/**
		* Getter method that retrieves all supported video/audio sites and their corresponding extractor configurations.
		*/
		public function GetVideoHosts()
		{
			return $this->_videoHosts;
		}

		/**
		* Getter method that retrieves whether or not FFmpeg conversion is required (i.e., if the MP3 is directly available from the video/audio site.
		*/
		public function GetSkipConversion()
		{
			return $this->_skipConversion;
		}
		#endregion
	}

?>
