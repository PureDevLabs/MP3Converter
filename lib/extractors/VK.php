<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// VK Extractor Class
	class VK extends Extractor
	{		
		// Fields
		protected $_params = array(
			'name' => 'VK',
			'abbreviation' => 'vk',
			'url_root' => array(
				'http://vk.com/wall',
				'http://vk.com/#wildcard#?w=wall',
				'http://m.vk.com/video',
				'http://new.vk.com/video',
				'http://vk.com/video/#wildcard#?z=video',
				'http://vk.com/#wildcard#?z=video',
				'http://vk.com/video?z=video',
				'http://vk.com/video'
			),
			'url_example_suffix' => '4643923_163339118',
			'allow_https_urls' => true,
			'src_video_type' => 'mp4',
			'video_qualities' => array(
				'hd' => 'url720',  // high definition
				'hq' => 'url480',  // high quality
				'sd' => 'url360',  // standard definition
				'ld' => 'url240'  // low definition
			),
			'icon_style' => 'fab fa-vk'
		);
		protected $_mainUserAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36';

		#region Public Methods	
		function RetrieveVidInfo($vidUrl)
		{
			$converter = $this->GetConverter();
			$vidHosts = $converter->GetVideoHosts();
			$vhosts = array();
			array_walk($vidHosts, function($val) use(&$vhosts) { $vhosts[$val['name']] = $val; });
			$videoInfo = array();
			if (preg_match('/((wall)[0-9_-]+)$/', $vidUrl) == 1)
			{
				$wallPost = $this->FileGetContents($vidUrl);
				if (!empty($wallPost) && preg_match('/<meta property="og:url" content="([^"]+)"\s*\/>/', $wallPost, $wallMatch) == 1 && preg_match('/((wall)[0-9_-]+)$/', $wallMatch[1]) != 1)
				{
					return $this->RetrieveVidInfo($wallMatch[1]);
				}
			}
			foreach ($vhosts['VK']['url_root'] as $urlRoot)
			{
				$wildcardRegex = ($converter::_PHP_VERSION >= 7.3) ? '/\\\#wildcard\\\#/' : '/#wildcard#/';				
				$vkPatternPart = preg_replace('/^(http)/', "$1(s)?", preg_replace($wildcardRegex, '[^\\\\/\?]+', preg_quote($urlRoot, '/')));
				$vidId = preg_replace('/^('.$vkPatternPart.')/', "", $vidUrl);
				if ($vidId != $vidUrl)
				{
					//die($vidId);
					$vidId = current(explode("/", urldecode($vidId)));	
					$reqHeaders = [
						'Referer: https://vk.com/al_video.php', 
						'X-Requested-With: XMLHttpRequest'
					];
					$postData = http_build_query([
						'act' => 'show',
						'al' => '1',
						'video' => $vidId
					]);
					$vidPage = $this->FileGetContents('https://vk.com/al_video.php', $postData, $reqHeaders);
					if (!empty($vidPage))
					{
						//die(print_r($vidPage));
						$vkInfo = iconv("cp1251", "utf-8", $vidPage);
						$jsonData = json_decode($vkInfo, true);
						if (json_last_error() == JSON_ERROR_NONE)
						{
							//die(print_r($jsonData));
							if (isset($jsonData['payload'][1][4]['player']['params'][0]))
							{
								$jsonData = $jsonData['payload'][1][4]['player']['params'][0];
								$vid = (isset($jsonData['vid'])) ? $jsonData['vid'] : '';
								$oid = (isset($jsonData['oid'])) ? $jsonData['oid'] : '';
								$title = (isset($jsonData['md_title'])) ? $jsonData['md_title'] : '';
								$thumb = (isset($jsonData['jpg'])) ? $jsonData['jpg'] : '';
								$urls = [
									'240' => ((isset($jsonData['url240'])) ? $jsonData['url240'] : ''),
									'360' => ((isset($jsonData['url360'])) ? $jsonData['url360'] : ''),
									'480' => ((isset($jsonData['url480'])) ? $jsonData['url480'] : ''),
									'720' => ((isset($jsonData['url720'])) ? $jsonData['url720'] : '')
								];
								$duration = (isset($jsonData['duration'])) ? array('duration' => $jsonData['duration']) : array();
								$videoInfo = array('id' => $vid, 'oid' => $oid, 'title' => $title, 'thumb_preview' => $thumb, 'url240' => $urls['240'], 'url360' => $urls['360'], 'url480' => $urls['480'], 'url720' => $urls['720']) + $duration;
								break;
							}
						}
					}					
				}
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
			foreach ($vidQualities as $fq)
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
	}
?>