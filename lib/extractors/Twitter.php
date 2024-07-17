<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// Twitter Extractor Class
	class Twitter extends Extractor
	{
		// Constants
		const _USERAGENT = 'Mozilla/5.0 (Android 6.0.1; Mobile; rv:54.0) Gecko/54.0 Firefox/54.0';
		const _GUEST_TOKEN = 'AAAAAAAAAAAAAAAAAAAAAPYXBAAAAAAACLXUNDekMxqa8h%2F40K4moUkGsoc%3DTYfbDKbT3jJPCEVnMYqilB28NHfOPqkca3qaAxGfsyKCs0wRbw';
		
		// Fields
		private $_quals = array("High", "Med", "Low");
        protected $_params = array(
            'name' => 'Twitter',
            'abbreviation' => 'tw',
            'url_root' => array(
                'http://twitter.com/',
                'http://mobile.twitter.com/'
            ),
            'url_example_suffix' => 'Youngblood_Pics/status/927322275820523521',
            'allow_https_urls' => true,
            'src_video_type' => 'mp4',
            'video_qualities' => array(
                'hd' => 'High',  // high definition
                'hq' => 'Med',  // high quality
                'sd' => 'Med',  // standard definition
                'ld' => 'Low'  // low definition
            ),
            'icon_style' => 'fab fa-twitter-square',
            'enable_native_playlist_download' => true  // When 'true', M3U8 playlists are downloaded "without" FFmpeg. Otherwise, FFmpeg is used for playlist downloads.
        );	
		protected $_mainUserAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36';
		
		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$converter = $this->GetConverter();
			$videoInfo = array();

			$vidHost = $converter->GetCurrentVidHost();
			$vidHosts = $converter->GetVideoHosts();
			$urlRoots = array();
			array_walk($vidHosts, function($vh, $key) use(&$urlRoots, $vidHost) {if ($vh['name'] == $vidHost) $urlRoots = $vh['url_root'];});

			$vidId = $converter->ExtractVideoId($vidUrl);
			$info = array('vTitle' => 'Unknown', 'thumb' => 'https://img.youtube.com/vi/oops/1.jpg', 'duration' => array(), 'urls' => array());
			
			foreach ($urlRoots as $urlRoot)
			{
				$patternRoot = preg_replace('/^(http)/', "$1(s)?", preg_quote($urlRoot, '/'));
				$urlSuffix = preg_replace('/^(' . $patternRoot . ')/', "", $vidUrl);
				if ($urlSuffix != $vidUrl)
				{
					$funcArgs = compact('vidUrl', 'urlSuffix', 'vidId');
					$info = $this->RetrieveMobileFormats($funcArgs + $info);
					//die(print_r($info));
					if (empty($info['urls']))
					{
						$info = $this->RetrieveAlternateFormats($funcArgs + $info);
					}
					break;
				}
			}		
			$videoInfo = array('id' => $vidId, 'title' => $this->UnicodeToHtmlEntities($info['vTitle']), 'thumb_preview' => $info['thumb']) + $info['duration'] + $info['urls'];
			//die(print_r($videoInfo));
			return $videoInfo;
		}

		function ExtractVidSourceUrls()
		{
			// Populate vars required for extraction
			$converter = $this->GetConverter();
			$vidUrls = array();
			$vidHost = $converter->GetCurrentVidHost();
			$vidInfo = $converter->GetVidInfo();

			$vidHosts = $converter->GetVideoHosts();
			$vidQualities = array();
			array_walk($vidHosts, function($vh, $key) use(&$vidQualities, $vidHost) {if ($vh['name'] == $vidHost) $vidQualities = $vh['video_qualities'];});

			// Start extraction
			foreach ($vidQualities as $key => $fq)
			{
				if (!empty($vidInfo[$fq]) && !in_array($vidInfo[$fq], $vidUrls))
				{
					$vidUrls[] = $vidInfo[$fq];
				}
			}

			//die(print_r($vidUrls));
			return array_reverse($vidUrls);
		}
		#endregion
		
		#region Private "Helper" Methods
		private function RetrieveMobileFormats(array $info)
		{
			extract($info);
			$streamOptions  = array('http' => array('user_agent' => self::_USERAGENT));
			$streamContext  = stream_context_create($streamOptions);
			$mobilePage = file_get_contents('https://mobile.twitter.com/' . $urlSuffix, false, $streamContext);
			if ($mobilePage !== false && !empty($mobilePage))
			{
				if (preg_match('/<script[^>]+src="([^"]+main\.[^"]+)"/', $mobilePage, $matches) == 1 && preg_match('/document\.cookie\s*=\s*decodeURIComponent\("gt=(\d+)/', $mobilePage, $matches2) == 1)
				{
					//die($matches[1]);
					//die($matches2[1]);
					$mainJS = file_get_contents($matches[1]);
					if ($mainJS !== false && !empty($mainJS))
					{
						if (preg_match('/BEARER_TOKEN\s*:\s*"([^"]+)"/', $mainJS, $matched) == 1)
						{
							//die($matched[1]);	
							$streamOptions = array('http' => array('header' => array('Authorization: Bearer ' . $matched[1], 'x-guest-token: ' . $matches2[1])));
							$streamContext = stream_context_create($streamOptions);
							$apiResponse = file_get_contents('https://api.twitter.com/2/timeline/conversation/' . $vidId . '.json', false, $streamContext);
							if ($apiResponse !== false && !empty($apiResponse))
							{
								//die($apiResponse);
								$jsonInfo = json_decode(trim($apiResponse), true);
								if (isset($jsonInfo['globalObjects']['tweets']) && is_array($jsonInfo['globalObjects']['tweets']))
								{
									foreach ($jsonInfo['globalObjects']['tweets'] as $tweet)
									{
										if (isset($tweet['extended_entities']['media'][0]['video_info']['variants']) && is_array($tweet['extended_entities']['media'][0]['video_info']['variants']) && !empty($tweet['extended_entities']['media'][0]['video_info']['variants']) && isset($tweet['extended_entities']['media'][0]['expanded_url']) && preg_match('/' . preg_quote($vidId, "/") . '/', $tweet['extended_entities']['media'][0]['expanded_url']) == 1)
										{
											//die(print_r($tweet['extended_entities']['media'][0]['video_info']['variants']));
											$urlsTmp = array();
											foreach ($tweet['extended_entities']['media'][0]['video_info']['variants'] as $variant)
											{
												if (isset($variant['content_type'], $variant['bitrate'], $variant['url']) && $variant['content_type'] == 'video/mp4')
												{
													$urlsTmp[$variant['bitrate']] = $variant['url'];
												}
											}
											if (!empty($urlsTmp))
											{
												krsort($urlsTmp);
												$urlsTmp = array_values($urlsTmp);
												foreach ($this->_quals as $k => $qual)
												{
													$urls[$qual] = $urlsTmp[$k];
												}
											}
											$vTitle = (isset($tweet['text']) && !empty($tweet['text'])) ? substr(trim($tweet['text']), 0, 100) : $vTitle;
											$thumb = (isset($tweet['extended_entities']['media'][0]['media_url_https'])) ? $tweet['extended_entities']['media'][0]['media_url_https'] : $thumb;
											$duration = (isset($tweet['extended_entities']['media'][0]['video_info']['duration_millis'])) ? array('duration' => (int)$tweet['extended_entities']['media'][0]['video_info']['duration_millis'] / 1000) : $duration;
											break;
										}												
									}
								}
							}
						}
					}
				}
			}
			return compact('vTitle', 'thumb', 'duration', 'urls');
		}
		
		private function RetrieveAlternateFormats(array $info)
		{
			extract($info);
			$playbackUrl = '';
			$vmapUrl = '';
			
			// Try video page
			$videoPage = file_get_contents('https://twitter.com/i/videos/tweet/' . $vidId);
			if ($videoPage !== false && !empty($videoPage) && preg_match('/data-(?:player-)?config="([^"]+)"/', $videoPage, $matches) == 1)
			{
				$jsonInfo = json_decode(htmlspecialchars_decode(trim($matches[1])), true);
				//die(print_r($jsonInfo));
				$vmapUrl = (!isset($jsonInfo['vmapUrl'])) ? ((!isset($jsonInfo['vmap_url'])) ? '' : $jsonInfo['vmap_url']) : $jsonInfo['vmapUrl'];
			}	
			else
			{
				// Try Twitter API as guest
				$this->FileGetContents($info['vidUrl']);
				$cookies = $this->ExtractCookies();
				//die($cookies);
				$reqHeaders = array('Authorization: Bearer ' . self::_GUEST_TOKEN, 'Referer: ' . $info['vidUrl'], 'Cookie: ' . $cookies);
				$apiResponse = $this->FileGetContents('https://api.twitter.com/1.1/guest/activate.json', ' ', $reqHeaders);
				if (!empty($apiResponse))			
				{
					//die($apiResponse);
					$jsonData = json_decode(trim($apiResponse), true);
					if (json_last_error() == JSON_ERROR_NONE && isset($jsonData['guest_token']))
					{
						$reqHeaders = array('Authorization: Bearer ' . self::_GUEST_TOKEN, 'x-guest-token: ' . $jsonData['guest_token']);
						
						$apiResponse = $this->FileGetContents('https://api.twitter.com/1.1/statuses/show/' . $info['vidId'] . '.json', '', $reqHeaders);
						if (!empty($apiResponse))
						{
							//die($apiResponse);
							$jsonData = json_decode(trim($apiResponse), true);
							if (json_last_error() == JSON_ERROR_NONE)
							{
								$jsonInfo['status']['text'] = (isset($jsonData['text']) && !empty($jsonData['text'])) ? $jsonData['text'] : $vTitle;
							}
						}
						
						$apiResponse = $this->FileGetContents('https://api.twitter.com/1.1/videos/tweet/config/' . $info['vidId'], '', $reqHeaders);
						if (!empty($apiResponse))
						{
							//die($apiResponse);
							$jsonData = json_decode(trim($apiResponse), true);
							if (json_last_error() == JSON_ERROR_NONE)
							{
								$playbackUrl = (isset($jsonData['track']['playbackUrl'])) ? $jsonData['track']['playbackUrl'] : '';
								$vmapUrl = (isset($jsonData['track']['vmapUrl'])) ? $jsonData['track']['vmapUrl'] : '';
							}
						}
					}
				}
			}
			
			if (!empty($playbackUrl) && preg_match('/^((\.m3u8)(.*))$/', strrchr($playbackUrl, ".")) == 1)
			{
				$urls = $this->ParsePlaylist($playbackUrl);
			}
			elseif (!empty($vmapUrl))
			{
				$vmapFile = file_get_contents($vmapUrl);
				//die($vmapFile);
				if ($vmapFile !== false && !empty($vmapFile))
				{
					try
					{
						$sxe = @new \SimpleXMLElement(trim($vmapFile));
						$mediaFile = $sxe->xpath('.//MediaFile');
						if (is_array($mediaFile))
						{
							foreach ($mediaFile as $mf)
							{
								$filePath = parse_url($mf, PHP_URL_PATH);
								if ($filePath !== false && !is_null($filePath))
								{
									$fileExt = strrchr($filePath, ".");
									if ($fileExt !== false)
									{
										if ($fileExt == ".m3u8" && empty($urls))
										{
											$urls = $this->ParsePlaylist(trim((string)$mf));
											break;
										}
										if ($fileExt != ".m3u8" && count($urls) < count($this->_quals))
										{
											$urls[$this->_quals[count($urls)]] = trim((string)$mf);
										}
									}
								}
							}
						}
					}
					catch (Exception $ex) {}
				}
			}
			//die(print_r($urls));
			if (empty($urls) && isset($jsonInfo['video_url']))
			{
				$urls = $this->ParsePlaylist($jsonInfo['video_url']);
			}
			if (empty($urls) && !empty($playbackUrl))
			{
				$urls = array($this->_quals[0] => $playbackUrl);
			}
			//die(print_r($urls));
			$vTitle = (isset($jsonInfo['status']['text']) && !empty($jsonInfo['status']['text'])) ? substr(trim($jsonInfo['status']['text']), 0, 100) : $vTitle;
			$thumb = (!isset($jsonInfo['image_src'])) ? ((!isset($jsonData['posterImage'])) ? $thumb : $jsonData['posterImage']) : $jsonInfo['image_src'];
			$duration = (!isset($jsonInfo['duration'])) ? ((!isset($jsonData['track']['durationMs'])) ? $duration : array('duration' => (int)$jsonData['track']['durationMs'] / 1000)) : array('duration' => (int)$jsonInfo['duration'] / 1000);
			
			return compact('vTitle', 'thumb', 'duration', 'urls');
		}
		
		private function ParsePlaylist($playlistUrl)
		{
			$urls = array();
			$m3u8UrlInfo = parse_url($playlistUrl);
			$m3u8UrlRoot =  $m3u8UrlInfo["scheme"] . "://" . $m3u8UrlInfo["host"];
			//die($m3u8UrlRoot);					
			$m3u8 = file_get_contents($playlistUrl);
			if ($m3u8 !== false && !empty($m3u8))
			{
				$m3u8Lines = preg_split('/\n|\r/', $m3u8, -1, PREG_SPLIT_NO_EMPTY);
				$m3u8Lines = preg_grep('/^(#)/', $m3u8Lines, PREG_GREP_INVERT);
				if (!empty($m3u8Lines))
				{
					//die(print_r(array_reverse($m3u8Lines)));
					$m3u8Lines = array_reverse($m3u8Lines);
					$quals = $this->_quals;
					$uniqueResolutions = array();
					$qualCount = 0;
					foreach ($m3u8Lines as $val) 
					{ 
						if (preg_match('/\/(\d+x\d+)\//i', $val, $matches) == 1 && !in_array($matches[1], $uniqueResolutions))
						{
							if ($qualCount <= 1 || $val == end($m3u8Lines))
							{
								$urls[$quals[$qualCount]] = ((preg_match('/^(https?)/i', $val) == 1) ? '' : $m3u8UrlRoot) . $val;
								$qualCount++;
								$uniqueResolutions[] = $matches[1];
							}
						}
					}
					//die(print_r($urls));
				}
			}	
			return $urls;
		}
		
		private function UnicodeToHtmlEntities($str)
		{
			$output = json_encode(htmlentities((string)$str, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'));
			//die($output);
			$output = preg_replace_callback('/\\\u([0-9a-z]{4})/', function($matches){
				$entity = '&#x'. $matches[1] .';';
				$entityDecoded = html_entity_decode($entity, ENT_COMPAT | ENT_HTML401, 'UTF-8');
				//echo json_encode($entityDecoded) . "<br />";
				return (json_encode($entityDecoded) == "null") ? '' : $entity;
			}, $output);
			//die(print_r(json_decode($output)));
			$output = trim(json_decode($output));
			$output = preg_replace('/\n|\r/', " ", $output);
			return $output;
		}		
		#endregion
	}
?>