<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// Facebook Extractor Class
	class Facebook extends Extractor
	{
		// Fields
		protected $_params = array(
			'name' => 'Facebook',
			'abbreviation' => 'fb',
			'url_root' => array(
				'http://www.facebook.com/video/video.php?v=',
				'http://www.facebook.com/video.php?v=',
				'http://www.facebook.com/photo.php?v=',
				'http://www.facebook.com/watch?v=',
				'http://www.facebook.com/watch/?v=',
				'http://www.facebook.com/groups/#wildcard#/permalink/',
				'http://www.facebook.com/#wildcard#/posts/',
				'http://www.facebook.com/#wildcard#/videos/#wildcard#/',
				'http://www.facebook.com/#wildcard#/videos/',
				'http://web.facebook.com/#wildcard#/videos/',
				'http://m.facebook.com/story.php?story_fbid=',
				'http://fb.watch/',
				'http://fb.gg/v/'
			),
			'url_example_suffix' => '10151848825508876',
			'allow_https_urls' => true,
			'src_video_type' => 'mp4',
			'video_qualities' => array(
				'au' => 'src_au',  // audio only
				'hd' => 'src_hd',  // high definition
				'hq' => 'src_hd',  // high quality
				'sd' => 'src_sd',  // standard definition
				'ld' => 'src_sd'  // low definition
			),
			'icon_style' => 'fab fa-facebook-square'
		);
		protected $_mainUserAgent = 'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36';
		private $_backupUserAgent = 'facebookexternalhit/1.1';

		#region Public Methods		
		function RetrieveVidInfo($vidUrl)
		{
			$converter = $this->GetConverter();
			$videoInfo = array();
			$vidID = $converter->ExtractVideoId($vidUrl);
			if (preg_match('/^(https?:\/\/fb\.)/', $vidUrl) == 1)
			{
				$mainUA = $this->_mainUserAgent;
				$this->_mainUserAgent = $this->_backupUserAgent;
				$appRequest = $this->FileGetContents($vidUrl, '', array("Host: " . (string)parse_url($vidUrl, PHP_URL_HOST), "Accept: text/html,application/xhtml+xml,application/xml"));
				$this->_mainUserAgent = $mainUA;
				//die(print_r($this->_headers));
				$effectiveUri = '';
				if (!empty($this->_headers))
				{
					foreach ($this->_headers as $header)
					{
						if (preg_match('/^(Location:\s*(.+))$/i', $header, $hmatch) == 1)
						{
							$effectiveUri = $hmatch[2];
							break;
						}
					}
				}
				if (!empty($effectiveUri))
				{
					$vidID = $converter->ExtractVideoId($effectiveUri);
					$vidUrl = $this->_params['url_root'][0] . $vidID;
				}
				//die($vidUrl . " " . $vidID);
			}
			if ($vidID == "story.php")
			{
				$urlQueryStr = parse_url($vidUrl, PHP_URL_QUERY);
				if ($urlQueryStr !== false && !is_null($urlQueryStr))
				{
					parse_str($urlQueryStr, $qsVars);
					$vidID = $qsVars['story_fbid'];
					$vidUrl = 'https://www.facebook.com/' . $qsVars['id'] . '/videos/' . $vidID;
				}
			}
			$postUrlRegex = '/(\/posts\/[^\/]+\/?)$/';
			if (preg_match($postUrlRegex, $vidUrl) == 1)
			{
				$postPage = $this->FileGetContents($vidUrl);
				if (!empty($postPage) && preg_match('/"?video_ids"?\s*:\s*\["(.+?)"\]/', $postPage, $matched) == 1)
				{
					$vidID = $matched[1];
					$vidUrl = preg_replace($postUrlRegex, "/videos/" . $matched[1], $vidUrl);
				}
			}
			$vidData = array('sd_src' => '', 'hd_src' => '', 'au_src' => '');
			$vidImage = 'https://img.youtube.com/vi/oops/1.jpg';
			$vTitle = 'Unknown';				
			$vidPage = $this->FileGetContents($vidUrl, '', array("Host: " . (string)parse_url($vidUrl, PHP_URL_HOST), "Accept: text/html,application/xhtml+xml,application/xml"));
			//die($vidPage);
			if (!empty($vidPage))
			{				
				if (preg_match('/<meta.*?\s*property="og:image"\s*content="([^"]+)"\s*.*?\/>/is', $vidPage, $imgmatch) == 1)
				{
					$vidImage = (isset($imgmatch[1]) && !empty($imgmatch[1])) ? preg_replace('/&amp;/', "&", $imgmatch[1]) : $vidImage;
				}
				if (preg_match('/<title[^>]*>([^<]*)<\/title>/si', $vidPage, $matches) == 1)
				{
					$vidTitle = preg_replace('/[^\p{L}\p{N}\p{P} ]+/u', "", html_entity_decode(trim($matches[1]), ENT_QUOTES));
					$vidTitle = preg_replace('/(\s*facebook)$/i', "", trim($vidTitle));
					$vTitle = (!empty($vidTitle)) ? ((strlen($vidTitle) > 50) ? substr($vidTitle, 0, 50) . "..." : $vidTitle) : $vTitle;
				}
				//die($vidPage);
				if ((int)preg_match_all('/,"require"\s*:\s*(\[.+?\])\}\);\}\);\}\);/s', $vidPage, $requireMatches) > 0)
				{
					//die(print_r($requireMatches));
					$jsonWithMedia = preg_grep('/"media"\s*:\s*\{/s', $requireMatches[1]);
					//die(print_r($jsonWithMedia));
					if ($jsonWithMedia !== false && !empty($jsonWithMedia))
					{
						$requireInfo = json_decode(current($jsonWithMedia), true);
						//die(print_r($requireInfo));
						if (json_last_error() == JSON_ERROR_NONE)
						{
							$mediaInfo = array();
							$iterator = new \RecursiveArrayIterator($requireInfo);
							$recursive = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
							foreach ($recursive as $key => $value)
							{
								$mediaInfo = ($key === "media") ? $value : $mediaInfo;
								if (!empty($mediaInfo)) break;
							}
							//die(print_r($mediaInfo));
							if (isset($mediaInfo['__typename'], $mediaInfo['id'], $mediaInfo['playable_url']) && $mediaInfo['__typename'] == "Video" && $mediaInfo['id'] == $vidID)
							{
								$vTitle = (isset($mediaInfo['savable_description']['text'])) ? ((strlen($mediaInfo['savable_description']['text']) > 50) ? substr($mediaInfo['savable_description']['text'], 0, 50) . "..." : $mediaInfo['savable_description']['text']) : $vTitle;
								$vidData['hd_src'] = (isset($mediaInfo['playable_url_quality_hd']) && !empty($mediaInfo['playable_url_quality_hd'])) ? stripslashes($mediaInfo['playable_url_quality_hd']) : $vidData['hd_src'];
								$vidData['sd_src'] = stripslashes($mediaInfo['playable_url']);
								if (isset($mediaInfo['dash_manifest']))
								{
									$manifestXml = simplexml_load_string($mediaInfo['dash_manifest']);
									if ($manifestXml !== false && !empty($manifestXml))
									{
										$manifestJson = json_encode($manifestXml);
										$manifestArr = json_decode($manifestJson, true);
										//die(print_r($manifestArr));
										if (isset($manifestArr['Period']['AdaptationSet']) && is_array($manifestArr['Period']['AdaptationSet']))
										{
											foreach ($manifestArr['Period']['AdaptationSet'] as $urlSet)
											{
												$isAudio = isset($urlSet['@attributes']['mimeType']) && preg_match('/^((video|audio)\/(\w+))/', $urlSet['@attributes']['mimeType'], $avMatch) == 1 && $avMatch[2] == "audio";
												if (isset($urlSet['Representation']) && is_array($urlSet['Representation']))
												{
													if (!isset($urlSet['Representation'][0]))
													{
														$urlSet['Representation'][0] = $urlSet['Representation'];
														$urlSet['Representation'] = array_filter($urlSet['Representation'], "is_numeric", ARRAY_FILTER_USE_KEY);
													}
													foreach ($urlSet['Representation'] as $fileInfo)
													{
														$isAudio = (!$isAudio) ? isset($fileInfo['@attributes']['mimeType']) && preg_match('/^((video|audio)\/(\w+))/', $fileInfo['@attributes']['mimeType'], $avMatch) == 1 && $avMatch[2] == "audio" : $isAudio;
														if ($isAudio && isset($fileInfo['BaseURL']))
														{
															$vidData['au_src'] = stripslashes($fileInfo['BaseURL']);
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}				
			}
			$videoInfo = array('id' => $vidID, 'title' => $vTitle, 'thumb_preview' => $vidImage, 'src_sd' => $vidData['sd_src'], 'src_hd' => $vidData['hd_src'], 'src_au' => $vidData['au_src']);

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
			foreach ($vidQualities as $fq)
			{
				if (!empty($vidInfo[$fq]) && !in_array($vidInfo[$fq], $vidUrls))
				{
					$vidUrls[] = $vidInfo[$fq];
				}
			}

			//die(print_r($vidUrls));
			return $vidUrls;
		}
		#endregion
		
		#region Private "Helper" Methods
		private function ExtractVidData(array $vidData, array $instances) 
		{
			foreach ($instances as $infoData)
			{
				if (isset($infoData[1][0]) && $infoData[1][0] == "VideoConfig" && isset($infoData[2][0]['videoData']) && is_array($infoData[2][0]['videoData']))
				{
					$vidData = current($infoData[2][0]['videoData']) + $vidData;
					//die(print_r($vidData));
					break;
				}
			}
			return $vidData;
		}
		
		private function CleanJson($json)
		{
			$json = preg_replace('/(dash_manifest|highlights_manifest):\s*"(.+?)",/', "", $json);
			$json = preg_replace('/\\\\x/i', "\\u00", $json);
			$json = preg_replace('/(\w+\s*:\s*")(data:text\/css.*?)(")/is', "$1$3", $json);
			$json = preg_replace('/([{,])(\s*)([A-Za-z0-9_\-]+?)\s*:/is', '$1"$3":', $json);			

			// Remove unsupported characters
			for ($i = 0; $i <= 31; ++$i)
			{
				$json = str_replace(chr($i), "", $json);
			}
			$json = str_replace(chr(127), "", $json);

			// Remove the BOM (Byte Order Mark)
			if (0 === strpos(bin2hex($json), 'efbbbf')) $json = substr($json, 3);

			return $json;
		}		
		#endregion		
	}
?>