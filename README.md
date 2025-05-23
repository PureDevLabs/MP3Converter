## MP3 Converter

### Requirements:

This software requires the following server configuration:

• Linux Server (All Linux distributions supported)\
• For commercial servers: Shared†, Dedicated, and VPS hosting supported\
• Apache\
• PHP 5.6+\
• cURL and PHP cURL extension enabled\
• FFmpeg and libmp3lame packages installed\
• Node.js\
• That’s it!

† Note: If you’re using a shared hosting plan, then please ensure that FFmpeg and cURL are supported and installed.

### Changelog:

<h5 id="item-description__2016-02-26">2024-08-06</h5>
• Yet another update to YouTube "nsig" decryption

###### Updated files
```
README.md
lib/extractors/YouTube.php
```
<hr>

<h5 id="item-description__2016-02-26">2024-08-03</h5>
• Another update to YouTube "nsig" decryption<br>
• Updated default user agent and IP version used in YouTube requests

###### Updated files
```
README.md
lib/extractors/YouTube.php
```
<hr>

<h5 id="item-description__2016-02-26">2024-07-26</h5>
• Another update to YouTube "nsig" decryption

###### Updated files
```
README.md
lib/extractors/YouTube.php
```
<hr>

<h5 id="item-description__2016-02-26">2024-07-17</h5>
• Rebranded<br />
• Removed Licensing<br />
• Added <a href="https://shop.rajwebconsulting.com/knowledgebase/54/Using-YouTube-login-cookies-to-access-restricted-content.html">YouTube cookies support</a><br />
• Fixed YouTube "nsig" decryption

###### Updated files
```
LICENSE (new)
docs/config/Ubuntu-12.04-SETUP.htm
docs/faq.html
docs/index.html
inc/check_config.php
index.php
lib/extractors/YouTube.php
store/ytcookies.txt (new)
```
<hr>

<h5 id="item-description__2016-02-26">2024-05-01</h5>
• Added support for forcing IPv6- or IPv4-only HTTP requests to supported websites
<h5 id="item-description__2016-02-26">2024-03-26</h5>
• Added support for passing site-specific HTTP headers to media download requests<br />
<h5 id="item-description__2016-02-26">2022-08-09</h5>
• Added easy, 1-click installation of Node.js via the software's initial "Config Check" utility<br />
• Modified "Config Check" utility so that Node.js test runs (and corresponding auto-install is enabled) only if YouTube module is present<br />
• Added _NODEJS constant to Config file, enabling Node.js installation in any valid, PHP-accessible directory<br />
• Provided the option to force IPv4 for all HTTP requests issued by a supported video/audio site module<br />
<h5 id="item-description__2016-02-26">2021-12-14</h5>
• Added/Updated/Removed supported video/audio hosting sites<br />
• Updated 3rd-party libraries and frameworks<br />
• Added easy, 1-click installation of FFmpeg and cURL via the software's initial "Config Check" utility<br />
• Moved misc. software resources and dependencies to a separate 'store' folder<br />
• Improved M3U8 playlist download support<br />
<h5 id="item-description__2016-02-26">2020-11-26</h5>
• Fixed an issue that sometimes prevented recognition of the requested video/audio hosting site prior to MP3 conversion, for PHP 7.3+<br />
<h5 id="item-description__2016-02-26">2020-11-02</h5>
• Enabled optional, custom User Agent string for each supported video/audio hosting site and corresponding module<br />
<h5 id="item-description__2016-02-26">2020-03-21</h5>
• Added Cloudflare SSL support to the internal HTTP request that ultimately executes MP3 conversion<br />
• Updated the temporary media file container format from FLV to MKV<br />
<h5 id="item-description__2016-02-26">2020-01-02</h5>
• Generally improved M3U8 playlist download support as well as enabled realtime M3U8 download progress, when possible<br />
<h5 id="item-description__2016-02-26">2019-09-28</h5>
• Fixed a bug that could potentially permit unauthorized downloads<br />
<h5 id="item-description__2016-02-26">2019-06-15</h5>
• Updated and improved video download URL extraction<br />
<h5 id="item-description__2016-02-26">2016-02-26</h5>
• Added support for the download (and conversion) of .m3u8 playlist files available from video/audio hosting sites.<br />
• Updated the FAQ (&#8220;docs/faq.html&#8221;).<br />
• Added Changelog file (&#8220;docs/changelog.txt&#8221;).<br />
<h5 id="item-description__2016-01-03">2016-01-03</h5>
• Added one meta tag to &#8220;inc/page_header.php&#8221; to improve display on Android phones/devices.<br />
• Updated command line instructions for installation on a CentOS 6.x system (&#8220;docs/config/CentOS-6.x-SETUP.htm&#8221;).<br />
