<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// SoundCloud Extractor Class
	class SoundCloud extends Extractor
	{
		// Fields
		private $_pageUrlSuffix = '';
		private $_clientId = '';
		private $_recursionLevel = 0;
		private $_formats = array();		
		protected $_params = array(
			'name' => 'SoundCloud',
			'abbreviation' => 'sc',
			'url_root' => array(
				'http://soundcloud.com/',
				'http://m.soundcloud.com/'
			),
			'url_example_suffix' => 'foolsgoldrecs/funkin-matt-america',
			'allow_https_urls' => true,
			'src_video_type' => 'wav',
			'video_qualities' => array(
				'hd' => 'src_sd',  // high definition
				'hq' => 'src_sd',  // high quality
				'sd' => 'src_sd',  // standard definition
				'ld' => 'src_sd'  // low definition
			),
			'icon_style' => 'fab fa-soundcloud'
		);

		// Constants
		const _API_BASE = 'https://api-v2.soundcloud.com/';
		const _CLIENT_ID = 'Uz4aPhG7GAl1VYGOnvOPW1wQ0M6xKtA9';

		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$videoInfo = array();
			$filePath = $this->GetStoreDir();
			$clientId = (is_file($filePath . 'sc_client_id.txt')) ? file_get_contents($filePath . 'sc_client_id.txt') : self::_CLIENT_ID;
			$this->_clientId = ($clientId !== false && !empty($clientId) && $clientId != self::_CLIENT_ID) ? trim($clientId) : self::_CLIENT_ID;
			$apiResponse = file_get_contents(self::_API_BASE . "resolve?url=" . $vidUrl . "&client_id=" . $this->GetClientId());
			if ($apiResponse !== false && !empty($apiResponse))
			{
				$jsonData = json_decode($apiResponse, true);
				if (json_last_error() == JSON_ERROR_NONE)
				{
					$this->_formats = (isset($jsonData['media']['transcodings'])) ? $jsonData['media']['transcodings'] : array();
					$duration = (isset($jsonData['duration'])) ? array('duration' => (int)$jsonData['duration'] / 1000) : array();
					$videoInfo = array('id' => $jsonData['id'], 'title' => $jsonData['title'], 'thumb_preview' => $jsonData['artwork_url'], 'file_permalink' => $jsonData['permalink'], 'user_permalink' => $jsonData['user']['permalink']) + $duration;
					$this->SetPageUrlSuffix($videoInfo['user_permalink'] . "/" . $videoInfo['file_permalink']);
				}
			}
			if (($apiResponse === false || empty($apiResponse)) && $this->GetRecursionLevel() == 0)
			{
				$videoInfo = $this->UpdateClientId($vidUrl);
			}			
			//die(print_r($videoInfo));
			return $videoInfo;
		}

		function ExtractVidSourceUrls()
		{
			// Populate vars required for extraction
			$converter = $this->GetConverter();
			$vidUrls = array();
			$vidInfo = $converter->GetVidInfo();
			
			// Start extraction
			$formats = $this->GetFormats();
			if (!empty($formats) && is_array($formats))
			{
				foreach ($formats as $url)
				{
					if (isset($url['url']) && isset($url['format']['mime_type']) && $url['format']['mime_type'] == "audio/mpeg")
					{
						$urlJson = $this->FileGetContents($url['url'] . "?client_id=" . $this->GetClientId());
						if (!empty($urlJson))
						{
							$jsonInfo = json_decode(trim($urlJson), true);
							if (isset($jsonInfo['url'])) 
							{
								$vidUrls[] = $jsonInfo['url'];
							}
						}
					}					
				}
			}
			else
			{
				$pageContent = $this->FileGetContents("https://soundcloud.com/" . $this->GetPageUrlSuffix());
				if (!empty($pageContent))
				{
					if (preg_match('/,(\[\{.+?\])\);/s', $pageContent, $matches) == 1)
					{
						$vidInfo = json_decode($matches[1], true);
						//die(print_r($vidInfo));
						if (isset($vidInfo[5]['data'][0]['media']['transcodings']) && is_array($vidInfo[5]['data'][0]['media']['transcodings']))
						{
							$transcodings = $vidInfo[5]['data'][0]['media']['transcodings'];
							foreach ($transcodings as $url)
							{
								if (isset($url['url']) && isset($url['format']['mime_type']) && $url['format']['mime_type'] == "audio/mpeg")
								{
									$urlJson = $this->FileGetContents($url['url'] . "?client_id=" . $this->GetClientId());
									if (!empty($urlJson))
									{
										$jsonInfo = json_decode(trim($urlJson), true);
										if (isset($jsonInfo['url'])) 
										{
											$vidUrls[] = $jsonInfo['url'];
										}
									}
								}
							}
						}
					}
				}				
			}		

			//die(print_r(array_reverse($vidUrls)));
			return array_reverse($vidUrls);
		}
		#endregion
		
		#region Private "Helper" Methods
		private function UpdateClientId($vidUrl)
		{
			$lockSucceeded = false;
			$siteContent = $this->FileGetContents($vidUrl);
			//die($siteContent);
			if (!empty($siteContent))
			{
				preg_match_all('/<script[^>]+src="([^"]+)"/is', $siteContent, $scriptMatches);
				if (!empty($scriptMatches))
				{
					//die(print_r($scriptMatches));
					$scriptMatches = array_reverse($scriptMatches[1]);
					foreach ($scriptMatches as $sm)
					{
						$scriptContent = file_get_contents($sm);
						if ($scriptContent !== false && !empty($scriptContent) && preg_match('/client_id\s*:\s*"([0-9a-zA-Z]{32})"/', $scriptContent, $ciMatch) == 1)
						{
							//die(print_r($ciMatch));
							$filePath = $this->GetStoreDir();
							$fp = fopen($filePath . 'sc_client_id.txt', 'w');
							if ($fp !== false)
							{
								if (flock($fp, LOCK_EX))
								{
									$lockSucceeded = true;
									fwrite($fp, $ciMatch[1]);
									flock($fp, LOCK_UN);
								}
								fclose($fp);
								if ($lockSucceeded)
								{
									chmod($filePath . "sc_client_id.txt", 0777);
								}
								else
								{
									unlink($filePath . 'sc_client_id.txt');
								}
							}
							break;
						}
					}
				}
			}
			$this->SetRecursionLevel($this->GetRecursionLevel() + 1);
			return ($lockSucceeded) ? $this->RetrieveVidInfo($vidUrl) : array();
		}
		#endregion		

		#region Properties
		private function SetPageUrlSuffix($value)
		{
			$this->_pageUrlSuffix = $value;
		}
		public function GetPageUrlSuffix()
		{
			return $this->_pageUrlSuffix;
		}
		
		private function GetFormats()
		{
			return $this->_formats;
		}		
		
		private function GetClientId()
		{
			return $this->_clientId;
		}
		
		private function SetRecursionLevel($value)
		{
			$this->_recursionLevel = $value;
		}		
		private function GetRecursionLevel()
		{
			return $this->_recursionLevel;
		}		
		#endregion
	}
?>