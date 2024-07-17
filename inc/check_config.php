<?php
	use MP3Converter\lib\Config;

	$appRoot = pathinfo($_SERVER['PHP_SELF'], PATHINFO_DIRNAME);
	$appRoot .= ($appRoot != "/") ? "/" : "";
	$tests = array();
	$success = '<span style="color:green"><i class="fa fa-check"></i></span>';
	$failed = '<span style="color:red"><i class="fa fa-times"></i></span>';

	// Install binaries, if they are not already installed
	$possibleBins = array('curl', 'ffmpeg', 'node');
	$currentBin = '';
	$storeDir = dirname(__DIR__) . '/store/';
	if (!is_dir($storeDir . 'bin')) mkdir($storeDir . 'bin');
	$binDir = $storeDir . 'bin/';
	foreach ($possibleBins as $bin)
	{
		${$bin . 'InStore'} = is_file($binDir . $bin);
		$currentBin = (empty($currentBin) && isset($_POST['binToInstall']) && $_POST['binToInstall'] == $bin) ? $bin : $currentBin;
	}
	if (function_exists('exec') && !empty($currentBin) && !${$currentBin . 'InStore'})
	{
		exec('arch', $arch);
		if (!empty($arch))
		{
			$archBits = ($arch[0] == "x86_64") ? '64' : '32';
			switch ($currentBin)
			{
				case "ffmpeg":
					exec('uname -r', $kernelVersion);
					if (!empty($kernelVersion))
					{
						$kvNum = current(explode("-", $kernelVersion[0]));
						if (strnatcmp($kvNum, '2.6.32') >= 0)
						{
							exec('wget -O ' . $binDir . $currentBin . ' http://puredevlabs.cc/builds/' . $currentBin . '/' . $archBits);
						}
					}					
					break;	
				case "curl":
					exec('wget -O ' . $binDir . $currentBin . ' http://puredevlabs.cc/builds/' . $currentBin . '/' . $archBits);					
					break;
				case "node":
					if ($archBits == '64')
					{
						exec('wget -O ' . $binDir . $currentBin . ' http://puredevlabs.cc/builds/' . $currentBin . '/' . $archBits);	
					}			
					break;			
			}
			${$currentBin . 'InStore'} = is_file($binDir . $currentBin);
			${$currentBin . 'InStore'} = (${$currentBin . 'InStore'}) ? chmod($binDir . $currentBin, 0755) : ${$currentBin . 'InStore'};			
		}
	}	
	
	// Check authorized domains
	array_walk(Config::$_authorizedDomains, function(&$domain) {$domain = strtolower($domain);});
	$tests['domains'] = in_array(strtolower($_SERVER['HTTP_HOST']), Config::$_authorizedDomains);
	//$tests['domains'] = false;

	// "store" Folder permissions
	$appStorePerms = substr(decoct(fileperms($storeDir)), -4);
	$tmpFile = $storeDir . "tmp.txt";
	$fp = @fopen($tmpFile, "w");
	$isWritable = $fp !== false;
	if ($isWritable)
	{
		fclose($fp);
		unlink($tmpFile);
	}
	$tests['appStorePerms'] = $isWritable;
	//$tests['appStorePerms'] = false;

	// Check PHP version
	$phpVersion = explode(".", PHP_VERSION);
	$tests['php_version'] = version_compare(PHP_VERSION, '5.6.0') >= 0 && Config::_PHP_VERSION == $phpVersion[0] . "." . $phpVersion[1];
	//$tests['php_version'] = false;

	// Check for PHP open_basedir restriction
	$phpOpenBaseDir = ini_get('open_basedir');
	$noObdRestriction = empty($phpOpenBaseDir) || $phpOpenBaseDir == "no value";
	if (!empty($phpOpenBaseDir) && $phpOpenBaseDir != "no value")
	{
		$absAppDir = dirname(dirname(__FILE__)) . "/";
		$obDirs = explode(":", $phpOpenBaseDir);
		$dirPattern = '/^(';
		foreach ($obDirs as $dir)
		{
			$dirPattern .= '(' . preg_quote($dir, "/") . ')';
			$dirPattern .= ($dir != end($obDirs)) ? '|' : '';
		}
		$dirPattern .= ')/';
		$noObdRestriction = preg_match($dirPattern, $absAppDir) == 1;
	}
	$tests['phpOpenBaseDir'] = $noObdRestriction;
	//$tests['phpOpenBaseDir'] = false;

	// Check if PHP exec() is enabled and working
	$phpExecRuns = function_exists('exec');
	if ($phpExecRuns)
	{
		$ffmpegData = array();
		@exec(Config::_FFMPEG . ' -version', $ffmpegData);
		$phpExecRuns = isset($ffmpegData[0]) && !empty($ffmpegData[0]);
	}
	$tests['phpExec'] = $phpExecRuns;
	//$tests['phpExec'] = false;

	// Get default directories that PHP exec() is allowed to access
	$validExecPaths = getenv('PATH');
	$validPathArr = array_merge([dirname(__DIR__) . "/store/bin"], explode(":", $validExecPaths));
	$validPathArrTrunc = array_slice($validPathArr, 0, count($validPathArr) - 1);

	// Check for cURL
	$curlExists = array();
	@exec('type curl', $curlExists);
	$curlFound = false;
	if (!empty($curlExists))
	{
		$curlFound = preg_match('/^(curl is )/i', $curlExists[0]) == 1;
	}
	if (!$curlFound && $curlInStore)
	{
		$curlFound = $curlInStore;
	}	
	$tests['curlExists'] = $curlFound;
	//$tests['curlExists'] = false;

	if ($tests['curlExists'])
	{
		// Get cURL version
		$curlVersion = array();
		@exec((($curlInStore) ? $binDir : '') . 'curl -V', $curlVersion);
		if (!empty($curlVersion)) preg_match('/\d+\.\d+.\d+/', $curlVersion[0], $curlVersionNo);

		// Check for PHP cURL extension
		$tests['phpCurl'] = extension_loaded("curl");
		//$tests['phpCurl'] = false;

		if ($tests['phpCurl'])
		{
			$curlVersionInfo = curl_version();
			$curlVersionNo = array($curlVersionInfo['version']);

			// Check for DNS error resolving site domain name
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . $appRoot . "exec_ffmpeg.php");
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_NOBODY, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			$result = curl_exec($ch);
			$tests['dns'] = curl_errno($ch) == 0;
			curl_close($ch);
			//$tests['dns'] = false;

			if ($tests['dns'])
			{
				// Check for SSL/TLS
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "https://" . $_SERVER['HTTP_HOST'] . $appRoot . "contact.php");
				curl_setopt($ch, CURLOPT_HEADER, 1);
				curl_setopt($ch, CURLOPT_NOBODY, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 15);
				$result = curl_exec($ch);
				$tests['ssl'] = curl_errno($ch) == 0;
				curl_close($ch);
				//$tests['ssl'] = false;
			}
		}
	}

	// Check for FFmpeg install
	$ffmpegLocation = array();
	@exec('type ffmpeg', $ffmpegLocation);
	$ffmpegFound = false;
	$ffmpegPath = array();
	if (!empty($ffmpegLocation))
	{
		$ffmpegFound = preg_match('/^((ffmpeg is )(.+))/i', $ffmpegLocation[0], $ffmpegPath) == 1;
	}
	if (!$ffmpegFound && $ffmpegInStore)
	{
		$ffmpegFound = $ffmpegInStore;
		$ffmpegPath[3] = $binDir . 'ffmpeg';
	}
	if (!$ffmpegFound)
	{
		$isValidFFmpegPath = false;
		foreach ($validPathArr as $validPath)
		{
			$isValidFFmpegPath = preg_match('/^(' . preg_quote($validPath, "/") . ')/', Config::_FFMPEG) == 1;
			if ($isValidFFmpegPath) break;
		}
	}
	$tests['FFmpeg'] = $ffmpegFound && Config::_FFMPEG == trim($ffmpegPath[3]);
	//$tests['FFmpeg'] = false;
	//$isValidFFmpegPath = false;

	if ($tests['FFmpeg'])
	{
		// Check for FFmpeg version
		$ffmpegInfo = array();
		@exec(Config::_FFMPEG . ' -version', $ffmpegInfo);
		$tests['FFmpegVersion'] = isset($ffmpegInfo[0]) && !empty($ffmpegInfo[0]);
		//$tests['FFmpegVersion'] = false;

		// Check for codecs
		$libmp3lame = array();
		@exec(Config::_FFMPEG . ' -codecs | grep -E "(\s|[[:space:]])libmp3lame(\s|[[:space:]])"', $libmp3lame);
		$tests['libmp3lame'] = isset($libmp3lame[0]) && !empty($libmp3lame[0]) && preg_match('/E/', current(preg_split('/\s/', $libmp3lame[0], -1, PREG_SPLIT_NO_EMPTY))) == 1;
		//$tests['libmp3lame'] = false;
	}

	// Check for at least one site module installed
	$modCount = 0;
	$modules = [];
	$iterator = new DirectoryIterator(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'extractors' . DIRECTORY_SEPARATOR);
	while ($iterator->valid())
	{
		$fname = $iterator->getFilename();
		if ($fname != '.' && $fname != '..' && $fname != "Extractor.php") 
		{
			$modules[] = $fname;
			$modCount++;
		}
		$iterator->next();
	}
	$tests['modInstalled'] = $modCount > 0;
	//$tests['modInstalled'] = false;

	if (in_array("YouTube.php", $modules))
	{
		// Check for Node.js install
		$nodeJS = array();
		@exec('type node', $nodeJS);
		$nodeFound = false;
		$nodePath = array();
		if (!empty($nodeJS))
		{
			$nodeFound = preg_match('/^((node is )(.+))/i', $nodeJS[0], $nodePath) == 1;
		}
		if (!$nodeFound && $nodeInStore)
		{
			$nodeFound = $nodeInStore;
			$nodePath[3] = $binDir . 'node';
		}
		if (!$nodeFound)
		{
			$isValidNodePath = false;
			foreach ($validPathArr as $validPath)
			{
				$isValidNodePath = preg_match('/^(' . preg_quote($validPath, "/") . ')/', Config::_NODEJS) == 1;
				if ($isValidNodePath) break;
			}
		}
		$tests['nodejs'] = $nodeFound && Config::_NODEJS == trim($nodePath[3]);
		//$tests['nodejs'] = false;
		//$isValidNodePath = false;

		if ($tests['nodejs'])
		{
			// Check for Node.js version
			$nodeInfo = array();
			@exec(Config::_NODEJS . ' -v', $nodeInfo);
			$tests['NodeVersion'] = isset($nodeInfo[0]) && !empty($nodeInfo[0]);
			//$tests['NodeVersion'] = false;
		}
	}

	// Get Config constant and variable line numbers
	$configVars = array('_PHP_VERSION', '_FFMPEG', '_NODEJS', '_TEMPVIDDIR', '_LOGSDIR', '_SONGFILEDIR', '\$_authorizedDomains');
	$configLines = file(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . "Config.php");
	$linesPattern = '/^((\s*)((const )|(public static ))((' . implode(")|(", $configVars) . ')))/';
	$linesArr = preg_grep($linesPattern, $configLines);
	$lineNumsArr = array();
	foreach ($linesArr as $num => $line)
	{
		$lineNumsArr[trim(preg_replace('/^((\s*)((const )|(public static ))(\S+)(.*))$/', "$6", $line))] = $num + 1;
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Check Configuration</title>
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/meyer-reset/2.0/reset.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style type="text/css">
	@import url(https://fonts.googleapis.com/css?family=Architects+Daughter);
	body {background-color:#ccc;font-family:Verdana,Arial;font-size:13px;line-height:16px;}
	h3 {font-size:20px;font-weight:bold;margin:15px 0 25px 0;text-align:center;}
	h4, h5 {font-size:22px;margin:25px 0 15px 0;font-family:"Architects Daughter",Verdana;color:#f9f9f9;padding:10px 12px;background:#111;}
	h5 {font-size:4px;padding:0;}
	ol, ul {padding-left:0;}
	ul {margin-left:11px;}
	ul li, p {margin:15px 0;}
	ul ul {margin-left:9px;}
	ul ul li {padding-left:9px;text-indent:-9px;}
	ul ul ul {margin-left:0;}
	#container {width:720px;margin:20px auto;padding:20px;background-color:#f9f9f9;}
	.response span {text-indent:2px;font-weight:bold;font-style:italic;font-size:18px;}
	.orange {color:#FB9904;font-size:15px;}
	.dark-orange {color:#cc0000;font-weight:bold;}
	.italic {font-style:italic;}
	.bold {font-weight:bold;}
	.buttons {text-align:center;margin:25px auto 5px auto;}
	.alert-dismissible .btn-close {top:-2px;}
	.btn {font-size:.9rem;}
	.tooltip {font-size:14px;}
	.tooltip-inner {max-width:250px;}
	.fade {
		transition:opacity 0.15s linear !important;
	}
	.modal.fade .modal-dialog {
		transition:-webkit-transform 0.3s ease-out !important;
		transition:transform 0.3s ease-out !important;
		transition:transform 0.3s ease-out,-webkit-transform 0.3s ease-out !important;
	}
	.modal h4 {margin:0;width:100%;}
	.modal .btn-light {border-color:#ccc;}
	.modal .btn-light.active, .modal .btn-light.focus, .modal .btn-light:active, .modal .btn-light:focus, .modal .btn-light:hover {background-color:#e6e6e6;border-color:#adadad;}
  </style>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
  <script type="text/javascript">
  //<![CDATA[
  	"use strict";
  	$(document).ready(function(){
		$(".rerun").on("click", function(){
			location.href = location.href;
		});

		$(".printpage").on("click", function(){
			window.print();
		});

		$(".popup").on("click", function(){
			runTests();
		});

		$("#leave").on("click", function(){
			location.href += "?config=complete";
		});
		
		$(".binInstall").on("submit", function(e){	
			if (!runTests('appStorePerms')) 
			{
				e.preventDefault();
			}
			else
			{
				$(this).find('i').removeClass('fa-cogs').addClass('fas fa-sync-alt fa-spin');
			}
		});		
	});
	
	function runTests(testName)
	{
		var testsPassed = true;
		var isExit = typeof testName == "undefined";
		var tests = {
			phpExec: <?php echo ($tests['phpExec']) ? "true" : "false"; ?>,
			appStorePerms: <?php echo ($tests['appStorePerms']) ? "true" : "false"; ?>,
			domains: <?php echo ($tests['domains']) ? "true" : "false"; ?>
		};
		if (!isExit && typeof tests[testName] != "undefined")
		{
			var testResult = tests[testName];
			tests = {};
			tests[testName] = testResult;
		}
		for (var test in tests)
		{
			if (!tests[test])
			{
				$("#fix-" + test).css("display", "inline");
				var offset = $("#fix-" + test).offset();
				offset.top -= 100;
				$("html, body").animate({
					scrollTop: offset.top
				}, 400, function(){
					$("#fix-" + test).tooltip('show');
				});
				testsPassed = false;
				break;
			}
		}
		if (isExit && testsPassed) 
		{
			var exitModal = new bootstrap.Modal($('#exitModal'), {});
			exitModal.show();
		}
		return testsPassed;
	}
  //]]>	
  </script>
</head>
<body>
	<div id="container">
		<h3>Check Your Server/Software Configuration. . .</h3>
		<p>This page will check your server and software installations for errors. Please ensure that you read through the results thoroughly and do not proceed until all tests have passed.</p>
		<?php if (!$tests['php_version'] || !$tests['phpExec'] || !$tests['phpOpenBaseDir'] || !$tests['curlExists'] || !$tests['phpCurl'] || !$tests['dns'] || !$tests['FFmpeg'] || !$tests['FFmpegVersion'] || !$tests['libmp3lame'] || (in_array("YouTube.php", $modules) && (!$tests['nodejs'] || !$tests['NodeVersion'])) || !$tests['appStorePerms'] || !$tests['domains'] || !$tests['modInstalled']) { ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<b><i class="fa fa-exclamation-triangle"></i> Warning!</b> &nbsp;You should <b>at least</b> confirm that all <b>"Required"</b> settings are OK!
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php } ?>
		<div class="alert alert-info alert-dismissible fade show" role="alert">
			<b><i class="fa fa-question-circle"></i> Questions?:</b> &nbsp;Get <b>help troubleshooting common issues</b> using "<a href="docs/faq.html" onclick="window.open(this.href); return false;" class="alert-link">The Official FAQ</a>".
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
		<div class="alert alert-warning alert-dismissible fade show" role="alert">
			<b><i class="fa fa-info-circle"></i> Support:</b> &nbsp;Find the <b>full array of support options</b> in the <a href="docs/" onclick="window.open(this.href); return false;" class="alert-link">Software Documentation</a>.
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
		<div class="buttons">
			<button class="btn btn-primary rerun"><i class="fas fa-sync-alt"></i> Run the tests again.</button> <button class="btn btn-success printpage"><i class="fa fa-print"></i> Print this page.</button> <button class="btn btn-danger popup"><i class="fas fa-sign-out-alt"></i> Get me out of here!</button>
		</div>

		<h4><u>Required</u> settings. . .</h4>
		<ul>
			<li><span class="italic bold">Software Dependencies</span>
				<ul>
					<li>PHP version: &nbsp;&nbsp;&nbsp;<?php echo PHP_VERSION; ?><span class="response"><span> <?php echo ($tests['php_version']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['php_version'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that your PHP version is at least 5.6 or above and that the _PHP_VERSION constant value in "lib/Config.php" (line ' . $lineNumsArr['_PHP_VERSION'] . ') is set correctly.</span></li></ul>';
						}
					?>
					</li>
					<li>PHP exec() enabled and working?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['phpExec']) ? 'Yes' : 'No'; ?><span class="response"><span> <?php echo ($tests['phpExec']) ? $success : $failed; ?></span></span><span id="fix-phpExec" style="display:none" data-bs-toggle="tooltip" data-bs-placement="right" data-trigger="manual" data-html="true" title="&nbsp;&nbsp;You must fix this before leaving.">&nbsp;&nbsp;</span>
					<?php
						if (!$tests['phpExec'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the PHP exec() function is enabled and that exec() can run FFmpeg commands.</span></li></ul>';
						}
					?>
					</li>
					<li>PHP "open_basedir": &nbsp;&nbsp;&nbsp;<?php echo (!empty($phpOpenBaseDir)) ? $phpOpenBaseDir : "no value"; ?><span class="response"><span> <?php echo ($tests['phpOpenBaseDir']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['phpOpenBaseDir'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the PHP "open_basedir" directive is empty, set to no value, or includes the app root folder in the specified directory-tree.</span></li></ul>';
						}
					?>
					</li>
					<li>cURL version: &nbsp;&nbsp;&nbsp;<?php echo ($tests['curlExists'] && isset($curlVersionNo) && !empty($curlVersionNo)) ? $curlVersionNo[0] : 'Unknown'; ?><span class="response"><span> <?php echo ($tests['curlExists']) ? $success : $failed; ?></span><?php echo (!$curlFound) ? ' &nbsp;&nbsp;<form method="post" style="display:inline" class="binInstall"><input type="hidden" name="binToInstall" value="curl" /><button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-cogs" aria-hidden="true"></i> Install cURL</button></form>' : ''; ?></span>
					<?php
						if (!$tests['curlExists'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that cURL is installed.</span></li></ul>';
						}
					?>
					</li>
					<?php if ($tests['curlExists']) { ?>
						<li>PHP cURL installed?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['phpCurl']) ? 'Yes' : 'Not found'; ?><span class="response"><span> <?php echo ($tests['phpCurl']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['phpCurl'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the PHP cURL extension is installed.</span></li></ul>';
							}
						?>
						</li>
						<?php if ($tests['phpCurl']) { ?>
							<li>DNS is OK?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['dns']) ? 'Yes' : 'No'; ?><span class="response"><span> <?php echo ($tests['dns']) ? $success : $failed; ?></span></span>
							<?php
								if (!$tests['dns'])
								{
									echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> An HTTP request made by your server to "' . $_SERVER['HTTP_HOST'] . '" failed -- indicating a faulty DNS configuration. Please try another DNS provider, changing nameservers, and/or having a professional configure your DNS. &nbsp;<a href="http://' . $_SERVER['HTTP_HOST'] . $appRoot . 'docs/faq.html#nine" onclick="window.open(this.href); return false;"><b>Read More &nbsp;&nbsp;<i class="fa fa-angle-double-right"></i></b></a></span></li></ul>';
								}
							?>
							</li>
						<?php } ?>
					<?php } ?>
					<li>FFmpeg location: &nbsp;&nbsp;&nbsp;<?php echo (isset($ffmpegPath[3])) ? trim($ffmpegPath[3]) : 'Not found'; ?><span class="response"><span> <?php echo ($tests['FFmpeg']) ? $success : $failed; ?></span><?php echo (!$ffmpegFound) ? ' &nbsp;&nbsp;<form method="post" style="display:inline" class="binInstall"><input type="hidden" name="binToInstall" value="ffmpeg" /><button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-cogs" aria-hidden="true"></i> Install FFmpeg</button></form>' : ''; ?></span>
					<?php
						if (!$tests['FFmpeg'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that FFmpeg is installed and that the _FFMPEG constant value in "lib/Config.php" (line ' . $lineNumsArr['_FFMPEG'] . ') is set correctly.';
							echo (isset($isValidFFmpegPath) && !$isValidFFmpegPath) ? '<br /><br /><span class="dark-orange">Warning!</span>: The current _FFMPEG constant value contains a directory path that is NOT accessible to PHP! You must install FFmpeg in a valid, PHP-accessible directory path. Valid installation paths include <b>"' . implode('</b>", "<b>', $validPathArrTrunc) . '</b>", and "<b>' . end($validPathArr) . '</b>".' : '';
							echo '</span></li></ul>';
						}
					?>
					</li>
					<?php if ($tests['FFmpeg']) { ?>
						<li>FFmpeg version: &nbsp;&nbsp;&nbsp;<?php echo ($tests['FFmpegVersion']) ? $ffmpegInfo[0] : 'Not found'; ?><span class="response"><span> <?php echo ($tests['FFmpegVersion']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['FFmpegVersion'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Something is wrong with your FFmpeg installation. Consider reinstalling FFmpeg.</span></li></ul>';
							}
						?>
						</li>
						<li>libmp3lame installed?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['libmp3lame']) ? 'Yes' : 'Not found'; ?><span class="response"><span> <?php echo ($tests['libmp3lame']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['libmp3lame'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the libmp3lame codec is installed and compiled with FFmpeg.</span></li></ul>';
							}
						?>
						</li>
					<?php } ?>
					<li>Site module(s) installed?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['modInstalled']) ? 'Yes' : 'Not found'; ?><span class="response"><span> <?php echo ($tests['modInstalled']) ? $success : $failed; ?></span></span>
					<?php
						if (!$tests['modInstalled'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> No site modules and corresponding PHP files found in the "lib/extractors/" directory.</span></li></ul>';
						}
					?>
					</li>
					<?php if (in_array("YouTube.php", $modules)) { ?>
						<li>Node.js location: &nbsp;&nbsp;&nbsp;<?php echo (isset($nodePath[3])) ? trim($nodePath[3]) : 'Not found'; ?><span class="response"><span> <?php echo ($tests['nodejs']) ? $success : $failed; ?></span><?php echo (!$nodeFound) ? ' &nbsp;&nbsp;<form method="post" style="display:inline" class="binInstall"><input type="hidden" name="binToInstall" value="node" /><button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-cogs" aria-hidden="true"></i> Install Node.js</button></form>' : ''; ?></span>
						<?php
							if (!$tests['nodejs'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that Node.js is installed and that the _NODEJS constant value in "lib/Config.php" (line ' . $lineNumsArr['_NODEJS'] . ') is set correctly.';
								echo (isset($isValidNodePath) && !$isValidNodePath) ? '<br /><br /><span class="dark-orange">Warning!</span>: The current _NODEJS constant value contains a directory path that is NOT accessible to PHP! You must install Node.js in a valid, PHP-accessible directory path. Valid installation paths include <b>"' . implode('</b>", "<b>', $validPathArrTrunc) . '</b>", and "<b>' . end($validPathArr) . '</b>".' : '';
								echo '</span></li></ul>';
							}
						?>
						</li>
						<?php if ($tests['nodejs']) { ?>
							<li>Node.js version: &nbsp;&nbsp;&nbsp;<?php echo ($tests['NodeVersion']) ? $nodeInfo[0] : 'Not found'; ?><span class="response"><span> <?php echo ($tests['NodeVersion']) ? $success : $failed; ?></span></span>
							<?php
								if (!$tests['NodeVersion'])
								{
									echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Something is wrong with your Node.js installation. Consider reinstalling Node.js.</span></li></ul>';
								}
							?>
							</li>
						<?php } ?>
					<?php } ?>
				</ul>
			</li>
			<li><span class="italic bold">Folder/File Permissions</span>
				<ul>
					<li>"store" directory permissions: &nbsp;&nbsp;&nbsp;<?php echo $appStorePerms; ?><span class="response"><span> <?php echo ($tests['appStorePerms']) ? $success : $failed; ?></span></span><span id="fix-appStorePerms" style="display:none" data-bs-toggle="tooltip" data-bs-placement="right" data-trigger="manual" data-html="true" title="&nbsp;&nbsp;You must fix this before leaving.">&nbsp;&nbsp;</span>
					<?php
						if (!$tests['appStorePerms'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that the "store" directory is "chmod" to 0777 permissions. If that\'s not possible or practical, then at least ensure that permissions enable writing to this folder.</span></li></ul>';
						}
					?>
					</li>					
				</ul>
			</li>
			<li><span class="italic bold">Software Settings</span>
				<ul>
					<li>Domain Name: &nbsp;&nbsp;&nbsp;<?php echo $_SERVER['HTTP_HOST']; ?><span class="response"><span> <?php echo ($tests['domains']) ? $success : $failed; ?></span></span><span id="fix-domains" style="display:none" data-bs-toggle="tooltip" data-bs-placement="right" data-trigger="manual" data-html="true" title="&nbsp;&nbsp;You must fix this before leaving.">&nbsp;&nbsp;</span>
					<?php
						if (!$tests['domains'])
						{
							echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please <a href="docs/faq.html#five" target="_blank">add your domain name</a> (and any subdomains) to the $_authorizedDomains array in "lib/Config.php" (line ' . $lineNumsArr['$_authorizedDomains'] . ').</span></li></ul>';
						}
					?>
					</li>
				</ul>
			</li>
		</ul>

		<h4><u>Recommended</u> settings. . .</h4>
		<ul>
			<?php if (isset($tests['ssl'])) { ?>
				<li><span class="italic bold">Miscellaneous</span>
					<ul>
						<li>SSL certificate?: &nbsp;&nbsp;&nbsp;<?php echo ($tests['ssl']) ? 'Yes' : 'No'; ?><span class="response"><span> <?php echo ($tests['ssl']) ? $success : $failed; ?></span></span>
						<?php
							if (!$tests['ssl'])
							{
								echo '<ul><li><span style="color:#777"><i class="fa fa-exclamation-circle orange"></i> Please ensure that a valid SSL certificate is installed.</span></li></ul>';
							}
						?>
						</li>
					</ul>
				</li>
			<?php } ?>
		</ul>

		<h5>&nbsp;</h5>
		<div class="buttons">
			<button class="btn btn-primary rerun"><i class="fas fa-sync-alt"></i> Run the tests again.</button> <button class="btn btn-success printpage"><i class="fa fa-print"></i> Print this page.</button> <button class="btn btn-danger popup"><i class="fas fa-sign-out-alt"></i> Get me out of here!</button>
		</div>
	</div>

	<!-- Exit Modal -->
	<div class="modal fade" id="exitModal" tabindex="-1" role="dialog" aria-labelledby="exitModalLabel">
	  <div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<h4 class="modal-title" id="exitModalLabel">Are you sure?</h4>
		  </div>
		  <div class="modal-body">
			<p>At the very least, <u>you should confirm that all "Required" settings are configured correctly</u>. Failure to do so will adversely affect software performance!</p>
			<p>Consider printing this page for future reference before you leave. After you leave, you will not see this page again.<span style="font-weight:bold">*</span></p>
			<div class="alert alert-danger" role="alert">
				<p style="margin:0;padding-left:14px;text-indent:-14px;"><b>*</b> <span class="italic">If you do ever want to return, then you can delete the "store/setup.log" file and navigate back to the software's index.php.</span></p>
			</div>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-light" data-bs-dismiss="modal">No, take me back.</button>
			<button id="leave" type="button" class="btn btn-primary">Yes!</button>
		  </div>
		</div>
	  </div>
	</div>
</body>
</html>