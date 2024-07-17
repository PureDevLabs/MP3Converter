<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// TikTok Extractor Class
	class TikTok extends Extractor
	{
		// Fields
        protected $_params = array(
            'name' => 'TikTok',
            'abbreviation' => 'tt',
            'url_root' => array(
                'http://www.tiktok.com/#wildcard#/video/',
                'http://m.tiktok.com/v/',
				'http://vm.tiktok.com/'
            ),
            'url_example_suffix' => '6862441166835600646',
            'allow_https_urls' => true,
            'src_video_type' => 'mp4',
            'video_qualities' => array(
                'hd' => 'src_hd',  // high definition
                'hq' => 'src_hd',  // high quality
                'sd' => 'src_sd',  // standard definition
                'ld' => 'src_sd'  // low definition
            ),
            'icon_style' => 'fab fa-tiktok'
        );
		protected $_mainUserAgent = 'facebookexternalhit/1.1';
		protected $_downloadReqHeaders = array(
			'referer' => 'Referer: https://www.tiktok.com/',
			'cookie' => ''
		);
		private $_avSources = array(
			'play_addr',
			'download_addr',
			'play_addr_h264',
			'play_addr_bytevc1',
			//'play_url'
		);
		private $_imgSources = array(
			'cover',
			'origin_cover'
		);

		// Constants
		const _API_APP_VERSIONS = array(
			['26.1.3', '260103'],
			['26.1.2', '260102'],
			['26.1.1', '260101'],
			['25.6.2', '250602']
		);
		const _API_APP_NAME = 'trill';
		const _API_AID = 1180;
		const _API_HOSTNAME = 'api22-normal-c-useast2a.tiktokv.com';
	
		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$converter = $this->GetConverter();
			$vidID = preg_replace('/(\..+)$/', "", $converter->ExtractVideoId($vidUrl));
			$vidImage = 'https://img.youtube.com/vi/oops/1.jpg';
			$vTitle = 'Unknown';
			if (preg_match('/^(https?:\/\/vm\.)/', $vidUrl) == 1)
			{
				$appRequest = $this->FileGetContents($vidUrl);
				//die(print_r($this->_headers));
				$redirects = preg_grep('/^(Location:\s*)/', $this->_headers);
				if ($redirects !== false && !empty($redirects))
				{
					$effectiveUri = trim(preg_replace('/^(Location:\s*)/', "", current(array_reverse($redirects))));
					$vidID = $converter->ExtractVideoId($effectiveUri);
				}
			}
            $videoInfo = $this->SendApiRequest(compact('converter', 'vidUrl', 'vidID', 'vidImage', 'vTitle'));
			if (!isset($videoInfo['src_sd'], $videoInfo['src_hd']) || (empty($videoInfo['src_sd']) && empty($videoInfo['src_hd'])))
			{
				$videoInfo = $this->SendWebRequest(compact('converter', 'vidUrl', 'vidID', 'vidImage', 'vTitle'));
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
			return $vidUrls;
		}
		#endregion
		
		#region Private "Helper" Methods
        private function SendApiRequest(array $params)
        {
            extract($params);
            $videoInfo = array();
			$response = '';
			foreach (self::_API_APP_VERSIONS as $version)
			{
				$reqVars = array(
					'aweme_id' => $vidID,
					'version_name' => $version[0],
					'version_code' => $version[1],
					'build_number' => $version[0],
					'manifest_version_code' => $version[1],
					'update_version_code' => $version[1],
					'openudid' => str_shuffle('0123456789abcdef'),
					'uuid' => $this->GenRandomString('0123456789', 16),
					'_rticket' => time() * 1000,
					'ts' => time(),
					'device_brand' => 'Google',
					'device_type' => 'Pixel 7',
					'device_platform' => 'android',
					'resolution' => '1080*2400',
					'dpi' => 420,
					'os_version' => '13',
					'os_api' => '29',
					'carrier_region' => 'US',
					'sys_region' => 'US',
					'region' => 'US',
					'app_name' => self::_API_APP_NAME,
					'app_language' => 'en',
					'language' => 'en',
					'timezone_name' => 'America/New_York',
					'timezone_offset' => '-14400',
					'channel' => 'googleplay',
					'ac' => 'wifi',
					'mcc_mnc' => '310260',
					'is_my_cn' => 0,
					'aid' => self::_API_AID,
					'ssmix' => 'a',
					'as' => 'a1qwert123',
					'cp' => 'cbfhckdckkde1'
				);
				$reqHeaders = array(
					'Cookie: odin_tt=' . $this->GenRandomString('0123456789abcdef', 160),
					'User-Agent: com.ss.android.ugc.' . self::_API_APP_NAME . '/' . $version[1] . ' (Linux; U; Android 13; en_US; Pixel 7; Build/TD1A.220804.031; Cronet/58.0.2991.0)',
					'Accept: application/json'
				);
				$response = $this->FileGetContents('https://' . self::_API_HOSTNAME . '/aweme/v1/feed/?' . http_build_query($reqVars), '', $reqHeaders);
				if (!empty($response)) break;
			}
			//die($response);
			if (!empty($response))
			{
				$videoDetail = json_decode(trim($response), true);
				if (json_last_error() == JSON_ERROR_NONE)
				{
					//die(print_r($videoDetail));
					$vidInfoSrc = (isset($videoDetail['aweme_list'][0]['video'])) ? (array)$videoDetail['aweme_list'][0]['video'] : (array)$videoDetail['aweme_detail']['video'];
					$items = array();
					$imgs = array();
					$iterator = new \RecursiveArrayIterator($vidInfoSrc);
					$recursive = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);
					foreach ($recursive as $key => $value) 
					{
						if (in_array($key, $this->_imgSources, true) && isset($value['url_list']) && is_array($value['url_list']))
						{
							$imgs = array_unique(array_merge($imgs, $value['url_list']));
						}
						if (in_array($key, $this->_avSources, true) && isset($value['width'], $value['url_list']) && is_array($value['url_list']))
						{
							$items[$value['width']] = array_unique(array_merge((array)$items[$value['width']], $value['url_list']));
						}
					}
					if (!empty($items) && count((array)preg_grep('/^(\d+)$/', array_keys($items))) == count($items))
					{
						krsort($items);
						//die(print_r($items));
						$items = array_merge(...$items);
						//die(print_r($items));
						$mp3 = (array)preg_grep('/((\.mp3)(.*))$/', $items);
						$vidUrls = (!empty($mp3)) ? $mp3 : array_reverse($items);
						$vidUrls = array_values($vidUrls);
						$vidUrls = (count($vidUrls) == 1) ? array($vidUrls[0], $vidUrls[0]) : $vidUrls;
						//die(print_r($vidUrls));
						if (!empty($vidUrls))
						{
							$srcSd = (!empty($mp3)) ? $vidUrls[1] : $vidUrls[0];
							$srcHd = (!empty($mp3)) ? $vidUrls[0] : $vidUrls[1];
							$thumb = (!empty($imgs)) ? current($imgs) : $vidImage;
							$substrFunc = (function_exists('mb_substr')) ? 'mb_substr' : 'substr';
							$vTitle = (isset($videoDetail['aweme_detail']['desc']) && !empty($videoDetail['aweme_detail']['desc'])) ? $substrFunc(trim($videoDetail['aweme_detail']['desc']), 0, 100) : ((isset($videoDetail['aweme_list'][0]['desc']) && !empty($videoDetail['aweme_list'][0]['desc'])) ? $substrFunc(trim($videoDetail['aweme_list'][0]['desc']), 0, 100) : $vTitle);
							$duration = (isset($videoDetail['aweme_detail']['video']['duration'])) ? array('duration' => (int)$videoDetail['aweme_detail']['video']['duration'] / 1000) : ((isset($videoDetail['aweme_list'][0]['video']['duration'])) ? array('duration' => (int)$videoDetail['aweme_list'][0]['video']['duration'] / 1000) : array());
							$videoInfo = array('id' => $vidID, 'title' => $vTitle, 'thumb_preview' => $thumb, 'src_sd' => $srcSd, 'src_hd' => $srcHd) + $duration;
						}
					}
				}
			}
            return $videoInfo;
        }

		private function SendWebRequest(array $params)
        {
            extract($params);
			$videoInfo = array();
			$response = $this->FileGetContents($vidUrl, '', array('User-Agent: Mozilla/5.0'));
			if (!empty($response) && preg_match('/<script[^>]+\bid="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.+?)<\/script>/i', $response, $matches) == 1)
			{
				$json = json_decode($matches[1], true);
				//die(print_r($json));
				if (isset($json['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct']) && !empty($json['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct']))
				{
					$vidData = $json['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct'];
					if (isset($vidData['video']['bitrateInfo']) && is_array($vidData['video']['bitrateInfo']))
					{
						$videos = array();
						foreach ($vidData['video']['bitrateInfo'] as $vidInfo)
						{
							if (isset($vidInfo['PlayAddr']['UrlList'], $vidInfo['PlayAddr']['UrlKey']) && is_array($vidInfo['PlayAddr']['UrlList']) && !empty($vidInfo['PlayAddr']['UrlList']) && preg_match('/_(\d+)p_/', $vidInfo['PlayAddr']['UrlKey'], $qlmatch) == 1)
							{
								foreach ($vidInfo['PlayAddr']['UrlList'] as $playUrl)
								{
									$playUrl = json_decode('{"url":"' . $playUrl . '"}', true);
									if (isset($playUrl['url']) && !isset($videos[$qlmatch[1]]))
									{
										$videos[$qlmatch[1]] = $playUrl['url'];
									}
								}
							}
						}
						if (!empty($videos))
						{
							krsort($videos);
							$videos = array_values($videos);
							$srcSd = (isset($videos[1])) ? $videos[1] : $videos[0];
							$srcHd = $videos[0];
							$thumb = (isset($vidData['video']['originCover']) && !empty($vidData['video']['originCover'])) ? $vidData['video']['originCover'] : $vidImage;
							$thumb = current(json_decode('{"url":"' . $thumb . '"}', true));
							$substrFunc = (function_exists('mb_substr')) ? 'mb_substr' : 'substr';
							$vTitle = (isset($vidData['desc']) && !empty($vidData['desc'])) ? $substrFunc(trim($vidData['desc']), 0, 100) : $vTitle;
							$duration = (isset($vidData['video']['duration']) && !empty($vidData['video']['duration'])) ? array('duration' => (int)$vidData['video']['duration']) : array();
							$videoInfo = array('id' => $vidID, 'title' => $vTitle, 'thumb_preview' => $thumb, 'src_sd' => $srcSd, 'src_hd' => $srcHd) + $duration;
						}
					}
				}
			}
			return $videoInfo;
		}

		private function GenRandomString($chars, $length)
		{
			$randStr = '';
			for ($i = 0; $i < $length; $i++)
			{
				$randStr = $chars[mt_rand(0, strlen($chars) - 1)];
			}
			return $randStr;
		}
		#endregion
	}
?>