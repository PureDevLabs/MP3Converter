<?php
	use MP3Converter\lib\Config;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>MP3 Converter :: <?php echo Config::_SITENAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" integrity="sha512-iBBXm8fW90+nuLcSKlbmrPcLa0OT92xO1BIsZ+ywDWZCvqsWgccV3gFoRBv0z+8dLJgyAHIhR35VZc2oM/gI1w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="assets/css/media-icons.css" />
	<link rel="stylesheet" href="assets/css/style.css" />
	<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
	<script type="text/javascript">
	//<![CDATA[
		"use strict";	
		$(document).ready(function(){
			<?php if (isset($pageName)) { ?>
				$("ul.navbar-nav li a").each(function(){
					if ($(this).text().toLowerCase() == " <?php echo strtolower($pageName); ?>")
					{
						$(this).parent().addClass("active");
					}
				});
			<?php } ?>
		});
	//]]>
	</script>
	<?php if (isset($converter)) include 'inc/converter_js.php'; ?>
</head>
<body>
	<nav class="navbar navbar-expand-md navbar-aluminium navbar-light">
	  <div class="container">
		<a class="navbar-brand" href="<?php echo ($_SERVER['PHP_SELF'] != "/") ? preg_replace('/(\/[^\/]+)$/', "/", $_SERVER['PHP_SELF']) : $_SERVER['PHP_SELF']; ?>"><?php echo Config::_SITENAME; ?></a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#bs-example-navbar-collapse-1" aria-controls="bs-example-navbar-collapse-1" aria-expanded="false" aria-label="Toggle navigation">
		  <span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
		  <ul class="navbar-nav me-auto mb-2 mb-md-0 mt-2 mt-md-0">
			<li class="nav-item"><a class="nav-link<?php echo (isset($converter)) ? ' active' : ''; ?>" aria-current="page" href="<?php echo ($_SERVER['PHP_SELF'] != "/") ? preg_replace('/(\/[^\/]+)$/', "/", $_SERVER['PHP_SELF']) : $_SERVER['PHP_SELF']; ?>"><i class="fas fa-home"></i> Home</a></li>
			<li class="nav-item"><a class="nav-link<?php echo ($pageName == "About") ? ' active' : ''; ?>" href="about.php"><i class="fas fa-user"></i> About</a></li>
			<li class="nav-item"><a class="nav-link<?php echo ($pageName == "FAQ") ? ' active' : ''; ?>" href="faq.php"><i class="fas fa-question"></i> FAQ</a></li>
			<li class="nav-item"><a class="nav-link<?php echo ($pageName == "Contact") ? ' active' : ''; ?>" href="contact.php"><i class="far fa-envelope"></i> Contact</a></li>
		  </ul>
		</div>
	  </div>
	</nav>	