<?php

/**@author Alex Borchers
 * The purpose of this file is to assist with API calls to the google drive folder for actions such as creating folders, adding files, etc.
 * source: https://thecodelearners.com/php-google-drive-api-list-create-folders-sub-folders-and-upload-files/
 * 
 * getClient(): This function returns authorized client object.
 * create_folder(): Creates folder or subfolder
 * create_sub_folders(): creates list of subfolders
 * check_folder_exists(): Before creating checks wheather folder exists using query terms.
 * upload_quote_to_drive(): Inserts file into projects and proposal sub folder
 * upload_subcontractor_quote_to_drive(): Inserts subcontractor quotes to sub folder
 * get_files_and_folders(): Retrives root folder with files with their direct childrens given a parent ID.
 * get_subcontractor_files(): lists out contents of subcontractor detail into an html list with checkboxes
 * dd(): This function is used to make debugging easier.
 */

//include constants sheet
include('constants.php');

error_reporting(E_ERROR | E_PARSE);
require __DIR__ . '/google-drive.php';

//decide testing based on use variable
if ($use == "test"){
	//testing (links to Test - New PW Corporate folder)
	$shared_ID = '0AM_oL3H7ZQULUk9PVA';
}
else{
	//live (links to PW Projects folder)
	$shared_ID = '0AC4lTFP9lOHhUk9PVA';
}

// This will create a folder and also sub folder when $parent_folder_id is given
function create_folder( $folder_name, $parent_folder_id, $sub_folder_opt=null ){

	// set new instance of google API call
	$service = new Google_Service_Drive( $GLOBALS['client'] );
	$folder = new Google_Service_Drive_DriveFile();

	$folder->setName( $folder_name );
	$folder->setMimeType('application/vnd.google-apps.folder');
	$folder->setDriveId($shared_ID);
	$folder->setParents( [ $parent_folder_id ] );   

	// set optional params to work with projects@pw email address
	$optional_params = [ 'supportsAllDrives' => true];
	
	// pass parameters and create folder
	$result = $service->files->create( $folder, $optional_params );

	// default folder_id to null (for error debugging)
	$folder_id = null;
	
	// if we have an ID, update from null
	if( isset( $result['id'] ) && !empty( $result['id'] ) )
		$folder_id = $result['id'];
	
	// if successful, set parent of $folder
	if( !empty( $parent_folder_id ) )
		$folder->setParents( [ $parent_folder_id ] );        
	
	// if we're creating a folder for a project, check if we need to set up entire folder structure
	if ($sub_folder_opt == true)
		create_sub_folders($folder_id);
	
	// return ID of new folder
	return $folder_id;    
}

// This will a set of generic sub folers for each project
function create_sub_folders($parent_folder_id, $tell=null){
		
	//if tell is null, create base sub folders
	if (is_null($tell)){
		
		//initialize sub folders
		$sub_folders = ['Project Requirements', 'Solution Technical Details', 'Proposal and Pricing', 'Close-Out Documentation'];
	
		for ($i = 0; $i < sizeof($sub_folders); $i++){

			// default folder_id to null (for error catching)
			$folder_id = create_folder($sub_folders[$i], $parent_folder_id);

			//call create sub folders with tell
			create_sub_folders($folder_id, $sub_folders[$i]);

		}
	}
	// handles sub folders contained in each sub folder
	else{
		// determine next set of sub folders depending on value of $tell
		if ($tell == "Project Requirements")
			$sub_folders = ['General Requirements', 'Legal'];
		elseif($tell == "Solution Technical Details")
			$sub_folders = ['PW Engineering Details', 'Subcontractor Details'];
		elseif($tell == "Proposal and Pricing")
			$sub_folders = [];
		elseif($tell == "Close-Out Documentation")
			$sub_folders = ['Photos', 'Test Results', 'As-Built Documentation'];
		// catch for any unknown tells
		else
			$sub_folders = [];
		
		for ($i = 0; $i < sizeof($sub_folders); $i++){

			// set new instance of google API call
			$service = new Google_Service_Drive( $GLOBALS['client'] );
			$folder = new Google_Service_Drive_DriveFile();

			// set parameters for this folder
			$folder->setName( $sub_folders[$i] );
			$folder->setMimeType('application/vnd.google-apps.folder');
			$folder->setDriveId($shared_ID);
			$folder->setParents( [ $parent_folder_id ] );     
			$optional_params = [ 'supportsAllDrives' => true];

			// pass parameters and create folder
			$result = $service->files->create( $folder, $optional_params );

		}
	}
}

// This will check folders and sub folders by name
function check_folder_exists( $folder_name, $parent_folder_id){
   		
    $service = new Google_Service_Drive($GLOBALS['client']);
	
	try {
		$parameters = array(
			'driveId' => $shared_ID, //analytics
			'fields' => 'nextPageToken, files(*)',
			'supportsAllDrives' => true,
			'includeItemsFromAllDrives' => true,
			'q' => "mimeType='application/vnd.google-apps.folder' and name='" . $folder_name . "' and '" . $parent_folder_id . "' in parents and trashed = false"
		);
		$files = $service->files->listFiles($parameters);
	
		$op = [];
		foreach( $files as $k => $file ){
			$op[] = $file;
		}
	
		return $op;
		
	} catch (Exception $e) {
		print "An error occurred: " . $folder_name . " " . $e->getMessage();
		return [];
	}
}

/**
 * This will display list of folders and direct child folders and files given a parent folder id
 * @param mixed $parent_folder_id
 * @return mixed
 */
function get_files_and_folders($parent_folder_id){

	// new instance of google API
    $service = new Google_Service_Drive($GLOBALS['client']);

	// set parameters for search
	$parameters = array(
		'driveId' => $shared_ID, //analytics
		'fields' => '*',
		'supportsAllDrives' => true,
		'includeItemsFromAllDrives' => true,
		'q' => "mimeType != 'application/vnd.google-apps.folder' and '" . $parent_folder_id . "' in parents and trashed=false"
	);
	
	// grab files defined by parameters above
    $files = $service->files->listFiles($parameters);
	return $files;
    
	/*
	// see detail and attributes we can use
	// print_r($files);
	
	// set beginning of list
    echo "<ul class = 'google-drive-file-list'>";

	// loop through each file, output as list
    foreach( $files as $k => $file ){
        echo "<li> <input type = 'checkbox' id = '{$file['id']}'> {$file['name']}";

			//OPTIONAL - loop through sub files
			// subfiles
			$parameters = array(
				'driveId' => $shared_ID, //analytics
				//'fields' => 'nextPageToken, files(*)',
				'supportsAllDrives' => true,
				'includeItemsFromAllDrives' => true,
				'q' => "'" . $file['id'] . "' in parents and trashed = false"
			);
		
			//$sub_files = $service->files->listFiles(array('q' => "'{$file['id']}' in parents"));
			$sub_files = $service->files->listFiles($parameters);
				
			echo "<ul>";
			foreach( $sub_files as $kk => $sub_file ) {
				echo "<li> {$sub_file['name']} - {$sub_file['id']}  ---- ". $sub_file['mimeType'] ." </li>";
			}
			echo "</ul>";
        echo "</li>";
    }

	// close list
    echo "</ul>";
	*/
}

/**
 * Handles taking google drive link OR ID and returning the list of files within the folder path: [project folder] / Solution Technical Details / Subcontractor Details
 * @param mixed $parent_file_id
 * @return mixed
 */
function get_subcontractor_files($parent_file_id){

	// new instances of google API
	$service = new Google_Service_Drive( $GLOBALS['client'] );

	// check if this is full URL or just ID (check for "folders/")
	$folder_pos = strpos($parent_file_id, "folders/");

	// if we find something, update parent ID
	if ($folder_pos > -1)
		$parent_file_id = substr($parent_file_id, $folder_pos + 8);
	
	// check to see if Solution Technical Details subfolder exists
	$solution_tech_details = check_folder_exists('Solution Technical Details', $parent_file_id);
		
	// check to see if we found a match (if not return nothing)
	if (!isset($solution_tech_details[0]['id']))
		return "";

	// now check solution technical details folder to see if we have a subfolder called subcontractor details
	$sub_details = check_folder_exists('Subcontractor Details', $solution_tech_details[0]['id']);

	// if we find match, update parent ID
	if (!isset($sub_details[0]['id']))
		return "";

	// use sub_detail id to get list of files
	$files = get_files_and_folders($sub_details[0]['id']);

	// see detail and attributes we can use
	//print_r($files[0]);
	
	// render HTML list for user to select from
	// set beginning of list
    echo "<ul class = 'google-drive-file-list'>";

	// loop through each file, output as list
    foreach( $files as $k => $file ){
        echo "<li> <input type = 'checkbox' class = 'google-drive-file' id = '{$file['id']}'> <span class = 'google-drive-file-name'>{$file['name']}</span></li>";
    }

	// close list
    echo "</ul>";
}

/**
 * Handles fetching subcontractor onboarding email attachments, downloading to server, attaching to email, and removing from server.
 * @param mixed $mail PHP Mailer object instance
 * @param mixed $search_files array with names of files we are looking for
 * @return mixed PHP Mailer object with package attached
 */
function get_subcontractor_onboarding_package($mail, $search_files){

	// get sub-contractor files based on folder ID
	$files = get_files_and_folders("1j4zapbX6aZZCHKp_MINeNexE_tFljUXH");
	// $files = get_files_and_folders("1BBgQM-rXFBoE8m3mnQpjDmSCkzatoQbo");	(Test )
	// print_r($files);

	// loop through list and look for specific file name ('subcontractor package.pdf')
	foreach($files as $k => $file){

		// if we find match, download and push to $mailer object
		if (in_array($file['name'], $search_files)){
			$file_path = download_file($file['id'], $file['name']);
			$mail->addAttachment($file_path);
		}
	}

	// return object with package included
	return $mail;
}

/**
 * Handles uploading quote to google drive "proposal and pricing" subfolder
 * @param mixed $file_path
 * @param mixed $file_name
 * @param mixed $parent_file_id
 * @return bool
 */
function upload_quote_to_drive( $file_path, $file_name, $parent_file_id = null ){
	
	$service = new Google_Service_Drive( $GLOBALS['client'] );
    $file = new Google_Service_Drive_DriveFile();
	
    $file->setName( $file_name );
	
	$p_and_p = check_folder_exists('Proposal and Pricing', $parent_file_id);
		
	//check to see if we found a match
	if (isset ($p_and_p[0]['id']))
		$parent_file_id = $p_and_p[0]['id'];
	
    if( !empty( $parent_file_id ) ){
        $file->setParents( [ $parent_file_id ] );        
    }

    $result = $service->files->create(
        $file,
        array(
            'data' => file_get_contents($file_path),
            'mimeType' => 'application/octet-stream',
			'supportsAllDrives' => true
        )
    );

    $is_success = false;
    
    if( isset( $result['name'] ) && !empty( $result['name'] ) ){
        $is_success = true;
    }

    return $is_success;
}

/**
 * Handles uploading attached documents related to subcontractors to google drive sub folder path [project folder] / Solution Technical Details / Subcontractor Details
 * @param mixed $file_path
 * @param mixed $file_name
 * @param mixed $parent_file_id
 * @return bool
 */
function upload_subcontractor_quote_to_drive($file_path, $file_name, $parent_file_id){
	
	// new instances of google API
	$service = new Google_Service_Drive( $GLOBALS['client'] );
    $file = new Google_Service_Drive_DriveFile();
	
	// set file name passed by user
    $file->setName( $file_name );
	
	// check to see if Solution Technical Details subfolder exists
	$solution_tech_details = check_folder_exists('Solution Technical Details', $parent_file_id);
		
	//check to see if we found a match
	if (isset ($solution_tech_details[0]['id'])){

		// now check solution technical details folder to see if we have a subfolder called subcontractor details
		$parent_file_id = $solution_tech_details[0]['id'];
		$sub_details = check_folder_exists('Subcontractor Details', $parent_file_id);

		// if we find match, update parent ID
		if (isset ($sub_details[0]['id']))
			$parent_file_id = $sub_details[0]['id'];
	}

	// set parents of file (using found file id)
	$file->setParents( [ $parent_file_id ] );

	// create & upload file
    $result = $service->files->create(
        $file,
        array(
            'data' => file_get_contents($file_path),
            'mimeType' => 'application/octet-stream',
			'supportsAllDrives' => true
        )
    );

	// hold boolean to return if we have a successful upload or not
    $is_success = false;
    
    if( isset( $result['name'] ) && !empty( $result['name'] ) ){
        $is_success = true;
    }

    return $is_success;
}

/**
 * Handles uploading attached documents related to subcontractors to google drive sub folder path [project folder] / Solution Technical Details / Subcontractor Details
 * @param mixed $file_paths
 * @param mixed $file_names
 * @param mixed $parent_file_id
 * @param mixed $type
 * @return bool
 */
function upload_ops_service_data_to_drive($file_paths, $file_names, $parent_file_id, $type){

	// new instances of google API
	$service = new Google_Service_Drive( $GLOBALS['client'] );
    $file = new Google_Service_Drive_DriveFile();
	
	// depending on type, take different route to folder
	if ($type == "Site Survey"){

		// check to see if Solution Technical Details subfolder exists
		$solution_tech_details = check_folder_exists('Solution Technical Details', $parent_file_id);

		//check to see if we found a match
		if (isset ($solution_tech_details[0]['id'])){

			// now check solution technical details folder to see if we have a subfolder called PW Engineering Details
			$parent_file_id = $solution_tech_details[0]['id'];
			$pw_engineering_details = check_folder_exists('PW Engineering Details', $parent_file_id);

			// if we find match, update parent ID
			if (isset ($pw_engineering_details[0]['id'])){
				
				// now check solution technical details folder to see if we have a subfolder called Site Survey Data
				$parent_file_id = $pw_engineering_details[0]['id'];
				$site_survey_data = check_folder_exists('Site Survey Data', $parent_file_id);
				
				// if we find a match, update parent ID
				if (isset ($site_survey_data[0]['id']))
					$parent_file_id = $site_survey_data[0]['id'];
				// if not, create the folder at this time
				else
					$parent_file_id = create_folder('Site Survey Data', $parent_file_id);
				
			}
			// if no sub details, return failure to upload
			else
				return false;
		}
		else
			return false;

	}
	elseif ($type == "Data Collection"){
		
		// check to see if Solution Technical Details subfolder exists
		$closeout = check_folder_exists('Close-Out Documentation', $parent_file_id);

		//check to see if we found a match
		if (isset ($closeout[0]['id'])){

			// now check solution technical details folder to see if we have a subfolder called PW Engineering Details
			$parent_file_id = $closeout[0]['id'];
			$test_results = check_folder_exists('Test Results', $parent_file_id);

			// if we find match, update parent ID
			if (isset ($test_results[0]['id'])){
				// update parent folder id
				$parent_file_id = $test_results[0]['id'];

				// create new folder to hold data collection for this date
				$data_collection = date("m-d-Y H:i:s") . " Data Collection";
				$parent_file_id = create_folder($data_collection, $parent_file_id);
			}
			// if no sub details, return failure to upload
			else
				return false;
		}
		else
			return false;

	}

	// set parents of file (using found file id)
	$file->setParents( [ $parent_file_id ] );

	// hold boolean to return if we have a successful upload or not
    $is_success = true;

	// loop through file_paths and add to parent folder
	for($i = 0; $i < sizeof($file_paths); $i++){

		// set file name passed by user
		$file->setName( $file_names[$i] );
		
		// create & upload file
		$result = $service->files->create(
			$file,
			array(
				'data' => file_get_contents($file_paths[$i]),
				'mimeType' => 'application/octet-stream',
				'supportsAllDrives' => true
			)
		);
	
		// check for success
		if( !isset( $result['name'] ) || empty( $result['name'] ) )
			$is_success = false;

	}

    return $is_success;

}

/**
 * Handles uploading attached documents related to qr received items to google drive sub folder path [project folder] / Close-Out Documentation / [new folder]
 * @param mixed $file_paths
 * @param mixed $file_names
 * @param mixed $parent_file_id
 * @param mixed $type
 * @return mixed (false if failure, google drive parent folder ID if successful)
 */
function upload_qr_receiving_images($file_paths, $file_names, $parent_file_id, $ship_id){

	// new instances of google API
	$service = new Google_Service_Drive( $GLOBALS['client'] );
    $file = new Google_Service_Drive_DriveFile();
	
	// check to see if Solution Technical Details subfolder exists
	$closeout = check_folder_exists('Close-Out Documentation', $parent_file_id);

	//check to see if we found a match
	if (isset ($closeout[0]['id'])){

		// now check solution technical details folder to see if we have a subfolder called PW Engineering Details
		$photos = check_folder_exists('Photos', $closeout[0]['id']);
		
		// if we find match, update parent ID
		if (isset ($photos[0]['id'])){

			// Last, check for folder called "Shipment #x", create if not found
			$ship_string = 'Shipment #' . $ship_id;
			$shipment = check_folder_exists($ship_string, $photos[0]['id']);

			if (isset($shipment[0]['id']))
				$ship_folder_id = $shipment[0]['id'];			
			else
				$ship_folder_id = create_folder($ship_string, $photos[0]['id']);

			// create new folder to hold data collection for this date
			$container_string = "Materials Received - " . date("m-d-Y H:i:s");
			$parent_file_id = create_folder($container_string, $ship_folder_id);
		}
		else
		// if no sub details, return failure to upload
			return false;
	}
	else
		return false;

	// set parents of file (using found file id)
	$file->setParents( [ $parent_file_id ] );

	// loop through file_paths and add to parent folder
	for($i = 0; $i < sizeof($file_paths); $i++){

		// set file name passed by user
		$file->setName( $file_names[$i] );
		
		// create & upload file
		$result = $service->files->create(
			$file,
			array(
				'data' => file_get_contents($file_paths[$i]),
				'mimeType' => 'application/octet-stream',
				'supportsAllDrives' => true
			)
		);
	
		// check for success
		if( !isset( $result['name'] ) || empty( $result['name'] ) )
			return false;

	}

    return $parent_file_id;

}


function add_subcontractor_quotes($target_dir, $files, $form_files, $vendor_id, $gdrive){

	// loop through files and upload to drive folder
	foreach ($files as $file){

		// save file locally
		// prefix all file names with vendor ID
		$target_file = $target_dir . "\\uploads\\" . $vendor_id . "-" . basename($form_files[$file]["name"]);

		// if file is added successfully, push to google drive folder, then remove locally
		if (move_uploaded_file($form_files[$file]["tmp_name"], $target_file)) {

			// parse out info to re-create file name [vendor_id]_[file name]_[date].[ext]
			$date_string = date("m-d-Y");
			$ext = "." . pathinfo($form_files[$file]["name"], PATHINFO_EXTENSION);
			$just_name = str_replace($ext,"",$form_files[$file]["name"]);
			$new_name = $vendor_id . "_" . $just_name . "_" . $date_string . $ext;

			$temp = upload_subcontractor_quote_to_drive($target_file, $new_name, $gdrive);
			unlink($target_file);	// remove locally
		}
		else {
			echo "Sorry, there was an error uploading your file.";
		}
	}
}

/**
 * [NOT IN USE] can update folder name or file name based on file ID
 * @param mixed $fileId
 * @param mixed $newName
 * @return void
 */
function update_name($fileId, $newName) {
	
	$service = new Google_Service_Drive( $GLOBALS['client'] );
	
	try {
		$file = new Google_Service_Drive_DriveFile();
		$file->setName($newName);
		$service->files->update($fileId, $file, array(
			'supportsAllDrives' => true
		));
		
	} catch (Exception $e) {
		print "An error occurred: " . $e->getMessage();
	}
	
}

/**
 * Downloads file to FST/uploads directory, returns file path
 * @param mixed $id google drive file ID to be downloaded
 * @param mixed $name (optional) pass the requested name of the file (with .pdf), otherwise save file as ID
 * @return string filepath
 */
function download_file($id, $name = null)
 {
    try {

		// new instance of google API call
		$client = getClient();
		$client->addScope(Google_Service_Drive::DRIVE);
		//$client->useApplicationDefaultCredentials();
		
		// new instance of google service drive
		$service = new Google_Service_Drive($client);
		$content = $service->files->get($id, array("alt" => "media"));
		
		// Set file path based on parameters
		if (is_null($name))
			$file_path = getcwd() . "//uploads//" . $id . ".pdf";
		else
			$file_path = getcwd() . "//uploads//" . $name;

		// Open file handle for output.
		$outHandle = fopen($file_path, "w+");
		
		// Until we have reached the EOF, read 1024 bytes at a time and write to the output file handle.
		while (!$content->getBody()->eof()) {
			fwrite($outHandle, $content->getBody());
		}
		
		// Close output file handle.
		fclose($outHandle);

		// return name of file
		return $file_path;

    } catch(Exception $e) {
      echo "Error Message: ".$e;
		return $e;
    }
}

/**TESTING attemp to create shortcut to another folder [create_shortcut & create_shortcut2] */
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
function create_shortcut(){

	try {

		// new instance of google API call
		$client = getClient();
		$client->addScope(Google_Service_Drive::DRIVE);
        $driveService = new Drive($client);
        $fileMetadata = new DriveFile(array(
            'name' => 'Testing',
            'mimeType' => 'application/vnd.google-apps.shortcut',
			'shortcutDetails' => array(
				'targetID' => '1HvLsga7KGd1jB8Dtr-K4RV_NlxMS4h__'
			)
		));
        $file = $driveService->files->create($fileMetadata, array(
            'fields' => 'id'));
        printf("File ID: %s\n", $file->id);
        return $file->id;

    } catch(Exception $e) {
        echo "Error Message: ".$e;
    }

}

function create_shortcut2(){

	try {
		$client = getClient();
		$client->addScope(Google_Service_Drive::DRIVE);
        $service = new Drive($client);

		$file = new \Google_Service_Drive_DriveFile();
		$file->setName('Shortcut Name');
		$file->setParents(["1arKSHNtCPlLF4qR41P_gfkwkJhE7YJ3m"]);
		$file->setMimeType('application/vnd.google-apps.shortcut');

		$shortcutDetails = new \Google_Service_Drive_DriveFileShortcutDetails();
		$shortcutDetails->setTargetId("1HvLsga7KGd1jB8Dtr-K4RV_NlxMS4h__");
		$file->setShortcutDetails($shortcutDetails);

		$createdFile = $service->files->create($file);


    } catch(Exception $e) {
        echo "Error Message: ".$e;
    }
}