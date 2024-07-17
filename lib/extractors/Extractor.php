<?php
	/**
	* All video/audio site "extractor" classes MUST extend this base class.
	*/
	namespace MP3Converter\lib\extractors;

	use MP3Converter\lib\Config;
	use MP3Converter\lib\VideoConverter;

	// Extraction Base Class
	abstract class Extractor
	{
		// Common Fields
		protected $_converter;
		protected $_isCurlError = false;
		protected $_headers = array();
		protected $_mainUserAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/535.19 (KHTML, like Gecko) Ubuntu/12.04 Chromium/18.0.1025.168 Chrome/18.0.1025.168 Safari/535.19';
		protected $_ipVersion = -1;  // 4 = IPv4, 6 = IPv6, -1 = no preference
		protected $_downloadReqHeaders = array();

		#region Common Public Methods
		/**
		* Instantiate class and initialize class variables.
		*
		* @param VideoConverter $converter Instance of VideoConverter class
		* @return void
		*/
		function __construct(VideoConverter $converter)
		{
			$this->_converter = $converter;
		}

		/**
		* Extract cookies from cURL HTTP response headers.
		*
		* @return string Formatted cookies string
		*/
		public function ExtractCookies()
		{
			$cookies = '';
			$cookieNames = array();
			$headers = array_reverse($this->_headers);
			foreach ($headers as $headr)
			{
				$cookies .= (preg_match('/^(Set-Cookie:\s*(\w+)=([^;]+))/i', $headr, $matches) == 1 && !in_array($matches[2], $cookieNames)) ? $matches[2] . "=" . $matches[3] . ";" : '';
				$cookieNames[] = $matches[2];
			}
			return trim($cookies, ";");	
		}

		/**
		* Make media download request headers from $_downloadReqHeaders and cURL HTTP response headers.
		*
		* @return array The download request headers
		*/
		public function MakeDownloadReqHeaders()
		{
			$headers = array();
			$responseHeaders = $this->_headers;
			if (!empty($this->_downloadReqHeaders))
			{
				foreach ($this->_downloadReqHeaders as $name => $value)
				{
					if (!empty($value))
					{
						$headers[] = $value;
					}
					else 
					{
						if ($name != "cookie")
						{
							$headerMatches = preg_grep('/^(' . preg_quote($name, "/") . ':)/i', $responseHeaders);
							if (!empty($headerMatches))
							{
								$headers = array_merge($headers, $headerMatches);
							}
						}
						else 
						{
							$headers[] = "Cookie: " . $this->ExtractCookies();	
						}
					}
				}
			}
			return $headers;
		}
		#endregion

		#region Common Protected Methods
		/**
		* Retrieve source code of remote video page.
		*
		* @param string $url Video page URL
		* @param string $postData POST data parameters
		* @param array $reqHeaders HTTP request headers
		* @return string Video page source code
		*/
		protected function FileGetContents($url, $postData='', $reqHeaders=array())
		{
			$converter = $this->GetConverter();
			$file_contents = '';
			$this->_headers = array();
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, $this->GetMainUserAgent());
			if (!empty($postData))
			{
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);					
			}
			if (!empty($reqHeaders))
			{
				curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
			}
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			if ($this->GetIpVersion() != -1)
			{
				curl_setopt($ch, CURLOPT_IPRESOLVE, constant("CURL_IPRESOLVE_V" . (string)$this->GetIpVersion()));
			}
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'AppendHttpHeader'));
			$file_contents = curl_exec($ch);
			$this->_isCurlError = curl_errno($ch) != 0;
			$curlInfo = curl_getinfo($ch);
			if (curl_errno($ch) == 0)
			{
				if ($converter->GetCurrentVidHost() == "YouTube" && ($curlInfo['http_code'] == '302' || $curlInfo['http_code'] == '301'))
				{
					if (isset($curlInfo['redirect_url']) && !empty($curlInfo['redirect_url']))
					{
						$file_contents = $this->FileGetContents($curlInfo['redirect_url']);
					}
				}
			}
			curl_close($ch);
			return $file_contents;
		}

		/**
		* Callback for collection of cURL HTTP response headers.
		*
		* @param resource $ch Curl resource handle
		* @param string $headr HTTP response header
		* @return int Length of header line
		*/
		protected function AppendHttpHeader($ch, $headr)
		{
			$this->_headers[] = $headr;
			return strlen($headr);				
		}
		#endregion

		#region Force child classes to define these methods
		/**
		* Retrieve info about a video.
		*
		* @param string $vidUrl Video page URL
		* @return array Info about the video
		*/
		abstract public function RetrieveVidInfo($vidUrl);

		/**
		* Extract all available source URLs for requested video.
		*
		* @return array Video source URLs
		*/
		abstract public function ExtractVidSourceUrls();
		#endregion

		#region Common Properties
		/**
		* Getter method that retrieves VideoConverter instance.
		*/
		protected function GetConverter()
		{
			return $this->_converter;
		}

		/**
		* Getter method that retrieves extractor configuration parameters.
		*/
		public function GetParams()
		{
			return $this->_params;
		}
		
		/**
		* Getter method that retrieves extractor user agent.
		*/		
		public function GetMainUserAgent()
		{
			return $this->_mainUserAgent;
		}

		/**
		* Getter method that retrieves "store" directory path.
		*/
		public function GetStoreDir()
		{
			return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'store' . DIRECTORY_SEPARATOR;
		}
		
		/**
		* Getter method that retrieves "Force IPv4" setting for outgoing HTTP requests to video/audio site.
		*/		
		public function GetIpVersion()
		{
			return $this->_ipVersion;
		}
		#endregion
	}
?>