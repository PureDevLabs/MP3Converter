<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	use MP3Converter\lib\Config;

	// Vimeo Extractor Class
	class Vimeo extends Extractor
	{
		// Fields
		protected $_params = array(
			'name' => 'Vimeo',
			'abbreviation' => 'vm',
			'url_root' => array(
				'http://vimeo.com/'
			),
			'url_example_suffix' => '72695254',
			'allow_https_urls' => true,
			'src_video_type' => 'mp4',
			'video_qualities' => array(
				'hd' => 'hd',  // high definition
				'hq' => 'hd',  // high quality
				'sd' => 'sd',  // standard definition
				'ld' => 'mobile'  // low definition
			),
			'icon_style' => 'fab fa-vimeo-square'
		);

		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$converter = $this->GetConverter();
			$vidID = $converter->ExtractVideoId($vidUrl);
			$videoInfo = array('id' => $vidID, 'title' => 'Unknown', 'thumb_preview' => 'https://img.youtube.com/vi/oops/1.jpg', 'files' => array());
			$videoInfo = $this->UseNewApi($videoInfo, $vidID);
			if (empty($videoInfo['files']))
			{
				$videoInfo = $this->UseOldApiAndPlayer($videoInfo, $vidID);
			}
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
		private function UseNewApi(array $videoInfo, $vidID)
		{
			$authResponse = $this->FileGetContents("https://vimeo.com/_rv/viewer");
			if (!empty($authResponse))
			{
				$authResponse = json_decode(trim($authResponse), true);
				if (isset($authResponse['jwt']))
				{
					$apiResponse = $this->FileGetContents("https://api.vimeo.com/videos/" . $vidID, '', array("Authorization: jwt " . $authResponse['jwt']));
					if (!empty($apiResponse))
					{
						$apiResponse = json_decode(trim($apiResponse), true);
						//die(var_dump($apiResponse));
						if (isset($apiResponse['name'], $apiResponse['pictures']['base_link']))
						{
							$videoInfo = array('id' => $vidID, 'title' => $apiResponse['name'], 'thumb_preview' => $apiResponse['pictures']['base_link'], 'files' => array());
							if (isset($apiResponse['download']) && !empty($apiResponse['download']) && is_array($apiResponse['download']))
							{
								//die(print_r($apiResponse['download']));
								foreach ($apiResponse['download'] as $source)
								{
									if (isset($source['link'], $source['rendition']) && $source['rendition'] != "source")
									{
										$videoInfo['files'][(int)$source['rendition']] = $source['link'];
									}
								}
								if (!empty($videoInfo['files']))
								{
									krsort($videoInfo['files'], SORT_NUMERIC);
								}
							}
						}
					}
				}
			}
			return $videoInfo;
		}
	
		private function UseOldApiAndPlayer(array $videoInfo, $vidID)
		{
			$apiResponse = $this->FileGetContents("https://vimeo.com/api/v2/video/" . $vidID . ".json");
			if (!empty($apiResponse))
			{
				$apiResponse = json_decode(trim($apiResponse), true);
				//die(var_dump($apiResponse));
				if (isset($apiResponse[0]['id'], $apiResponse[0]['title'], $apiResponse[0]['thumbnail_medium']))
				{
					$videoInfo = array('id' => $apiResponse[0]['id'], 'title' => $apiResponse[0]['title'], 'thumb_preview' => $apiResponse[0]['thumbnail_medium'], 'files' => array());
					$playerContents = $this->FileGetContents('https://player.vimeo.com/video/' . $videoInfo['id']);
					if (!empty($playerContents))
					{
						//if (preg_match('/(\w+)\.video\.id/', $playerContents, $objName) == 1) die($objName[1]);
						$jsonObjName = (preg_match('/(\w+)\.video\.id/', $playerContents, $objName) == 1) ? $objName[1] : "r";
						$pattern1 = preg_match('/var ' . preg_quote($jsonObjName, '/') . '=(\{.+?\});/s', $playerContents, $matchArray);
						$pattern2 = preg_match('/config\s*=\s*(\{.+?\})\s*;/s', $playerContents, $matchArray2);
						$matchArray = ($pattern1 != 1) ? (($pattern2 != 1) ? array() : $matchArray2) : $matchArray;
						if (!empty($matchArray))
						{
							$json = json_decode(strip_tags($matchArray[1]), true);
							//die(print_r($json));
							if (isset($json['request']['files']['progressive']) && !empty($json['request']['files']['progressive']) && is_array($json['request']['files']['progressive']))
							{
								$urls = array();
								array_walk($json['request']['files']['progressive'], function($url) use(&$urls) { 
									if (isset($url['quality'], $url['url'], $url['mime']) && $url['mime'] == "video/mp4")
									{
										$urls[(int)preg_replace('/\D/', '', $url['quality'])] = $url['url'];
									} 
								});
								if (!empty($urls))
								{
									krsort($urls, SORT_NUMERIC);
									//die(print_r($urls));
									$videoInfo['files'] = $urls;
								}
							}
						}
					}
				}
			}
			return $videoInfo;
		}
		#endregion
	}
?>