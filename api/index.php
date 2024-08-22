<?php 


include("utils.php");
include("pdi.php");

$padding_width = 4;
$service = get_url_parameter('service');

//TODO check if exists
//echo "service: '".$service."'";

if($service == "transformations"){
	$transformation = get_url_parameter('transformation');
	$version = get_url_parameter('version');
	if($transformation != ""){
		$filename = get_url_parameter('filename');
		$transformation_id_padded = str_pad($transformation, $padding_width, '0', STR_PAD_LEFT);
		
		if($version != ""){
			$version_padded = str_pad($version, $padding_width, '0', STR_PAD_LEFT);
			$file = "transformations/".$transformation_id_padded."/".$version_padded."/index.json";

			if (file_exists($file)) {					
				$string = file_get_contents($file);
				$json = json_decode($string, true);
				$json["version"] = array_merge(array("transformation_id"=>ltrim($transformation, '0'),"version_id"=>ltrim($version, '0')),$json["version"]);
				
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode($json);
			}else{
				//ToDo Error handling, version does not exist
			}
					
		}else if($filename != ""){
			//ToDo get file
		}else{
			$listing = array();
			foreach (new DirectoryIterator($service."/".$transformation_id_padded) as $fileInfo) {
				if($fileInfo->isDir() && !$fileInfo->isDot() && $fileInfo->getFilename() != "." && $fileInfo->getFilename() != ".." ) {
					$file = $service."/".$transformation_id_padded."/".$fileInfo->getFilename()."/index.json";

					if (file_exists($file)) {
						
						$string = file_get_contents($file);
						$json = json_decode($string, true);
						//ToDo deprecated tracking
						//ToDo handle deprecated latest version separately
						if(array_key_exists('published',$json) && ($json['published'] == "false" || $json['published'] == "hidden" )){
							continue;
						}
						
						$listing[] = $fileInfo->getFilename();
					}
				}
			}
			sort($listing);
			$latest_version_id_padded = $listing[sizeof($listing)-1];
			
			header('Content-Type: application/json; charset=utf-8');
			$output = array();
			
			$file = "transformations/".$transformation_id_padded."/".$latest_version_id_padded."/index.json";
				
			$string = file_get_contents($file);
			$latest_version_content = json_decode($string, true);
			$output["transformation"] = $latest_version_content["version"];	
			$output["transformation"] = array_merge(array("transformation_id"=>ltrim($transformation, '0')),$output["transformation"]);
			header('Content-Type: application/json; charset=utf-8');
			foreach ($listing as $version_id_padded) {
				$file = "transformations/".$transformation_id_padded."/".$version_id_padded."/index.json";
				$string = file_get_contents($file);
				$version_content = json_decode($string, true);
				$version_content["version"] = array_merge(array("transformation_id"=>ltrim($transformation, '0'),"version_id"=>ltrim($version_id_padded, '0')),$version_content["version"]);
				$output["transformation"]["versions"][] = $version_content["version"];
			}
			echo json_encode($output);
		}
	}else{
		//ToDo: list all available transformations
		//header('Content-Type: application/json; charset=utf-8');
		//echo "{\"transformations\": []}";
		
		//ToDo combine versions
			//filter for unpublished flag
			
		$output = array();
		$transformation_listing = array();
		foreach (new DirectoryIterator($service) as $fileInfo) {
			if($fileInfo->isDir() && !$fileInfo->isDot() && $fileInfo->getFilename() != "." && $fileInfo->getFilename() != ".." ) {
				$transformation_listing[] = $fileInfo->getFilename();
			}
		}
		sort($transformation_listing);
		
		foreach ($transformation_listing as $transformation_id_padded) {
			$file = $service."/".$fileInfo->getFilename();
				
			$version_listing = array();
			foreach (new DirectoryIterator($service."/".$transformation_id_padded) as $fileInfo) {
				if($fileInfo->isDir() && !$fileInfo->isDot() && $fileInfo->getFilename() != "." && $fileInfo->getFilename() != ".." ) {
					$file = $service."/".$transformation_id_padded."/".$fileInfo->getFilename()."/index.json";

					if (file_exists($file)) {
						
						$string = file_get_contents($file);
						$json = json_decode($string, true);
						//ToDo deprecated tracking
						if(array_key_exists('published',$json) && ($json['published'] == "false" || $json['published'] == "hidden" )){
							continue;
						}
						
						$version_listing[] = $fileInfo->getFilename();
					}
				}
			}
			sort($version_listing);
			$latest_version_id_padded = $version_listing[sizeof($version_listing)-1];

			//ToDo if latest version is deprecated, deprecate transformation

			$file = "transformations/".$transformation_id_padded."/".$latest_version_id_padded."/index.json";
		
			$string = file_get_contents($file);
			$latest_version_content = json_decode($string, true);
			$latest_version_content["version"] = array_merge(array("transformation_id"=>ltrim($transformation_id_padded, '0'),"version_id"=>ltrim($latest_version_id_padded, '0')),$latest_version_content["version"]);
			$output["transformations"][] = $latest_version_content["version"];
		}
		
		
		header('Content-Type: application/json; charset=utf-8');
		

		echo json_encode($output);
		//
	}
}else if($service == "transform"){
	//get GET parameters
	$transformation = get_url_parameter('transformation');
	$version = get_url_parameter('version');
	$input_file_url = get_url_parameter('input_file_url');
	$input_file_zipped = get_url_parameter('input_file_zipped',"false");
	//ToDo load additional custom parameters
	//print_r($_GET);
	//load transformation
	
	if($transformation != ""){
		$transformation_id_padded = str_pad($transformation, $padding_width, '0', STR_PAD_LEFT);
		
		if($version != ""){
			$version_padded = str_pad($version, $padding_width, '0', STR_PAD_LEFT);
			$file = "transformations/".$transformation_id_padded."/".$version_padded."/index.json";

			if (file_exists($file)) {					
				$transformation_file = $file;
			}else{
				//ToDo Error handling, version does not exist
				return;
			}
					
		}else{
			$listing = array();
			foreach (new DirectoryIterator("transformations/".$transformation_id_padded) as $fileInfo) {
				if($fileInfo->isDir() && !$fileInfo->isDot() && $fileInfo->getFilename() != "." && $fileInfo->getFilename() != ".." ) {
					$file = "transformations/".$transformation_id_padded."/".$fileInfo->getFilename()."/index.json";

					if (file_exists($file)) {
						$listing[] = $fileInfo->getFilename();
					}
				}
			}
			sort($listing);
			$latest_version_id_padded = $listing[sizeof($listing)-1];
			$version = $latest_version_id_padded;
			
			$transformation_file = "transformations/".$transformation_id_padded."/".$latest_version_id_padded."/index.json";
			
		}
	}else{
		//to transformation specified
		//ToDo warning
		return;
	}
	
	if($transformation_file != ""){
		$string = file_get_contents($file);
		$transformation_json = json_decode($string, true);
		$transformation_json["version"] = array_merge(array("transformation_id"=>ltrim($transformation, '0'),"version_id"=>ltrim($version, '0')),$transformation_json["version"]);
		
		do{
		$job_json = array();
		//
		$job_id = rand(0,9999999999);
		$job_id_token_width = 10;
		$job_id = str_pad($job_id, $job_id_token_width, '0', STR_PAD_LEFT);
			
		}while(file_exists("results/".$job_id));
			
		mkdir("results/".$job_id);
		mkdir("results/".$job_id."/input");
		mkdir("results/".$job_id."/output");
		mkdir("results/".$job_id."/tmp");
		
		$job_json["job"]["job_id"] = $job_id;
		$job_json["job"]["transformation_id"] = ltrim($transformation, '0');
		$job_json["job"]["version_id"] = ltrim($version, '0');
		$job_json["job"]["input_file_url"] = $input_file_url;
		$job_json["job"]["input_file_zipped"] = $input_file_zipped;
		$job_json["job"]["query"] = (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) ? 'https' : 'http')."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		//ToDo error handling urls with parameters or slash at the end
		$input_file_name = substr($input_file_url, strrpos($input_file_url,"/")+1);
		$url = substr($input_file_url, 0, strrpos($input_file_url,"/")+1);
		$job_json["job"]["input_file"] = "input/".$input_file_name;
		//error_log($url);
		//error_log($input_file_name, 0);
		//error_log($input_file_url, 0);
		error_log($url . rawurlencode($input_file_name));
		//error_log(rawurlencode($input_file_url));
		
		// Download input file
		$input_file_content = file_get_contents($url . rawurlencode($input_file_name));
		if ($input_file_content)
			file_put_contents("results/".$job_id."/".$job_json["job"]["input_file"],$input_file_content);
		
		foreach ($_GET as $parameter => $value) {
			if(substr($parameter,0,1)=="_"){
				$job_json["job"]["parameters"][substr($parameter,1)] = $value;
			}
		}
		$job_json["job"]["status"] = "processing";
		$job_json["job"]["start_time"] = date("c");
		
		
		if($transformation_json["version"]["engine"]=="xslt")
			$job_json = xslt_transformation($job_json,$transformation_json);
		else if($transformation_json["version"]["engine"]=="python")
			$job_json = python_transformation($job_json,$transformation_json);
		else if($transformation_json["version"]["engine"]=="pdi")
			if($transformation_json["version"]["input_format"]=="ABCD 2.06")
				$job_json = dwca_transformation($job_json,$transformation_json);
			else
				$job_json = cdmlight_transformation($job_json,$transformation_json);
		else{
			//ToDo error: unsupported engine
			//remove directory?
			//
		}
		
		// Remove tmp folder
		rrmdir("results/".$job_id."/tmp");
		
		$job_json["job"]["finish_time"] = date("c",strtotime ("+2 seconds"));
		$job_json["job"]["combined_download"] = $job_id.".zip";
		$result_caching_in_hours = "24";
		$job_json["job"]["job_expiration_date"] = date("c",strtotime ("+".$result_caching_in_hours." hours"));
		/*
{"job": {
    "job_id": "9297105672",
    "transformation_id": "1",
    "version_id": "2",
    "input_file_url":"https://data.example.org/my-dataset/observations.zip",
    "input_file_zipped":"false",
	"input_file":"input/observations.zip"
    "parameters": [{"result_file_name": "my_data_results.xml"}],
    "status": "complete",
    "start_time": "2019-07-15T13:37:24.782",
    "finish_time": "2019-07-15T13:37:26.275",
    "result_file": "my_data_results.xml",
    "combined_download": "9297105672.zip",
    "job_expiration_date": "2019-08-15T13:37:26.275"
}}
		
		*/
		file_put_contents("results/".$job_id."/job.json",json_encode($job_json));
		header("Location: results/".$job_id."/");
	}else{
		//ToDo error handling
		return;
	}
	
	//check for engine, pass to engine
	return;
	
}else if($service == "results"){
	$job_id = get_url_parameter('job');
	$task = get_url_parameter('task');
	//ToDo wait 2 seconds
	if($task == "delete"){
		//ToDo delete task
		rrmdir("results/".$job_id."/");
		echo "deleted ".$job_id;
		return;
	}
	if(file_exists("results/".$job_id."/job.json")){
		$string = file_get_contents("results/".$job_id."/job.json");
		header('Content-Type: application/json; charset=utf-8');
		echo $string;
	}else{
		//ToDo 404: unknown job
		http_response_code (404);
		echo "unknonwn job";
	}
}else{
	http_response_code (404);
	echo "unknonwn service";
}

function python_transformation($job_json,$transformation_json){
	$padding_width = 4;
	$job_json["job"]["status"] = "complete";
	$job_json["job"]["result_file"] = "output/result.xml";
	
	$transformation_id_padded = str_pad($transformation_json["version"]["transformation_id"], $padding_width, '0', STR_PAD_LEFT);
	$version_id_padded = str_pad($transformation_json["version"]["version_id"], $padding_width, '0', STR_PAD_LEFT);
	$python_file = "transformations/".$transformation_id_padded."/".$version_id_padded."/".$transformation_json["version"]["files"][0];
	$input_file = "results/".$job_json["job"]["job_id"]."/".$job_json["job"]["input_file"];
	$result_file = "results/".$job_json["job"]["job_id"]."/".$job_json["job"]["result_file"];

	$command = "python ".$python_file." ".$input_file." ".$result_file;
	shell_exec($command);

	return $job_json;
}

function xslt_transformation($job_json,$transformation_json){
	$padding_width = 4;
	$job_json["job"]["status"] = "complete";
	$job_json["job"]["result_file"] = "output/result.xml";
	
	$transformation_id_padded = str_pad($transformation_json["version"]["transformation_id"], $padding_width, '0', STR_PAD_LEFT);
	$version_id_padded = str_pad($transformation_json["version"]["version_id"], $padding_width, '0', STR_PAD_LEFT);
	$xsl_file = "transformations/".$transformation_id_padded."/".$version_id_padded."/".$transformation_json["version"]["files"][0];
	$input_file = "results/".$job_json["job"]["job_id"]."/".$job_json["job"]["input_file"];
	$result_file = "results/".$job_json["job"]["job_id"]."/".$job_json["job"]["result_file"];
	
	//ToDo tmp hack, remove
	//$content = file_get_contents($xsl_file);
	//file_put_contents("results/".$job_json["job"]["job_id"]."/".$job_json["job"]["result_file"],$content);
	//return $job_json;
	
	$result = "";
	if($transformation_json["version"]["xslt_processor"]=="saxon"){
		//Use the Saxon CE processor, to get XSLT2 support. 
		//This needs to be installed separatly, see //www.saxonica.com/saxon-c/index.xml for details
		$proc = new Saxon\SaxonProcessor();
		
		$xsltProc = $proc->newXsltProcessor();
		$xsltProc->setSourceFromFile($input_file);
		$xsltProc->compileFromFile($xsl_file);  
		$result = $xsltProc->transformToString();	
		//$job_json["job"]["error_count"] = $xsltProc->getExceptionCount();
		//$job_json["job"]["error_message"] = $xsltProc->getErrorMessage(0);
	}else{
		$xmldoc = DOMDocument::loadXML(file_get_contents($input_file));
		$xsldoc = DOMDocument::loadXML(file_get_contents($xsl_file));

		$proc = new XSLTProcessor();
		$proc->registerPHPFunctions();
		$proc->importStyleSheet($xsldoc);
		$result = $proc->transformToXML($xmldoc);
	}
	
	file_put_contents($result_file,$result);
	return $job_json;
}