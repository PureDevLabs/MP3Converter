	<script type="text/javascript">
	//<![CDATA[
		"use strict";
		var mpc_conversionLogLength = 0;

		function mpc_updateVideoDownloadProgress(percentage, isRealTime)
		{
			percentage = parseInt(percentage);
			if (isRealTime)
			{
				$("#progress").css("width", percentage + "%").html(percentage + "%");
			}
			else
			{
				$("#progress").addClass("progress-striped").css("width", percentage + "%").html("&nbsp;");
			}
		}

		function mpc_updateConversionProgress(songFile)
		{
			var progress = document.getElementById('progress');
			document.getElementById('conversion-status').innerHTML = "Converting video. . .";
			$.ajax({
				type : "POST",
				url : "ffmpeg_progress.php",
				data : "uniqueId=<?php echo $converter->GetUniqueID(); ?>&logLength=" + mpc_conversionLogLength + "&mp3File=" + encodeURI(songFile),
				success : function(retVal, status, xhr) {
					var retVals = retVal.split('|');
					if (retVals[3] == 2)
					{
						progress.style.width = progress.innerHTML = parseInt(retVals[1]) + '%';
						if (parseInt(retVals[1]) < 100)
						{
							mpc_conversionLogLength = parseInt(retVals[0]);
							setTimeout(function(){mpc_updateConversionProgress(songFile);}, 10);
						}
						else
						{
							mpc_showConversionResult(songFile, retVals[2]);
						}
					}
					else
					{
						setTimeout(function(){mpc_updateConversionProgress(songFile);}, 1);
					}
				},
				error : function(xhr, status, ex) {
					setTimeout(function(){mpc_updateConversionProgress(songFile);}, 1);
				}
			});
		}

		function mpc_showConversionResult(songFile, success)
		{
			$("#preview").css("display", "none");
			var convertSuccessMsg = (success == 1) ? '<p class="alert alert-success">Success!</p><p><a class="btn btn-success" href="<?php echo $_SERVER['PHP_SELF']; ?>?mp3=' + encodeURI(songFile) + '"><i class="fa fa-download"></i> Download your MP3 file</a><br /> <br /><a class="btn btn-warning" href="<?php echo $_SERVER['PHP_SELF']; ?>"><i class="fa fa-reply"></i> Back to Homepage</a></p>' : '<p class="alert alert-danger">Error generating MP3 file!</p>';
			$("#conversionSuccess").html(convertSuccessMsg);
		}

		$(document).ready(function(){
			if (!document.getElementById('preview'))
			{
				$("#conversionForm").css("display", "block");
			}

			$(function(){
			  $('[data-toggle="tooltip"]').tooltip();
			});
		});
	//]]>
	</script>