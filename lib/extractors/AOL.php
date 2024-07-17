<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// AOL Extractor Class
	class AOL extends Extractor
	{
		// Fields
		protected $_params = array(
			'name' => 'AOL',
			'abbreviation' => 'al',
			'url_root' => array(
				'http://www.aol.com/video/play/#wildcard#/',
				'http://www.aol.com/video/#wildcard#/#wildcard#/'
			),
			'url_example_suffix' => 'e2b8b012-1783-3f21-bc2d-73585b6aa19f',
			'allow_https_urls' => true,
			'src_video_type' => 'mp4',
			'video_qualities' => array(
				'hd' => 'src_hd',  // high definition
				'hq' => 'src_hq',  // high quality			
				'sd' => 'src_sd',  // standard definition
				'ld' => 'src_ld'  // low definition
			),
			'icon_style' => 'icon icon-aol'
		);

		#region Public Methods		
		function RetrieveVidInfo($vidUrl)
		{
			$vidId = current(array_reverse(preg_split('/\//', parse_url($vidUrl, PHP_URL_PATH), -1, PREG_SPLIT_NO_EMPTY)));
			//die($vidId);
			$videoInfo = $this->CheckYahooPlaylist($vidId);
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
			if (isset($vidInfo['files']))
			{
				foreach ($vidInfo['files'] as $file)
				{
					if (!in_array($file, $vidUrls))
					{
						$vidUrls[] = $file;
					}
				}
			}

			//die(print_r($vidUrls));
			return array_reverse($vidUrls);
		}		
		#endregion

		#region Private "Helper" Methods
		private function CheckFeedApi($vidUrl, $vidId)
		{
			$videoInfo = array();
			$infoPage = $this->FileGetContents('https://feedapi.b2c.on.aol.com/v1.0/app/videos/aolon/' . $vidId . '/details');
			if (!empty($infoPage))
			{
				$jsonData = json_decode(trim($infoPage), true);
				//die(print_r($jsonData));
				if (json_last_error() == JSON_ERROR_NONE)
				{
					if (isset($jsonData['response']['statusText']) && strtolower($jsonData['response']['statusText']) == "ok" && isset($jsonData['response']['data']))
					{
						$data = $jsonData['response']['data'];
						if (isset($data['renditions']) && !empty($data['renditions']))
						{
							$duration = (isset($data['duration'])) ? array('duration' => $data['duration']) : array();
							$title = (isset($data['title'])) ? stripslashes($data['title']) : "Unknown";
							
							$thumb = 'https://img.youtube.com/vi/oops/1.jpg';
							if (isset($data['o2Id']))
							{
								$vidPage = $this->FileGetContents($vidUrl);
								if (!empty($vidPage))
								{
									$thumb = (preg_match('/<meta property="og:image" content="(.+?)" \/>/', $vidPage, $matched) == 1) ? preg_replace('/' . preg_quote($vidId, "/") . '/', $data['o2Id'], $matched[1]) : $thumb;
								}
							}
							
							$videoInfo = array('id' => $vidId, 'title' => $title, 'thumb_preview' => $thumb, 'files' => $data['renditions']) + $duration;
						}
					}
				}
			}
			return $videoInfo;
		}	
		
		private function CheckVidibleJS($vidUrl, $vidId)
		{
			$videoInfo = array();
			$vidPage = $this->FileGetContents($vidUrl);
			if (!empty($vidPage) && preg_match('/<script [^>]*src="[^"]+\/pid=([^\/]+)\/vid=[^\/]+\/([^\.]+)\.js"/is', $vidPage, $matches) == 1)
			{
				//die(print_r($matches));
				if (isset($matches[1], $matches[2]))
				{
					$jsFile = $this->FileGetContents('https://delivery.vidible.tv/jsonp/pid=' . $matches[1] . '/vid=' . $vidId . '/' . $matches[2] . '.js');
					//die($jsFile);
					if (!empty($jsFile) && preg_match('/("videos":\[\{.+?\}\])\},"playerTemplate"/is', $jsFile, $matches2) == 1)
					{
						//die(print_r($matches2));
						$jsonData = json_decode('{' . trim($matches2[1]) . '}', true);
						//die(print_r($jsonData));
						$vidData = (isset($jsonData['videos']) && is_array($jsonData['videos'])) ? array_shift($jsonData['videos']) : array();
						if (!empty($vidData) && isset($vidData['videoUrls']) && is_array($vidData['videoUrls']))
						{
							$title = (isset($vidData['name']) && !empty($vidData['name'])) ? $vidData['name'] : "Unknown";
							$thumb = (isset($vidData['fullsizeThumbnail']) && !empty($vidData['fullsizeThumbnail'])) ? $vidData['fullsizeThumbnail'] : "https://img.youtube.com/vi/oops/1.jpg";
							$duration = (isset($vidData['metadata']['duration']) && !empty($vidData['metadata']['duration'])) ? array('duration' => ((int)$vidData['metadata']['duration'] / 1000)) : array();
							$videoInfo = array('id' => $vidId, 'title' => $title, 'thumb_preview' => $thumb, 'files' => $vidData['videoUrls']) + $duration;
						}
					}										
				}	
			}
			return $videoInfo;
		}	
		
		private function CheckYahooPlaylist($vidId)
		{
			$videoInfo = array();
			$converter = $this->GetConverter();
			$infoPage = $this->FileGetContents("https://video-api.yql.yahoo.com/v1/video/videos/" .$vidId);
			//die($infoPage);
			if (!empty($infoPage))
			{
				$json = json_decode(trim($infoPage), true);
				//die(print_r($json));
				if (isset($json['videos']['result'][0]) && is_array($json['videos']['result'][0]))
				{
					$info = $json['videos']['result'][0];
					if (isset($info['streaming_url']))
					{
						$m3u8 = $this->FileGetContents($info['streaming_url']);
						if (!empty($m3u8))
						{
							$m3u8Lines = preg_split('/\n|\r/', $m3u8, -1, PREG_SPLIT_NO_EMPTY);
							$commentsRegex = ($converter::_PHP_VERSION >= 7.3) ? '/^(\\\#)/' : '/^(#)/';
							$m3u8Lines = preg_grep($commentsRegex, $m3u8Lines, PREG_GREP_INVERT);
							if (!empty($m3u8Lines))
							{
								//die(print_r($m3u8Lines));
								$videoUrls = array_reverse($m3u8Lines);
								//die(print_r($m3u8Lines));
								$title = (isset($info['title']) && !empty($info['title'])) ? $info['title'] : "Unknown";
								$thumb = (isset($info['thumbnails'][0]['url']) && !empty($info['thumbnails'][0]['url'])) ? $info['thumbnails'][0]['url'] : "https://img.youtube.com/vi/oops/1.jpg";
								$duration = (isset($info['duration']) && !empty($info['duration'])) ? array('duration' => (int)$info['duration']) : array();
								$videoInfo = array('id' => $vidId, 'title' => $title, 'thumb_preview' => $thumb, 'files' => $videoUrls) + $duration;								
							}
						}
					}
				}
			}
			return $videoInfo;
		}
		
		private function CheckValidUrl($url)
		{
			$options = array('http' => array('method' => 'HEAD', 'ignore_errors' => 1));
			$context = stream_context_create($options);
			file_get_contents($url, false, $context);
			return substr($http_response_header[0], 9, 3) != "404";
		}
		#endregion
	}
?>