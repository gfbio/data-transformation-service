<?php 

#include("utils.php");
$log = array();
	
function dwca_transformation($job_json, $transformation_json){
	global $log;
	$padding_width = 4;

	// Get job & trafo parameters and fill in some paths
	$job_id = $job_json["job"]["job_id"];
	$transformation_id_padded = str_pad($transformation_json["version"]["transformation_id"], $padding_width, '0', STR_PAD_LEFT);
	$version_id_padded = str_pad($transformation_json["version"]["version_id"], $padding_width, '0', STR_PAD_LEFT);
	$kjbFile = "transformations/".$transformation_id_padded."/".$version_id_padded."/".$transformation_json["version"]["files"][0];
	$ktrFile = $transformation_json["version"]["files"][1];
	$libs = "transformations/".$transformation_id_padded."/".$version_id_padded."/";
	$input_file_zipped = $job_json["job"]["input_file_zipped"];
	$input_file = "results/" . $job_id . "/" . $job_json["job"]["input_file"];
	$result_file = "results/" . $job_id . "/output/dwca.zip";
	$tmp_folder = "results/" . $job_id . "/tmp/";

	try {
		// Unzip archive or copy+rename xml file
		if ($input_file_zipped == 'true') {
			add_log("Extracting XML documents from input file");
			add_log($input_file);
			$zip = new ZipArchive;
			if ($zip->open($input_file) === TRUE) {
				$zip->extractTo($tmp_folder);
				$zip->close();
			}
			else
				throw new Exception("ZIP file could not be opened; please check URL.");
		}
		else  {
			add_log("Copying XML document");
			if (copy($input_file, $tmp_folder."response.00001.xml"))
				add_log("Source file is unzipped XML.");
			else
				throw new Exception("Single XML file could not be opened; please check URL.");
		}

		// Invoke PDI
		$cmd = 	"java 2>&1" .								// redirect stderr to stdout
			" -Djava.ext.dirs=" . $libs .					// kettle libraries
			" -Djava.io.tmpdir=" . $tmp_folder .	 		// temp folder for Kettle
			" -DKETTLE_HOME=results/ " .		 			// archiveWorkLocator
			" -Xmx2048m " .									// max heap mem
			" org.pentaho.di.kitchen.Kitchen " . 			// Kettle main class
			" -level=Minimal " .							// Log level
			" -file=" . $kjbFile .							// job file
			" -param:sort_size=500000 " .					// sort size
			" -param:base_dir=" . $tmp_folder .				// source and dest folder
			" -param:transformer=" . $ktrFile;				// trafo file
		#echo $cmd;
		add_log("Starting PDI transformation...");
		exec($cmd, $log, $ret);
		if ($ret)
			throw new Exception('PDI call failed with error code ' . $ret); 

		// Zip files
		$zip = new ZipArchive();
		add_log("Zipping up files...");
		if ($zip->open($result_file, ZipArchive::CREATE)===TRUE) {
			$meta_xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<archive xmlns="http://rs.tdwg.org/dwc/text/">';

			$files = array('occurrence', 'identification', 'image', 'measurementorfact');
			foreach ($files as $file) {
				$file_name = $file . ".txt";
				if(file_exists($tmp_folder . $file_name)) {
					$zip->addFile($tmp_folder . $file_name, $file_name);
					$meta_xml .= meta_snippet($file, $version_id_padded);
				}
			}
			$zip->addFile($tmp_folder . "/eml.xml", "eml.xml");
			$zip->addFromString("meta.xml", $meta_xml . PHP_EOL . '</archive>');

			$zip->close();
		}
		else 
			throw new Exception("Couldn't create zip file ");

		// Final log statement
		add_log("Done.");

		// Set result JSON
		$job_json["job"]["status"] = "complete";
		$job_json["job"]["result_file"] = $result_file;
	}

	catch (Exception $e) {
		$job_json["job"]["status"] = "failed";
		add_log($e->getMessage(), 'ERROR');
		$job_json["job"]["error_message"] = $e->getMessage(); 
	}

	finally {
		// Add log to JSON and return it
		$job_json["job"]["log"] = $log;
		return $job_json;
	}
}

function add_log($msg, $tag='INFO') {
	global $log;
	array_push($log, $tag . date('  d-m H:i:s,') . substr(microtime(), 2, 3) . " - " . $msg);
}

function meta_snippet($file, $version_id_padded) {
	if ($file=="occurrence")
		// For the core file, EML is different for versions 1 and 2 (occorrenceDetails removed and occurrenceStatus added)
		if ($version_id_padded=="0001")
    		return '
	<core encoding="UTF-8" fieldsTerminatedBy="," linesTerminatedBy="\\n" fieldsEnclosedBy=\'"\' ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Occurrence">
		<files>
			<location>occurrence.txt</location>
		</files>
		<id index="0" />
		<!-- Occurrence fields -->
		<field index="0" term="http://rs.tdwg.org/dwc/terms/catalogNumber"/>
		<field index="1" term="http://rs.tdwg.org/dwc/terms/institutionCode"/>
		<field index="2" term="http://rs.tdwg.org/dwc/terms/collectionCode"/>
		<field index="3" term="http://rs.tdwg.org/dwc/terms/basisOfRecord"/>
		<field index="4" term="http://rs.tdwg.org/dwc/terms/occurrenceID"/>
		<field index="5" term="http://rs.tdwg.org/dwc/terms/recordNumber"/>
		<field index="6" term="http://purl.org/dc/terms/modified"/>
		<field index="7" term="http://rs.tdwg.org/dwc/terms/recordedBy"/>
		<field index="8" term="http://rs.tdwg.org/dwc/terms/fieldNumber"/>
		<field index="9" term="http://rs.tdwg.org/dwc/terms/samplingProtocol"/>
		<field index="10" term="http://rs.tdwg.org/dwc/terms/habitat"/>
		<field index="11" term="http://rs.tdwg.org/dwc/terms/eventRemarks"/>
		<field index="12" term="http://rs.tdwg.org/dwc/terms/verbatimElevation"/>
		<field index="13" term="http://rs.tdwg.org/dwc/terms/minimumElevationInMeters"/>
		<field index="14" term="http://rs.tdwg.org/dwc/terms/maximumElevationInMeters"/>
		<field index="15" term="http://rs.tdwg.org/dwc/terms/verbatimDepth"/>
		<field index="16" term="http://rs.tdwg.org/dwc/terms/minimumDepthInMeters"/>
		<field index="17" term="http://rs.tdwg.org/dwc/terms/maximumDepthInMeters"/>
		<field index="18" term="http://rs.tdwg.org/dwc/terms/minimumDistanceAboveSurfaceInMeters"/>
		<field index="19" term="http://rs.tdwg.org/dwc/terms/maximumDistanceAboveSurfaceInMeters"/>
		<field index="20" term="http://rs.tdwg.org/dwc/terms/country"/>
		<field index="21" term="http://rs.tdwg.org/dwc/terms/countryCode"/>
		<field index="22" term="http://rs.tdwg.org/dwc/terms/locality"/>
		<field index="23" term="http://rs.tdwg.org/dwc/terms/locationRemarks"/>
		<field index="24" term="http://rs.tdwg.org/dwc/terms/eventDate"/>
		<field index="25" term="http://rs.tdwg.org/dwc/terms/verbatimEventDate"/>
		<field index="26" term="http://rs.tdwg.org/dwc/terms/eventTime"/>
		<field index="27" term="http://rs.tdwg.org/dwc/terms/startDayOfYear"/>
		<field index="28" term="http://rs.tdwg.org/dwc/terms/endDayOfYear"/>
		<field index="29" term="http://rs.tdwg.org/dwc/terms/occurrenceDetails"/>
		<field index="30" term="http://rs.tdwg.org/dwc/terms/occurrenceRemarks"/>
		<field index="31" term="http://rs.tdwg.org/dwc/terms/sex"/>
		<field index="32" term="http://rs.tdwg.org/dwc/terms/verbatimCoordinates"/>
		<field index="33" term="http://rs.tdwg.org/dwc/terms/decimalLatitude"/>
		<field index="34" term="http://rs.tdwg.org/dwc/terms/decimalLongitude"/>
		<field index="35" term="http://rs.tdwg.org/dwc/terms/coordinateUncertaintyInMeters"/>
		<field index="36" term="http://rs.tdwg.org/dwc/terms/coordinatePrecision"/>
		<field index="37" term="http://rs.tdwg.org/dwc/terms/georeferenceProtocol"/>
		<field index="38" term="http://rs.tdwg.org/dwc/terms/geodeticDatum"/>
		<field index="39" term="http://rs.tdwg.org/dwc/terms/stateProvince"/>
		<field index="40" term="http://rs.tdwg.org/dwc/terms/county"/>
		<field index="41" term="http://rs.tdwg.org/dwc/terms/municipality"/>
		<field index="42" term="http://rs.tdwg.org/dwc/terms/continent"/>
		<field index="43" term="http://rs.tdwg.org/dwc/terms/waterBody"/>
		<field index="44" term="http://rs.tdwg.org/dwc/terms/islandGroup"/>
		<field index="45" term="http://rs.tdwg.org/dwc/terms/island"/>
		<field index="46" term="http://rs.tdwg.org/dwc/terms/higherGeography"/>
		<!-- Identification fields -->
		<field index="47" term="http://rs.tdwg.org/dwc/terms/dateIdentified"/>
		<field index="48" term="http://rs.tdwg.org/dwc/terms/identifiedBy"/>
		<field index="49" term="http://rs.tdwg.org/dwc/terms/nomenclaturalCode"/>
		<field index="50" term="http://rs.tdwg.org/dwc/terms/taxonRemarks"/>
		<field index="51" term="http://rs.tdwg.org/dwc/terms/identificationRemarks"/>
		<field index="52" term="http://rs.tdwg.org/dwc/terms/identificationVerificationStatus"/>
		<field index="53" term="http://rs.tdwg.org/dwc/terms/identificationReferences"/>
		<field index="54" term="http://rs.tdwg.org/dwc/terms/scientificName"/>
		<field index="55" term="http://rs.tdwg.org/dwc/terms/identificationQualifier"/>
		<field index="56" term="http://rs.tdwg.org/dwc/terms/scientificNameAuthorship"/>
		<field index="57" term="http://rs.tdwg.org/dwc/terms/higherClassification"/>
		<field index="58" term="http://rs.tdwg.org/dwc/terms/kingdom"/>
		<field index="59" term="http://rs.tdwg.org/dwc/terms/phylum"/>
		<field index="60" term="http://rs.tdwg.org/dwc/terms/class"/>
		<field index="61" term="http://rs.tdwg.org/dwc/terms/order"/>
		<field index="62" term="http://rs.tdwg.org/dwc/terms/family"/>
		<field index="63" term="http://rs.tdwg.org/dwc/terms/genus"/>
		<field index="64" term="http://rs.tdwg.org/dwc/terms/subgenus"/>
		<field index="65" term="http://rs.tdwg.org/dwc/terms/specificEpithet"/>
		<field index="66" term="http://rs.tdwg.org/dwc/terms/infraspecificEpithet"/>
		<field index="67" term="http://rs.tdwg.org/dwc/terms/taxonRank"/>
		<field index="68" term="http://rs.tdwg.org/dwc/terms/typeStatus"/>
	</core>';
		else return '
    <core encoding="UTF-8" fieldsTerminatedBy="," linesTerminatedBy="\\n" fieldsEnclosedBy=\'"\' ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Occurrence">
        <files>
            <location>occurrence.txt</location>
        </files>
        <id index="0" />
        <!-- Occurrence fields -->
        <field index="0" term="http://rs.tdwg.org/dwc/terms/catalogNumber"/>
        <field index="1" term="http://rs.tdwg.org/dwc/terms/institutionCode"/>
        <field index="2" term="http://rs.tdwg.org/dwc/terms/collectionCode"/>
        <field index="3" term="http://rs.tdwg.org/dwc/terms/basisOfRecord"/>
        <field index="4" term="http://rs.tdwg.org/dwc/terms/occurrenceID"/>
        <field index="5" term="http://rs.tdwg.org/dwc/terms/occurrenceStatus"/>
        <field index="6" term="http://rs.tdwg.org/dwc/terms/recordNumber"/>
        <field index="7" term="http://purl.org/dc/terms/modified"/>
        <field index="8" term="http://rs.tdwg.org/dwc/terms/recordedBy"/>
        <field index="9" term="http://rs.tdwg.org/dwc/terms/fieldNumber"/>
        <field index="10" term="http://rs.tdwg.org/dwc/terms/samplingProtocol"/>
        <field index="11" term="http://rs.tdwg.org/dwc/terms/habitat"/>
        <field index="12" term="http://rs.tdwg.org/dwc/terms/eventRemarks"/>
        <field index="13" term="http://rs.tdwg.org/dwc/terms/verbatimElevation"/>
        <field index="14" term="http://rs.tdwg.org/dwc/terms/minimumElevationInMeters"/>
        <field index="15" term="http://rs.tdwg.org/dwc/terms/maximumElevationInMeters"/>
        <field index="16" term="http://rs.tdwg.org/dwc/terms/verbatimDepth"/>
        <field index="17" term="http://rs.tdwg.org/dwc/terms/minimumDepthInMeters"/>
        <field index="18" term="http://rs.tdwg.org/dwc/terms/maximumDepthInMeters"/>
        <field index="19" term="http://rs.tdwg.org/dwc/terms/minimumDistanceAboveSurfaceInMeters"/>
        <field index="20" term="http://rs.tdwg.org/dwc/terms/maximumDistanceAboveSurfaceInMeters"/>
        <field index="21" term="http://rs.tdwg.org/dwc/terms/country"/>
        <field index="22" term="http://rs.tdwg.org/dwc/terms/countryCode"/>
        <field index="23" term="http://rs.tdwg.org/dwc/terms/locality"/>
        <field index="24" term="http://rs.tdwg.org/dwc/terms/locationRemarks"/>
        <field index="25" term="http://rs.tdwg.org/dwc/terms/eventDate"/>
        <field index="26" term="http://rs.tdwg.org/dwc/terms/verbatimEventDate"/>
        <field index="27" term="http://rs.tdwg.org/dwc/terms/eventTime"/>
        <field index="28" term="http://rs.tdwg.org/dwc/terms/startDayOfYear"/>
        <field index="29" term="http://rs.tdwg.org/dwc/terms/endDayOfYear"/>
        <field index="30" term="http://rs.tdwg.org/dwc/terms/occurrenceRemarks"/>
        <field index="31" term="http://rs.tdwg.org/dwc/terms/sex"/>
        <field index="32" term="http://rs.tdwg.org/dwc/terms/verbatimCoordinates"/>
        <field index="33" term="http://rs.tdwg.org/dwc/terms/decimalLatitude"/>
        <field index="34" term="http://rs.tdwg.org/dwc/terms/decimalLongitude"/>
        <field index="35" term="http://rs.tdwg.org/dwc/terms/coordinateUncertaintyInMeters"/>
        <field index="36" term="http://rs.tdwg.org/dwc/terms/coordinatePrecision"/>
        <field index="37" term="http://rs.tdwg.org/dwc/terms/georeferenceProtocol"/>
        <field index="38" term="http://rs.tdwg.org/dwc/terms/geodeticDatum"/>
        <field index="39" term="http://rs.tdwg.org/dwc/terms/stateProvince"/>
        <field index="40" term="http://rs.tdwg.org/dwc/terms/county"/>
        <field index="41" term="http://rs.tdwg.org/dwc/terms/municipality"/>
        <field index="42" term="http://rs.tdwg.org/dwc/terms/continent"/>
        <field index="43" term="http://rs.tdwg.org/dwc/terms/waterBody"/>
        <field index="44" term="http://rs.tdwg.org/dwc/terms/islandGroup"/>
        <field index="45" term="http://rs.tdwg.org/dwc/terms/island"/>
        <field index="46" term="http://rs.tdwg.org/dwc/terms/higherGeography"/>
        <!-- Identification fields -->
        <field index="47" term="http://rs.tdwg.org/dwc/terms/dateIdentified"/>
        <field index="48" term="http://rs.tdwg.org/dwc/terms/identifiedBy"/>
        <field index="49" term="http://rs.tdwg.org/dwc/terms/nomenclaturalCode"/>
        <field index="50" term="http://rs.tdwg.org/dwc/terms/taxonRemarks"/>
        <field index="51" term="http://rs.tdwg.org/dwc/terms/identificationRemarks"/>
        <field index="52" term="http://rs.tdwg.org/dwc/terms/identificationVerificationStatus"/>
        <field index="53" term="http://rs.tdwg.org/dwc/terms/identificationReferences"/>
        <field index="54" term="http://rs.tdwg.org/dwc/terms/scientificName"/>
        <field index="55" term="http://rs.tdwg.org/dwc/terms/identificationQualifier"/>
        <field index="56" term="http://rs.tdwg.org/dwc/terms/scientificNameAuthorship"/>
        <field index="57" term="http://rs.tdwg.org/dwc/terms/higherClassification"/>
        <field index="58" term="http://rs.tdwg.org/dwc/terms/kingdom"/>
        <field index="59" term="http://rs.tdwg.org/dwc/terms/phylum"/>
        <field index="60" term="http://rs.tdwg.org/dwc/terms/class"/>
        <field index="61" term="http://rs.tdwg.org/dwc/terms/order"/>
        <field index="62" term="http://rs.tdwg.org/dwc/terms/family"/>
        <field index="63" term="http://rs.tdwg.org/dwc/terms/genus"/>
        <field index="64" term="http://rs.tdwg.org/dwc/terms/subgenus"/>
        <field index="65" term="http://rs.tdwg.org/dwc/terms/specificEpithet"/>
        <field index="66" term="http://rs.tdwg.org/dwc/terms/infraspecificEpithet"/>
        <field index="67" term="http://rs.tdwg.org/dwc/terms/taxonRank"/>
        <field index="68" term="http://rs.tdwg.org/dwc/terms/typeStatus"/>
    </core>';

	else if ($file=="identification")
		return '
	<extension encoding="UTF-8" fieldsTerminatedBy="," linesTerminatedBy="\\n" fieldsEnclosedBy=\'"\' ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/Identification">
		<files>
			<location>identification.txt</location>
		</files>
		<coreid index="0" />
		<field index="1" term="http://rs.tdwg.org/dwc/terms/dateIdentified"/>
		<field index="2" term="http://rs.tdwg.org/dwc/terms/identifiedBy"/>
		<field index="3" term="http://rs.tdwg.org/dwc/terms/nomenclaturalCode"/>
		<field index="4" term="http://rs.tdwg.org/dwc/terms/taxonRemarks"/>
		<field index="5" term="http://rs.tdwg.org/dwc/terms/identificationRemarks"/>
		<field index="6" term="http://rs.tdwg.org/dwc/terms/identificationVerificationStatus"/>
		<field index="7" term="http://rs.tdwg.org/dwc/terms/identificationReferences"/>
		<field index="8" term="http://rs.tdwg.org/dwc/terms/scientificName"/>
		<field index="9" term="http://rs.tdwg.org/dwc/terms/identificationQualifier"/>
		<field index="10" term="http://rs.tdwg.org/dwc/terms/scientificNameAuthorship"/>
		<field index="11" term="http://rs.tdwg.org/dwc/terms/higherClassification"/>
		<field index="12" term="http://rs.tdwg.org/dwc/terms/kingdom"/>
		<field index="13" term="http://rs.tdwg.org/dwc/terms/phylum"/>
		<field index="14" term="http://rs.tdwg.org/dwc/terms/class"/>
		<field index="15" term="http://rs.tdwg.org/dwc/terms/order"/>
		<field index="16" term="http://rs.tdwg.org/dwc/terms/family"/>
		<field index="17" term="http://rs.tdwg.org/dwc/terms/genus"/>
		<field index="18" term="http://rs.tdwg.org/dwc/terms/subgenus"/>
		<field index="19" term="http://rs.tdwg.org/dwc/terms/specificEpithet"/>
		<field index="20" term="http://rs.tdwg.org/dwc/terms/infraspecificEpithet"/>
		<field index="21" term="http://rs.tdwg.org/dwc/terms/taxonRank"/>
		<field index="22" term="http://rs.tdwg.org/dwc/terms/typeStatus"/>
	</extension>';

	else if ($file=='image')
		return '
	<extension encoding="UTF-8" fieldsTerminatedBy="," linesTerminatedBy="\\n" fieldsEnclosedBy=\'"\' ignoreHeaderLines="1" rowType="http://rs.gbif.org/terms/1.0/Multimedia">
		<files>
			<location>image.txt</location>
		</files>
		<coreid index="0" />
		<field index="1" term="http://purl.org/dc/terms/identifier"/>
		<field index="2" term="http://purl.org/dc/terms/references"/>
		<field index="3" term="http://purl.org/dc/terms/description"/>
		<field index="4" term="http://purl.org/dc/terms/format"/>
		<field index="5" term="http://purl.org/dc/terms/created"/>
		<field index="6" term="http://purl.org/dc/terms/creator"/>
		<field index="7" term="http://purl.org/dc/terms/license"/>
		<field index="8" term="http://purl.org/dc/terms/rightsHolder"/>
	</extension>';
	
	else if ($file=='measurementorfact')
		return '
    <extension encoding="UTF-8" fieldsTerminatedBy="," linesTerminatedBy="\\n" fieldsEnclosedBy=\'"\' ignoreHeaderLines="1" rowType="http://rs.tdwg.org/dwc/terms/MeasurementOrFact">
	        <files>
	            <location>measurementorfact.txt</location>
	        </files>
	        <coreid index="0" />
	        <field index="1" term="http://rs.tdwg.org/dwc/terms/measurementType"/>
	        <field index="2" term="http://rs.tdwg.org/dwc/terms/measurementValue"/>
	        <field index="3" term="http://rs.tdwg.org/dwc/terms/measurementAccuracy"/>
	        <field index="4" term="http://rs.tdwg.org/dwc/terms/measurementUnit"/>
	        <field index="5" term="http://rs.tdwg.org/dwc/terms/measurementDeterminedBy"/>
	        <field index="6" term="http://rs.tdwg.org/dwc/terms/measurementDeterminedDate"/>
	        <field index="7" term="http://rs.tdwg.org/dwc/terms/measurementMethod"/>
  	</extension>';
}

	
// Manual testing
/*$job["job"]["job_id"] = "test";
$job["transformation_id"] = 3;
$job["version_id"] = 1;
$job["job"]["input_file"] = "input/bgbm_herbarium_small.xml";
$job["job"]["input_file_zipped"] = false;
//$job["job"]["input_file"] = "input/test.zip";
//$job["job"]["input_file_zipped"] = true;

$trafo["version"] = ["transformation_id" => 3, "version_id" => 1, "files" => ["dwca.kjb", "abcd2.ktr"]];


print_r($job); print_r($trafo);
print_r(dwca_transformation($job, $trafo));
*/