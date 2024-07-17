<?php
	/**
	* To install mod, upload this file to the software's "lib/extractors/" directory. That's it!
	*/
	namespace MP3Converter\lib\extractors;

	// Instagram Extractor Class
	class Instagram extends Extractor
	{
		// Fields
		protected $_params = array(
			'name' => 'Instagram',
			'abbreviation' => 'ig',
			'url_root' => array(
				'http://instagram.com/p/',
				'http://www.instagram.com/p/',
				'http://www.instagram.com/tv/',
				'http://www.instagram.com/reel/'
			),
			'url_example_suffix' => 'vIyKcyNdV-',
			'allow_https_urls' => true,
			'src_video_type' => 'mp4',
			'video_qualities' => array(
				'hd' => 'src_sd',  // high definition
				'hq' => 'src_sd',  // high quality
				'sd' => 'src_sd2',  // standard definition
				'ld' => 'src_sd2'  // low definition
			),
			'icon_style' => 'fab fa-instagram-square'
		);

		#region Public Methods
		function RetrieveVidInfo($vidUrl)
		{
			$videoInfo = array();
			$vidPage = $this->FileGetContents(trim(preg_replace('/(\?.*)$/', "", $vidUrl), "/") . '/embed/');
			//die($vidPage);
			if (preg_match('/window.__additionalDataLoaded\(\'[^\']+\',\s*(\{.+?\})\);/', $vidPage, $matched) == 1)
			{
				$jsonData = json_decode($matched[1], true);
				//die(print_r($jsonData));
				if (isset($jsonData['shortcode_media']))
				{
					$media = $jsonData['shortcode_media'];
					$caption = (isset($media['edge_media_to_caption']['edges'][0]['node']['text'])) ? $media['edge_media_to_caption']['edges'][0]['node']['text'] : $media['caption'];
					$isVideo = (isset($media['is_video'])) ? (bool)$media['is_video'] : true;
					$vTitleArr = explode("\n", wordwrap(preg_replace('/\n|\r|\t/', "", $this->UnicodeToHtmlEntities($caption)), 55, "\n", true));
					//die(print_r($vTitleArr));
					$vTitle = trim($vTitleArr[0]) . ((count($vTitleArr) > 1) ? "..." : "");
					$vTitle = (empty($vTitle)) ? "unknown" : $vTitle;
					$src1 = $src2 = (isset($media['video_url'])) ? $media['video_url'] : '';
					if (empty($src1) && empty($src2) && isset($media['edge_sidecar_to_children']['edges']) && is_array($media['edge_sidecar_to_children']['edges']))
					{
						foreach ($media['edge_sidecar_to_children']['edges'] as $edge)
						{
							if (isset($edge['node']['video_url']))
							{
								$media2 = $edge['node'];
								$src1 = $src2 = $media2['video_url'];
								$isVideo = true;
								break;
							}
						}
					}					
					$thumb = (!isset($media['display_src'])) ? ((!isset($media['display_url'])) ? 'https://img.youtube.com/vi/oops/1.jpg' : $media['display_url']) : $media['display_src'];
					$videoInfo = array('id' => $media['shortcode'], 'title' => $vTitle, 'thumb_preview' => $thumb, 'is_video_audio' => $isVideo, 'src_sd' => $src1, 'src_sd2' => $src2);
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

		#region Private "Helper" Methods
		private function UnicodeToHtmlEntities($str)
		{
			$output = json_encode((string)$str);
			$output = preg_replace_callback('/\\\u([0-9a-z]{4})/', function($matches){
				$entity = '&#x'. $matches[1] .';';
				$entityDecoded = html_entity_decode($entity, ENT_COMPAT | ENT_HTML401, 'UTF-8');
				//echo json_encode($entityDecoded) . "<br />";
				return (json_encode($entityDecoded) == "null") ? '' : $entity;
			}, $output);
			//die(print_r(json_decode($output)));
			return json_decode($output);
		}
		#endregion
	}
?>