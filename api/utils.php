<?php
function get_url_parameter($parameter, $default = ""){
	$value = $default;
	if(isset($_GET[$parameter])){
		$value = $_GET[$parameter];
	}
	return $value;
}

//recursivly remove a directory with all files and subdirectories
function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (is_dir($dir."/".$object) && !is_link($dir."/".$object))
           rrmdir($dir."/".$object);
         else
           unlink($dir."/".$object); 
       } 
     }
     rmdir($dir); 
   } 
 }
 
//turn URLs within a text into clickable links
function addLinks($text) {
	$linkRegEx = '/(https?:\/\/[^\s)]+)/';
	$replaceTemplate = '<a href="$1" target="_blank">$1</a>';
	return preg_replace($linkRegEx, $replaceTemplate, $text);
}

//recursively removes empty or non set fields in a json object
function json_clean($json){
	foreach($json as $key => $value){
		if(gettype($value) == "array" || gettype($value) == "object"){
			$value = json_clean($value);
		}
		if(gettype($value) == "NULL") {
			unset($json[$key]); 
		}else if((gettype($value) == "array" && sizeof($value)==0)) {
			unset($json[$key]); 
		}else if(gettype($value) == "string" && strlen(trim($value))==0) {
			unset($json[$key]); 
		}else{
			$json[$key]=$value;
		}
	}
	return $json;
}

function clean_doi_link($link){
	if(substr( $link, 0, 14 ) === "http://doi.org" || substr( $link, 0, 4 ) === "doi:" ){
		return preg_replace("/^(http:\/\/doi.org\/|doi:)/","https://doi.org/",$link);
	}
	return $link;
}

function generate_doi_link($link){
	$link = clean_doi_link($link);
	if(substr( $link, 0, 7 ) === "http://" || substr( $link, 0, 8 ) === "https://" ){
		$class = "";
		if(substr( $link, 0, 16 ) === "https://doi.org/" ){
			$class = "class=\"doi-link\"";
		}
		return "<a href=\"".$link."\"".$class.">".$link."</a>";
	}else{
		return $link;
	}
}

function get_creative_commons_icon($license){
	if (preg_match('#^https?://creativecommons.org/licenses/by-nc-nd/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]BY[ -_]NC[ -_]ND#i', $license) === 1 ) {
		return "by-nc-nd.png";
	}else if (preg_match('#^https?://creativecommons.org/licenses/by-nc-sa/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]BY[ -_]NC[ -_]SA#i', $license) === 1 ) {
		return "by-nc-sa.png";
	}else if (preg_match('#^https?://creativecommons.org/licenses/by-nc/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]BY[ -_]NC#i', $license) === 1 ) {
		return "by-nc.png";
	}else if (preg_match('#^https?://creativecommons.org/licenses/by-nd/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]BY[ -_]ND#i', $license) === 1 ) {
		return "by-nd.png";
	}else if (preg_match('#^https?://creativecommons.org/licenses/by-sa/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]BY[ -_]SA#i', $license) === 1 ) {
		return "by-sa.png";
	}else if (preg_match('#^https?://creativecommons.org/licenses/by/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]BY#i', $license) === 1 ) {
		return "by.png";
	}else if (preg_match('#^https?://creativecommons.org/publicdomain/zero/#i', $license) === 1 || preg_match('#(^|\()CC[ -_]?0#i', $license) === 1 || preg_match('#(^|\()CC[ -_]ZERO#i', $license) === 1 ) {
		return "cc-zero.png";
	}else if (preg_match('#^https?://creativecommons.org/publicdomain/mark/#i', $license) === 1 || preg_match('#(^|\()PUBLIC DOMAIN#i', $license) === 1 || preg_match('#(^|\()PD#i', $license) === 1 ) {
		return "publicdomain.png";
	}else {
		return "";
	}
}

function log_request($request_url, $remote_addr){
	$log_file = dirname(__FILE__, 4)."/log/dts/transform_requests.log";
	// $log_file = dirname(__FILE__, 4)."/transform_requests.log"; # for local testing
	$timestamp = date("Y-m-d H:i:s");
	error_log("[$timestamp] $remote_addr requested: $request_url\n", 3, $log_file);
}