<?php
// Crawler & Search utility for Sec.Gov pools
// Version 1.0
// Copyright 2010 - RazorWire Solutions, Inc.
// All Rights Reserved

//DEBUGGING           
// $matches[0] now contains the complete A tags; ex: <a href="link">text</a>
// $matches[1] now contains only the HREFs in the A tags; ex: link
// uncomment the two lines below to turn on debugging
//
// error_reporting(E_ALL);
// ini_set('display_errors', true);

/* Usage: /sitemap.php?domain=exmaple.com
   where exmaple.com = domain you want map */

$site = rawurlencode($_GET['domain']);

$link_to_dig = "http://" . $site;
$sitemap = array();
function dig($current_link) {
	$cookiejar = "/tmp/cookiejar.txt";
	global $site;
	global $sitemap;
	global $link_to_dig;
	// Return a handle to a <strong class="highlight">curl</strong> connection to the site you want to pull info from
	$ch = curl_init($current_link);
	// Set some options for the connection
	curl_setopt($ch,CURLOPT_HEADER, TRUE); // Don't return header information, although, this can be handy ;)
	curl_setopt($ch,CURLOPT_RETURNTRANSFER, TRUE); // Give us the page source
	curl_setopt($ch,CURLOPT_FOLLOWLOCATION, 0);
	curl_setopt($ch,CURLOPT_MAXREDIRS,100);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.8.1.11) Gecko/20071127 Firefox/2.0.0.11");	
	// Open the connection with the options specified
	$original_file = curl_exec($ch);
	if(!$original_file) {
		error_log("<br />" . curl_error($ch) . "<br /><br />");
	}
	$info = curl_getinfo($ch);
	curl_close($ch);

	if($info['http_code'] == 301 || $info['http_code'] == 302) { // redirect manually, cookies must be set, which curl does not itself
		// extract new location
		preg_match_all('|Location: (.*)\n|U', $original_file, $results);
		foreach($results[1] as &$value) {
		  $value=trim($value);
		}
		$location = implode(';', $results[1]);
		// redirect manually
		if(!stristr($location, $link_to_dig)) {
			$location = $link_to_dig . $location;
		}
		dig($location);

	}	
	$path_info = parse_url($current_link);
	$base = $path_info['scheme'] . "://" . $path_info['host'];
	$stripped_file = strip_tags($original_file, "<a>");
	$fixed_file = preg_replace("/<a([^>]*)href=\"\//is", "<a$1href=\"{$base}/", $stripped_file);
	if (strstr($current_link,"?")) $current_link=strstr($current_link,"?",true);
	$fixed_file = preg_replace("/<a([^>]*)href=\"\?/is", "<a$1href=\"{$current_link}?", $fixed_file);
	preg_match_all("/<a(?:[^>]*)href=\"([^\"]*)\"(?:[^>]*)>(?:[^<]*)<\/a>/is", $fixed_file, $matches);
	
	foreach($matches[1] as $k => $v){
		$current_parts = parse_url($current_link);
		$url_parts = parse_url($v);
		if($url_parts['query']) {
			$url_parts['query'] = "?" . $url_parts['query'];
		}
		if(!$url_parts['host']){
			$url_parts['host'] = $site;
		}
		if($url_parts['host'] != $site){
			unset($matches[1][$k]);
			continue;
		}
		if(stristr($v, "javascript:") || stristr($v, "mailto:") || stristr($v, "account")){
			unset($matches[1][$k]);
			continue;
		}
		if(preg_match("|^#|",$v)) {
			unset($matches[1][$k]);
			continue;		  
		}
		if(($v=="http://".$site."/" || $v=="http://".$site) && in_array("http://".$site,$sitemap)) {
			unset($matches[1][$k]);
			continue;
		}
		if(in_array($v, $sitemap)) {
			unset($matches[1][$k]);
			continue;
		}
		if($v == "" || $v == "#" || strstr($v,"#")){
			unset($matches[1][$k]);
			continue;
		}
		if(preg_match("/\.(gif|jpg|png|css|js|php)$/", $v)){
			unset($matches[1][$k]);
			continue;
		}
		array_push($sitemap, $v);
		dig($v);
	}
}
dig($link_to_dig);

$mapStart = <<< EOF
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\r\n
EOF;
$mapVar = $mapStart;

$i = 1;
foreach($sitemap as $url){
	$mapVar .= "   <url>\r\n";
	$mapVar .= "      <loc>" . htmlentities($url) . "</loc>\r\n";
	$mapVar .= "      <lastmod>" . date("Y-m-d") . "</lastmod>\r\n";
	$mapVar .= "      <changefreq>weekly</changefreq>\r\n";
	$mapVar .= "      <priority>0.8</priority>\r\n";
	$mapVar .= "   </url>\r\n";
	$i++;
}
$mapVar .= "</urlset>\r\n";

header("Content-Disposition: attachment; filename=". $site . ".xml.gz");
header("Content-type: application/x-gzip");
echo gzencode($mapVar, 9);


?>