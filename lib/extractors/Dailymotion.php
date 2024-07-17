<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// Dailymotion Extractor Class
	class Dailymotion extends Extractor
	{
		// Fields
		protected $_params = array(
			'name' => 'Dailymotion',
			'abbreviation' => 'dm',
			'url_root' => array(
				'http://www.dailymotion.com/video/'
			),
			'url_example_suffix' => 'x31j7ik_chapman-stick_music',
			'allow_https_urls' => true,
			'src_video_type' => 'mp4',
			'video_qualities' => array(
				'hd' => '720',  // high definition
				'hq' => '480',  // high quality
				'sd' => '380',  // standard definition
				'ld' => '240'  // low definition
			),
			'icon_style' => 'icon icon-dailymotion'
		);
		protected $_mainUserAgent = 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36';

		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$videoInfo = array();
			$apiResponse = json_decode($this->FileGetContents(preg_replace('/^(http(s)?:\/\/www)/i', "https://api", current(explode('?', $vidUrl))).'?fields=id,title,thumbnail_medium_url,duration'), true);
			//die(var_dump($apiResponse));
			$thumb = (empty($apiResponse['thumbnail_medium_url'])) ? 'https://img.youtube.com/vi/oops/1.jpg' : $apiResponse['thumbnail_medium_url'];
			$duration = (isset($apiResponse['duration'])) ? array('duration' => $apiResponse['duration']) : array();			
			$videoInfo = array('id' => $apiResponse['id'], 'title' => $apiResponse['title'], 'thumb_preview' => $thumb) + $duration;
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
			$file_contents = $this->FileGetContents('http://www.dailymotion.com/player/metadata/video/'.$vidInfo['id']);
			//die(print_r($this->_headers));
			if (!empty($file_contents))
			{
				$jsonArr = json_decode(trim($file_contents), true);
				//die(print_r($jsonArr));
				/*if (isset($jsonArr['error']['title'])) echo "<br><b>" . $jsonArr['error']['title'] . "<b><br>";*/
				if (isset($jsonArr['qualities']['auto']) && is_array($jsonArr['qualities']['auto']) && isset($jsonArr['qualities']['auto'][0]['url']) && preg_match('/^((\.m3u8)(.*))$/', (string)strrchr($jsonArr['qualities']['auto'][0]['url'], ".")) == 1)
				{
					$urls = $this->ParsePlaylist($jsonArr['qualities']['auto'][0]['url'], $vidQualities);
					if (!empty($urls))
					{
						foreach ($urls as $url)
						{
							$vidUrls[] = $url;
						}
					}
				}
				elseif (isset($jsonArr['qualities']) && is_array($jsonArr['qualities']))
				{
					$jsonQualities = $jsonArr['qualities'];
					foreach ($vidQualities as $fq)
					{
						if (isset($jsonQualities[$fq]) && !empty($jsonQualities[$fq]) && is_array($jsonQualities[$fq]))
						{
							foreach ($jsonQualities[$fq] as $sourceType)
							{
								if ($sourceType['type'] == 'video/mp4')
								{
									$vidUrls[] = stripslashes($sourceType['url']);
								}
							}
						}
					}
				}
			}

			//die(print_r($vidUrls));
			return array_reverse($vidUrls);
		}
		#endregion
		
		#region Private "Helper" Methods
		private function ParsePlaylist($playlistUrl, array $vidQualities)
		{
			$urls = array();				
			$m3u8 = $this->FileGetContents($playlistUrl);
			//die($m3u8);
			//die(print_r($this->_headers));
			if (!empty($m3u8))
			{
				$m3u8Lines = preg_split('/\n|\r/', $m3u8, -1, PREG_SPLIT_NO_EMPTY);
				$m3u8Lines = preg_grep('/^(#)/', $m3u8Lines, PREG_GREP_INVERT);
				if (!empty($m3u8Lines))
				{
					//die(print_r($m3u8Lines));
					$uniqueResolutions = array();
					foreach ($vidQualities as $key => $fq)
					{
						foreach ($m3u8Lines as $val) 
						{
							if (!in_array($key, $uniqueResolutions) && preg_match('/^(.+?_' . preg_quote($key, "/") . '(_\w+)?\.m3u8)/i', $val, $matches) == 1)
							{
								$urls[$key] = $matches[1];
								$uniqueResolutions[] = $key;
								break;
							}
						}
					}
					//die(print_r($urls));
				}
			}	
			return $urls;
		}
		#endregion
	}
?>