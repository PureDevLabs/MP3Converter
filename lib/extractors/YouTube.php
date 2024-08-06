<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// YouTube Extractor Class
	class YouTube extends Extractor
	{
		// Constants	
		const _AGE_GATE_PATTERN = '/("og:restrictions:age" content="18\+")|("18\+" property="og:restrictions:age")/';
		const _VID_INFO_PATTERN = '/;ytplayer\.config\s*=\s*({.+?});ytplayer/s';
		const _VID_INFO_PATTERN2 = '/ytInitialPlayerResponse\s*=\s*({.+})\s*;(?!\S*?")/';
		const _VID_PLAYER_PATTERN = '/((src="([^"]*player[^"]*\.js)"[^>]*><\/script>)|("(jsUrl|PLAYER_JS_URL)"\s*:\s*"([^"]+)"))/is';
		const _VID_URL_PREFIX = 'https://www.youtube.com/watch?v=';
		const _HOMEPAGE_URL = 'https://www.youtube.com';
		const _COOKIES_FILE = 'ytcookies.txt';
		
		// Fields
		private $_cypherUsed = false;
		private $_videoWebpage = '';
		private $_videoInfo = array();
		private $_signatures = array();
		private $_xmlFileHandle = null;
		private $_jsonTemp = '';
		private $_jsPlayerUrl = '';
		private $_nsigs = array();
		private $_nodeJS = '';
		protected $_mainUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
		protected $_ipVersion = 6;
		protected $_params = array(
			'name' => 'YouTube',
			'abbreviation' => 'yt',
			'url_root' => array(
				'http://www.youtube.com/watch?v=',
				'http://m.youtube.com/watch?v=',
				'http://youtu.be/',
				'http://music.youtube.com/watch?v=',
				'http://www.youtube.com/shorts/',
				'http://youtube.com/shorts/'
			),
			'url_example_suffix' => 'HMpmI2F2cMs',
			'allow_https_urls' => true,
			'src_video_type' => 'flv',
			'video_qualities' => array(
				'hd' => 'hd720',  // high definition
				'hq' => 'large',  // high quality
				'sd' => 'medium',  // standard definition
				'ld' => 'small',  // low definition
				'au' => 'audio'  // audio only
			),
			'icon_style' => 'fab fa-youtube-square'
		);

		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$converter = $this->GetConverter();
			$videoInfo = array();
			$duration = array();
			$vidID = $converter->ExtractVideoId($vidUrl);
			$this->_videoInfo = $this->VideoInfo($vidID);
			//die(print_r($this->_videoInfo));
			$videoDetails = (!empty($this->_videoInfo['videoDetails'])) ? $this->_videoInfo['videoDetails'] : array();
			if (!empty($videoDetails))
			{
				$title = (isset($videoDetails['title']) && !empty($videoDetails['title'])) ? $videoDetails['title'] : '';
				$duration = (isset($videoDetails['lengthSeconds']) && !empty($videoDetails['lengthSeconds'])) ? array('duration' => $videoDetails['lengthSeconds']) : $duration;
			}
			$title = (empty($title)) ? 'unknown_' . time() : $title;
			$videoInfo = array('id' => $vidID, 'title' => $title, 'thumb_preview' => 'https://img.youtube.com/vi/'.$vidID.'/0.jpg') + $duration;
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
			$vidTrackTitle = $vidInfo['title'];
			$jsonInfo = $this->_videoInfo;
			$audioUrls = array();
			$fmtStreamMap = array();
			$adaptiveFmts = array();
			if (isset($jsonInfo['player_response']))
			{
				$pr = $jsonInfo['player_response'];
				//die(print_r($pr));
				extract($this->FormatPlayerResponse($pr, $jsonInfo, 'fmt_stream_map'));
				extract($this->FormatPlayerResponse($pr, $jsonInfo, 'adaptive_fmts'));				
			}
			if (isset($jsonInfo['adaptive_fmts']))
			{
				$adaptiveFmts = (empty($adaptiveFmts)) ? $this->ExtractAdaptiveFmts($jsonInfo['adaptive_fmts']) : $adaptiveFmts;
				//die(print_r($adaptiveFmts));
				array_walk($adaptiveFmts, function($url) use(&$audioUrls) {if (preg_match('/audio\/mp4/', $url) == 1) $audioUrls[] = $url;});
			}
			if (isset($jsonInfo['fmt_stream_map']))
			{
				$fmtStreamMap = (empty($fmtStreamMap)) ? $this->ExtractFmtStreamMap($jsonInfo['fmt_stream_map']) : $fmtStreamMap;
				//die(print_r($fmtStreamMap));					
			}
			//die(print_r($audioUrls));

			// Detect cypher used
			$urlQueryStr = parse_url($fmtStreamMap[0], PHP_URL_QUERY);
			if ($urlQueryStr !== false && !is_null($urlQueryStr))
			{
				parse_str($urlQueryStr, $queryStrVars);
				//die(print_r($queryStrVars));
				$this->_cypherUsed = isset($queryStrVars['s']);
			}

			//$urls = array_merge($fmtStreamMap, $audioUrls);
			$urls = $fmtStreamMap;
			foreach ($urls as $url)
			{
				$queryStr = parse_url($url, PHP_URL_QUERY);
				if ($queryStr !== false && !is_null($queryStr))
				{
					parse_str($queryStr, $vars);
					if (!empty($vars) && (!isset($vars['quality']) || in_array($vars['itag'], array('22', '43', '18'))))
					{
						$vidUrls[] = $this->PrepareDownloadLink($url, $vidTrackTitle, !isset($vars['quality']));
					}						
				}
			}
			//die(print_r($vidUrls));
			return array_reverse($vidUrls);
		}

		function UpdateSoftwareXml(array $updateVars=array())
		{
			$filePath = $this->GetStoreDir();
			if (is_null($this->GetSoftwareXml())) $this->SetSoftwareXml();
			$xmlFileHandle = $this->GetSoftwareXml();
			if (!is_null($xmlFileHandle))
			{
				$info = $xmlFileHandle->xpath('/software/info');
				if ($info !== false && !empty($info))
				{
					$lastError = (int)$info[0]->lasterror;
					$currentTime = time();					
					if ($currentTime - $lastError > 600)
					{
						$version = $info[0]->version;
						$updateUrlPrefix = 'http://puredevlabs.cc/update-video-converter-v3/v:' . $version . '/';
						$updateUrl = '';
						if (isset($updateVars['signature']) && !empty($updateVars['signature']))
						{
							$sigLength = strlen($updateVars['signature']);
							if (empty($this->GetJsPlayerUrl())) $this->SetJsPlayerUrl();
							$updateUrl = $updateUrlPrefix . 'sl:' . $sigLength . '/jp:' . base64_encode($this->GetJsPlayerUrl());
						}
						else
						{
							$updateUrl = $updateUrlPrefix . 'rp:1';
						}
						//die($updateUrl);
						$updateResponse = file_get_contents($updateUrl);
						if ($updateResponse !== false && !empty($updateResponse))
						{
							$cookies = $this->RetrieveCookies();
							if ($updateResponse != "You have the newest version.")
							{
								$sxe2 = new \SimpleXMLElement($updateResponse);
								$sxe2->info[0]->lasterror = $currentTime;
								$sxe2->requests[0]->cookies = $cookies;
								$newXmlContent = $sxe2->asXML();
							}
							else
							{
								$xmlFileHandle->info[0]->lasterror = $currentTime;
								$xmlFileHandle->requests[0]->cookies = $cookies;
								$newXmlContent = $xmlFileHandle->asXML();
							}
							$fp = fopen($filePath . 'software2.xml', 'w');
							if ($fp !== false)
							{
								$lockSucceeded = false;
								if (flock($fp, LOCK_EX))
								{
									$lockSucceeded = true;
									fwrite($fp, $newXmlContent);
									flock($fp, LOCK_UN);
								}
								fclose($fp);
								if ($lockSucceeded)
								{
									rename($filePath . "software2.xml", $filePath . "software.xml");
									chmod($filePath . "software.xml", 0777);
								}
								else
								{
									unlink($filePath . 'software2.xml');
								}
							}
						}
					}
				}
				else
				{
					unlink($filePath . 'software.xml');
				}			
			}
		}
		
		function ExtractItagFromUrl($url)
		{
			$urlParts = parse_url($url);
			parse_str($urlParts['query'], $vars);
			return (isset($vars['itag'])) ? $vars['itag'] : '';
		}
		#endregion

		#region Private "Helper" Methods
		private function RetrieveCookies()
		{
			$cookies = "";
			$cookieFile = $this->GetStoreDir() . self::_COOKIES_FILE;
			if (is_file($cookieFile) && (int)filesize($cookieFile) > 0)
			{
				$cookiefileArr = file($cookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				if (is_array($cookiefileArr) && !empty($cookiefileArr))
				{
					$cookieStr = array_pop($cookiefileArr);
					if (preg_match('/^(([^;]+=[^;]+;)+)$/', trim($cookieStr)) == 1)
					{
						$cookies = base64_encode(trim($cookieStr));
					}
				}
			}
			return $cookies;			
		}

		private function VideoInfoRequest($vidId, $reqType)
		{
			$response = '';
			if (is_null($this->GetSoftwareXml())) $this->SetSoftwareXml();
			$xmlFileHandle = $this->GetSoftwareXml();
			if (!is_null($xmlFileHandle))
			{
				$requestParams = $xmlFileHandle->xpath('/software/requests');
				if ($requestParams !== false && !empty($requestParams))
				{
					//die(print_r($requestParams));	
					$rp = $requestParams[0];
					$cookies = (isset($rp->cookies) && !empty($rp->cookies)) ? trim(base64_decode((string)$rp->cookies)) : '';
					if ($reqType == "vidPage")
					{												
						$response = $this->FileGetContents(self::_VID_URL_PREFIX . $vidId . "&hl=en&persist_hl=1", '', array('Cookie: ' . $cookies));
						//die($response);												
					}
				}
			}
			$this->_videoWebpage = trim($response);
			return trim($response);
		}

		private function VideoInfo($vidId)
		{
			$jsonInfo = array();
			// Try Scraping Video Page
			$reqType = "vidPage";
			$response = $this->VideoInfoRequest($vidId, $reqType);
			if (!$this->CheckValidVidInfo($response, $reqType))
			{
				$this->UpdateSoftwareXml();
			}
			else
			{									
				$jsonInfo = (!empty($this->_jsonTemp)) ? $this->PopulateJsonData() : $jsonInfo;
			}
			return (json_last_error() == JSON_ERROR_NONE) ? $jsonInfo : array();
    	}

		private function PopulateJsonData()
		{
			$jsonObj = $this->_jsonTemp;
			//die(print_r($jsonObj));
			$jsonInfo = array(
				'adaptive_fmts' => isset($jsonObj['args']['adaptive_fmts']) ? $jsonObj['args']['adaptive_fmts'] : '',
				'fmt_stream_map' => isset($jsonObj['args']['url_encoded_fmt_stream_map']) ? $jsonObj['args']['url_encoded_fmt_stream_map'] : '',
				'player_response' => (!isset($jsonObj['args']['player_response'])) ? ((!isset($jsonObj['streamingData'])) ? '' : $jsonObj['streamingData']) : json_decode($jsonObj['args']['player_response'], true),
				'videoDetails' => (!isset($jsonObj['videoDetails'])) ? ((!isset($jsonObj['player_response']['videoDetails'])) ? '' : $jsonObj['player_response']['videoDetails']) : $jsonObj['videoDetails']
			);
			return $jsonInfo;
		}
    	
    	private function CheckValidVidInfo($response, $reqType)
    	{
    		$isValid = !empty($response);
    		if ($isValid)
    		{
    			$response = $this->VidInfoPatternMatches($response);
    			$this->_jsonTemp = $json = json_decode($response, true);
				$this->_jsonTemp = $json = (json_last_error() != JSON_ERROR_NONE) ? $this->CleanJson($response) : $json;
				//die(json_last_error_msg());
				//die(print_r($json));
    			//die(print_r($this->_headers));
    			$responseCode = (!empty($this->_headers) && preg_match('/^(HTTP\/\d(\.\d)?\s+(\d{3}))/i', $this->_headers[0], $rcmatches) == 1) ? $rcmatches[3] : '0';
    			$isValid = json_last_error() == JSON_ERROR_NONE && !isset($json['error']) && isset($json['playabilityStatus']['status']) && $json['playabilityStatus']['status'] != "LOGIN_REQUIRED" && $responseCode == '200';
    		}
    		//return false;
    		return $isValid;
    	}

		protected function VidInfoPatternMatches($videoPage)
		{
			$json = '{}';
			if (preg_match(self::_VID_INFO_PATTERN, $videoPage, $matches) == 1 || preg_match(self::_VID_INFO_PATTERN2, $videoPage, $matches2) == 1)
			{
				$matched = (!empty($matches)) ? $matches : $matches2;
				$json = $matched[1];
			}
			return $json;
		}

		protected function CleanJson($json)
		{
			$cleanedJson = [];
			$tries = ["current" => 0, "max" => 10];
			while (json_last_error() != JSON_ERROR_NONE && $tries["current"] < $tries["max"])
			{
				$json = preg_replace('/};(?!.*?};).*/s', "", $json);
				//die($json);
				$cleanedJson = json_decode($json . "}", true);
				//die(print_r($cleanedJson));
				$tries["current"]++;
			}
			return $cleanedJson;
		}
		
		private function ExtractFmtStreamMap($fmtStreamMap)
		{
			$formats = array();
			$urls = urldecode(urldecode($fmtStreamMap));
			//die($urls);
			if (preg_match('/^((.+?)(=))/', $urls, $matches) == 1)
			{
				$urlsArr = preg_split('/,'.preg_quote($matches[0], '/').'/', $urls, -1, PREG_SPLIT_NO_EMPTY);
				//print_r($urls);
				//print_r($urlsArr);
				$urlsArr2 = array();
				foreach ($urlsArr as $url)
				{
					if (preg_match('/,([a-zA-Z0-9_-]+=)/', $url, $matchArr) == 1)
					{
						$urlArr = preg_split('/,([a-zA-Z0-9_-]+=)/', $url, -1, PREG_SPLIT_NO_EMPTY);
						foreach ($urlArr as $k => $u)
						{
							$urlsArr2[] = ($k > 0) ? $matchArr[1].$u : $u;
						}
					}
					else
					{
						$urlsArr2[] = $url;
					}
				}
				//print_r($urlsArr2);
				foreach ($urlsArr2 as $url)
				{
					$inUrlsArr = count(preg_grep('/^('.preg_quote($url, '/').')/', $urlsArr)) > 0;
					if (($urlsArr == $urlsArr2 && $matches[0] != 'url=') || ($urlsArr != $urlsArr2 && !$inUrlsArr && preg_match('/^(url=)/', $url) != 1) || ($urlsArr != $urlsArr2 && $inUrlsArr && $matches[0] != 'url='))
					{
						$url = ($url != $urlsArr2[0] && $inUrlsArr) ? $matches[0].$url : $url;
						$urlBase = preg_replace('/(.+?)(url=)(.+?)(\?)(.+)/', "$3$4", $url);
						$urlParams = preg_replace('/(.+?)(url=)(.+?)(\?)(.+)/', "$1$5", $url);
						$url = $urlBase . "&" . $urlParams;
					}
					else
					{
						$url = preg_replace('/^(url=)/', "", $url);
					}
					$formats[] = $url;
				}
			}
			//die(print_r($formats));
			return $formats;
		}

		private function ExtractAdaptiveFmts($adaptiveFmts)
		{
			$formats = array();
			$adaptiveUrls = urldecode(urldecode($adaptiveFmts));
			//die($adaptiveUrls);
			if (preg_match('/^((.+?)(=))/', $adaptiveUrls, $matches) == 1)
			{
				$adaptiveUrlsArr = preg_split('/,'.preg_quote($matches[0], '/').'/', $adaptiveUrls, -1, PREG_SPLIT_NO_EMPTY);
				//die(print_r($adaptiveUrlsArr));
				$adaptiveUrlsArr2 = array();
				array_walk($adaptiveUrlsArr, function($url) use(&$adaptiveUrlsArr2, $adaptiveUrlsArr, $matches) {$adaptiveUrlsArr2[] = ($url != $adaptiveUrlsArr[0]) ? $matches[0] . $url : $url;});
				//die(print_r($adaptiveUrlsArr2));

				$adaptiveUrlsArr3 = array();
				$adaptiveAudioUrls = array();
				foreach ($adaptiveUrlsArr2 as $adaptiveUrl)
				{
					if (preg_match_all('/,(([^=,\&]+)(=))/', $adaptiveUrl, $matches2) > 0)
					{
						//die(print_r($matches2));
						$splitPattern = '';
						array_walk($matches2[0], function($m, $key) use(&$splitPattern, $matches2) {$splitPattern .= preg_quote($m, '/') . (($key != count($matches2[0])-1) ? "|" : "");});
						$audioUrls = preg_split('/('.$splitPattern.')/', $adaptiveUrl, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
						//die(print_r($audioUrls));
						$lastAdaptiveUrl = array_shift($audioUrls);
						//die(print_r($audioUrls));
						$audioUrls2 = array();
						array_walk($audioUrls, function($url, $key) use(&$audioUrls2, $audioUrls) {if ($key % 2 == 0 && isset($audioUrls[$key + 1])) $audioUrls2[] = trim($url, ",") . $audioUrls[$key + 1];});
						//die(print_r($audioUrls2));
						$adaptiveUrlsArr3[] = $lastAdaptiveUrl;
						$adaptiveAudioUrls = array_merge($adaptiveAudioUrls, $audioUrls2);
					}
					else
					{
						$adaptiveUrlsArr3[] = $adaptiveUrl;
					}
				}
				//die(print_r($adaptiveUrlsArr3));
				//die(print_r($adaptiveAudioUrls));
				$adaptiveUrlsArr3 = array_merge($adaptiveUrlsArr3, $adaptiveAudioUrls);
				foreach ($adaptiveUrlsArr3 as $url)
				{
					if (preg_match('/^(url=)/', $url) != 1)
					{
						$urlBase = preg_replace('/(.+?)(url=)(.+?)(\?)(.+)/', "$3$4", $url);
						$urlParams = preg_replace('/(.+?)(url=)(.+?)(\?)(.+)/', "$1$5", $url);
						$url = $urlBase . "&" . $urlParams;
					}
					else
					{
						$url = preg_replace('/^(url=)/', "", $url);
					}
					$formats[] = $url;
				}
			}
			//die(print_r($formats));
			return $formats;
		}

		private function FormatPlayerResponse(array $pr, array $jsonInfo, $fmtType)
		{
			//die(print_r($pr));
			$isAdaptiveFmts = $fmtType == "adaptive_fmts";
			$arrName = ($isAdaptiveFmts) ? "adaptiveFmts" : "fmtStreamMap";
			${$arrName} = array();
			$fmtName = ($isAdaptiveFmts) ? "adaptiveFormats" : "formats";
			$streamingData = (!isset($pr['streamingData'][$fmtName])) ? ((!isset($pr[$fmtName])) ? '' : $pr[$fmtName]) : $pr['streamingData'][$fmtName];
			if (is_array($streamingData))
			{
				$jsonInfo[$fmtType] = '';
				foreach ($streamingData as $format)
				{
					if (isset($format['url']))
					{
						$urlParts = parse_url($format['url']);
						parse_str($urlParts['query'], $vars);
						if (!isset($vars['type'])) $vars['type'] = urlencode(stripslashes($format['mimeType']));
						if ($isAdaptiveFmts && preg_match('/^(video)/', $format['mimeType']) == 1 && !isset($vars['quality_label'])) $vars['quality_label'] = $format['qualityLabel'];
						if (!$isAdaptiveFmts && !isset($vars['quality'])) $vars['quality'] = $format['quality'];
						$queryStr = http_build_query($vars, '', '&');
						$format['url'] = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . '?' . $queryStr;
						${$arrName}[] = $format['url'];
					}
					elseif (isset($format['cipher']) || isset($format['signatureCipher']))
					{
						$jsonInfo[$fmtType] .= "type=" . urlencode(stripslashes($format['mimeType']));
						$jsonInfo[$fmtType] .= ($isAdaptiveFmts) ? ((preg_match('/^(video)/', $format['mimeType']) == 1) ? "&quality_label=" . $format['qualityLabel'] : "") : "&quality=" . $format['quality'];
						$jsonInfo[$fmtType] .= "&" . ((isset($format['signatureCipher'])) ? $format['signatureCipher'] : $format['cipher']);
						$jsonInfo[$fmtType] .= ($format != end($streamingData)) ? "," : "";
					}
				}
				//die($jsonInfo[$fmtType]);
			}
			return compact('jsonInfo', $arrName);
		}

		private function PrepareDownloadLink($url, $vidTrackTitle, $isAdaptiveFmt)
		{
			//$url = preg_replace('/(.*)(itag=\d+&)(.*?)/', '$1$3', $url, 1);
			//$url = preg_replace('/&sig=|&s=/', "&signature=", $url);
			$url = trim($url, ',');
			$urlParts = parse_url($url);
			parse_str($urlParts['query'], $vars);
			
			$sigParamNames = array('sig' => 0, 's' => 1, 'signature' => 2);
			$sigParamName = (!isset($vars['s'], $vars['sp'])) ? ((!isset($vars['sig'])) ? "signature" : "sig") : $vars['sp'];
			$vars[$sigParamName] = (!isset($vars['sig'])) ? ((!isset($vars['s'])) ? ((!isset($vars['signature'])) ? "" : $vars['signature']) : $vars['s']) : $vars['sig'];
			unset($sigParamNames[$sigParamName]);
			foreach ($sigParamNames as $pname => $num)
			{
				unset($vars[$pname]);
			}
			$this->_signatures[$vars['itag']] = $vars[$sigParamName];
			
			if (isset($vars['c']) && preg_match('/^(web)$/i', $vars['c']) == 1 && isset($vars['n']))
			{
				$vars['n'] = $this->DecryptNSigCypher($vars['n']);
			}
			
			if (isset($vars['type'])) $vars['type'] = urlencode($vars['type']);
			if (!isset($vars['requiressl'])) $vars['requiressl'] = "yes";
			if (!isset($vars['ratebypass'])) $vars['ratebypass'] = "yes";
			if (!isset($vars['title'])) $vars['title'] = urlencode($vidTrackTitle);
			if ($isAdaptiveFmt)
			{
				unset($vars['bitrate'], $vars['init'], $vars['title'], $vars['projection_type'], $vars['type'], $vars['xtags'], $vars['index']);
			}
			if ($this->GetCypherUsed())
			{
				$vars[$sigParamName] = $this->DecryptCypher($vars[$sigParamName]);
			}
			//die(print_r($vars));
			$queryStr = http_build_query($vars, '', '&');
			$url = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'] . '?' . $queryStr;
			return $url;
		}

		protected function DecryptNSigCypher($nsig)
		{
			$converter = $this->GetConverter();
			if (isset($this->_nsigs[$nsig])) return $this->_nsigs[$nsig];
			$nsigDecrypted = $nsig;
			if (empty($this->_nodeJS))
			{
				if (empty($this->GetJsPlayerUrl())) $this->SetJsPlayerUrl();
				$playerUrl = (preg_match('/^((\/{1})(?=\w))/i', $this->GetJsPlayerUrl()) == 1) ? 'http://www.youtube.com' . $this->GetJsPlayerUrl() : $this->GetJsPlayerUrl();
				//die($playerUrl);
				$playerJS = $this->FileGetContents($playerUrl);
				if (!empty($playerJS) && preg_match('/(?x)(?:\.get\("n"\)\)&&\(b=|(?:b=String\.fromCharCode\(110\)|(?P<str_idx>[a-zA-Z0-9_$.]+)&&\(b="nn"\[\+(?P=str_idx)\])(?:,[a-zA-Z0-9_$]+\(a\))?,c=a\.(?:get\(b\)|[a-zA-Z0-9_$]+\[b\]\|\|null)\)&&\(c=|\b(?P<var>[a-zA-Z0-9_$]+)=)(?P<nfunc>[a-zA-Z0-9_$]+)(?:\[(?P<idx>\d+)\])?\([a-zA-Z]\)(?(var),[a-zA-Z0-9_$]+\.set\("n"\,(?P=var)\),(?P=nfunc)\.length)/', $playerJS, $pmatch) == 1)
				{
				    $fname = $pmatch['nfunc'];
				    $findex = $pmatch['idx'];
				    if (preg_match('/var ' . preg_quote($fname, "/") . '=\[([^\]]+)\];/', $playerJS, $pmatch2) == 1)
				    {
				        $funcs = explode(",", $pmatch2[1]);
				        if (isset($funcs[$findex]))
				        {
				            $fname = $funcs[$findex];
				            $fNamePattern = preg_quote($fname, "/");
				            if (preg_match('/((function\s+' . $fNamePattern . ')|([\{;,]\s*' . $fNamePattern . '\s*=\s*function)|(var\s+' . $fNamePattern . '\s*=\s*function))\s*\(([^\)]*)\)\s*\{(.+?)\};\n/s', $playerJS, $nsigFunc) == 1)
				            {
        						//die("<pre>" . print_r($nsigFunc, true) . "</pre>");
        						$this->_nodeJS = $fname . ' = function(' . $nsigFunc[5] . '){' . $nsigFunc[6] . '}; console.log(' . $fname . '("%nsig%"));';
				            }
				        }
				    }
				}
			}
			if (!empty($this->_nodeJS))
			{
				//die($this->_nodeJS);
				$nodeJS = preg_replace('/%nsig%/', $nsig, $this->_nodeJS);
				exec($converter::_NODEJS . ' ' . $this->GetStoreDir() . 'nsig.js ' . escapeshellarg($nodeJS) . ' 2>&1', $nodeOutput, $resultCode);
				//echo "encrypted nsig: " . $nsig . "<br><br>";
				//echo "decrypted nsig: " . $nodeOutput[0];
				//die(print_r($nodeOutput));	
				$nsigDecrypted = ($resultCode == 0 && !empty($nodeOutput) && count($nodeOutput) == 1) ? $nodeOutput[0] : $nsigDecrypted;
			}
			$this->_nsigs[$nsig] = $nsigDecrypted;
			return $nsigDecrypted;
		}

        private function DecryptCypher($signature)
        {
			$s = $signature;
			if (is_null($this->GetSoftwareXml())) $this->SetSoftwareXml();
			$xmlFileHandle = $this->GetSoftwareXml();
			if (!is_null($xmlFileHandle))
			{
				$algo = $xmlFileHandle->xpath('/software/decryption/funcgroup[@siglength="' . strlen($s) . '"]/func');
				if ($algo !== false && !empty($algo))
				{
					//die(print_r($algo));
					foreach ($algo as $func)
					{
						$funcName = (string)$func->name;
						if (!function_exists($funcName))
						{
							eval('function ' . $funcName . '(' . (string)$func->args . '){' . preg_replace('/self::/', "", (string)$func->code) . '}');
						}
					}
					$s = call_user_func((string)$algo[0]->name, $s);
				}
			}
			$s = ($s == $signature) ? $this->LegacyDecryptCypher($s) : $s;
			return $s;
		}

        // Deprecated - May be removed in future versions!
        private function LegacyDecryptCypher($signature)
        {
            $s = $signature;
            $sigLength = strlen($s);
            switch ($sigLength)
            {
                case 93:
                	$s = strrev(substr($s, 30, 57)) . substr($s, 88, 1) . strrev(substr($s, 6, 23));
                	break;
                case 92:
                    $s = substr($s, 25, 1) . substr($s, 3, 22) . substr($s, 0, 1) . substr($s, 26, 16) . substr($s, 79, 1) . substr($s, 43, 36) . substr($s, 91, 1) . substr($s, 80, 3);
                    break;
                case 90:
                	$s = substr($s, 25, 1) . substr($s, 3, 22) . substr($s, 2, 1) . substr($s, 26, 14) . substr($s, 77, 1) . substr($s, 41, 36) . substr($s, 89, 1) . substr($s, 78, 3);
                	break;
                case 89:
                	$s = strrev(substr($s, 79, 6)) . substr($s, 87, 1) . strrev(substr($s, 61, 17)) . substr($s, 0, 1) . strrev(substr($s, 4, 56));
                	break;
                case 88:
                    $s = substr($s, 7, 21) . substr($s, 87, 1) . substr($s, 29, 16) . substr($s, 55, 1) . substr($s, 46, 9) . substr($s, 2, 1) . substr($s, 56, 31) . substr($s, 28, 1);
                    break;
                case 87:
                	$s = substr($s, 6, 21) . substr($s, 4, 1) . substr($s, 28, 11) . substr($s, 27, 1) . substr($s, 40, 19) . substr($s, 2, 1) . substr($s, 60);
                    break;
                case 84:
					$s = strrev(substr($s, 71, 8)) . substr($s, 14, 1) . strrev(substr($s, 38, 32)) . substr($s, 70, 1) . strrev(substr($s, 15, 22)) . substr($s, 80, 1) . strrev(substr($s, 0, 14));
                    break;
                case 81:
					$s = substr($s, 56, 1) . strrev(substr($s, 57, 23)) . substr($s, 41, 1) . strrev(substr($s, 42, 14)) . substr($s, 80, 1) . strrev(substr($s, 35, 6)) . substr($s, 0, 1) . strrev(substr($s, 30, 4)) . substr($s, 34, 1) . strrev(substr($s, 10, 19)) . substr($s, 29, 1) . strrev(substr($s, 1, 8)) . substr($s, 9, 1);
                    break;
                case 80:
					$s = substr($s, 1, 18) . substr($s, 0, 1) . substr($s, 20, 48) . substr($s, 19, 1) . substr($s, 69, 11);
                    break;
                case 79:
					$s = substr($s, 54, 1) . strrev(substr($s, 55, 23)) . substr($s, 39, 1) . strrev(substr($s, 40, 14)) . substr($s, 78, 1) . strrev(substr($s, 35, 4)) . substr($s, 0, 1) . strrev(substr($s, 30, 4)) . substr($s, 34, 1) . strrev(substr($s, 10, 19)) . substr($s, 29, 1) . strrev(substr($s, 1, 8)) . substr($s, 9, 1);
                	break;
                default:
                    $s = $signature;
            }
            return $s;
        }
		#endregion

		#region Properties
		public function GetCypherUsed()
		{
			return $this->_cypherUsed;
		}

		public function GetSignature($itag)
		{
			return (!isset($this->_signatures[$itag])) ? "" : $this->_signatures[$itag];
		}

		protected function GetVideoWebpage()
		{
			return $this->_videoWebpage;
		}

		private function SetSoftwareXml()
		{
			$xmlFileHandle = null;
			$filePath = $this->GetStoreDir();
			if (is_file($filePath . 'software.xml'))
			{
				$xmlContent = file_get_contents($filePath . 'software.xml');
				if ($xmlContent !== false && !empty($xmlContent))
				{
					$isEx = false;
					try {$sxe = @new \SimpleXMLElement($xmlContent);}
					catch (\Exception $ex) {$isEx = true;}
					if (!$isEx && is_object($sxe) && $sxe instanceof \SimpleXMLElement)
					{	
						$xmlFileHandle = $sxe;
					}
					else
					{
						unlink($filePath . 'software.xml');
					}					
				}
				else
				{
					unlink($filePath . 'software.xml');
				}				
			}
			else
			{
				$updateResponse = file_get_contents('http://puredevlabs.cc/update-video-converter-v2/v:0');
				if ($updateResponse !== false && !empty($updateResponse))
				{
					$sxe3 = new \SimpleXMLElement($updateResponse);
					$sxe3->info[0]->lasterror = time();
					$sxe3->requests[0]->cookies = $this->RetrieveCookies();
					$fp = fopen($filePath . 'software.xml', 'w');
					if ($fp !== false)
					{
						$lockSucceeded = false;
						if (flock($fp, LOCK_EX))
						{
							$lockSucceeded = true;
							fwrite($fp, $sxe3->asXML());
							flock($fp, LOCK_UN);
						}
						fclose($fp);
						if ($lockSucceeded)
						{
							chmod($filePath . "software.xml", 0777);
							if (is_file($filePath . 'software.xml')) 
							{
								$this->SetSoftwareXml();
							}
						}
						else
						{
							unlink($filePath . 'software.xml');
						}
					}
				}
			}			
			$this->_xmlFileHandle = ($xmlFileHandle != null) ? $xmlFileHandle : $this->_xmlFileHandle;
		}
		private function GetSoftwareXml()
		{
			return $this->_xmlFileHandle;
		}

		private function SetJsPlayerUrl()
		{
			$playerUrl = '';
			$vidPageSrc = $this->GetVideoWebpage();
			if (!empty($vidPageSrc) && preg_match(self::_VID_PLAYER_PATTERN, $vidPageSrc, $matches) == 1) 
			{
				$playerUrl = (empty($matches[3])) ? ((empty($matches[6])) ? $playerUrl : $matches[6]) : $matches[3];
			}
			//die(print_r($matches));
			$this->_jsPlayerUrl = $playerUrl;
		}
		private function GetJsPlayerUrl()
		{
			return $this->_jsPlayerUrl;
		}
		#endregion
	}
?>