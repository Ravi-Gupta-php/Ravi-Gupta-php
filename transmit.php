<?php

// Config
require_once "../../../redcap_connect.php";
require_once "transmission_check.php";

global $REDCAP_PROXY_HOST, $REDCAP_PROXY_PORT;

$dirSep = DIRECTORY_SEPARATOR;
if ($dirSep == '/') {
   $os = 'linux';
}
// $redcapPath = realpath(__DIR__ . '/../..');
$redcapPath = realpath(__DIR__ . $dirSep . '..' . $dirSep .'..');
$versionFile = $redcapPath . $dirSep . 'myversion.txt';
$whattogetFile = $redcapPath . $dirSep . 'myversion.txt';
$pathExists = file_exists($versionFile);
// error_log("current wd: " . getcwd(), 0);
// error_log("current path: " . $versionFile . " exists: " . $pathExists, 0);

// $txt_fileVC = file_get_contents('C:\wamp64\www\redcap\myversion.txt');
$txt_fileVC = file_get_contents($versionFile);
$rowsVC = explode("\n", $txt_fileVC);

$currentABCDversion = '0.0';

foreach($rowsVC as $line)
{
	$row_data = preg_split("/;|,/", $line);
	$$currentABCDversion = $row_data[0];
}
error_log("current currentABCDversion: "  . $$currentABCDversion, 0);


// Setup proxy seerver if necessary.
$db_conn_file = dirname(APP_PATH_DOCROOT)."/database.php";	
include ($db_conn_file);
// error_log("hostname: " . $hostname);
if (strpos($hostname, "who.int") !== false) {
  error_log("Using proxy for a WHO server.");
  $REDCAP_PROXY_HOST = "tcp://openproxy.who.int"; // Proxy server address
  $REDCAP_PROXY_PORT = "8080";    // Proxy server port
  // Username and Password are required only if your proxy server needs basic authentication
  
  $auth = base64_encode("$PROXY_USER:$PROXY_PASS");
  stream_context_set_default(
  array(
    'http' => array(
    'proxy' => "$REDCAP_PROXY_HOST:$REDCAP_PROXY_PORT",
    'request_fulluri' => true
    //  'header' => "Proxy-Authorization: Basic $auth"
    // Remove the 'header' option if proxy authentication is not required
    )
  )
  );
}

if(isset($_POST['action']) && !empty($_POST['action'])) {
    $action = $_POST['action'];
    switch($action) {
        case 'transmitForm' : TransmitForm($_POST['record_id'], $_POST['project_id'], $_POST['forms'], $_POST['event_id'], $_POST['logEvents'], $_POST['fieldList'], $_POST['checkboxesOnly']);
        break;
        case 'getcases' : GetCaseForms($_POST['project_id']);
        break;
        case 'log_start' : LogStart($_POST['project_id']);
        break;
        case 'log_completion' : LogCompletion($_POST['project_id'], $_POST['message']);
        break;
        case 'update_study' : UpdateStudy($_POST['project_id']);
        break;
        case 'check_connectivity' : CheckConnectivity($_POST['project_id']);
        break;
        case 'update_study2' : UpdateStudy2($_POST['project_id']);
        break;
        case 'retrieve' : RetrieveData($_POST['record_id'],$_POST['project_id']);
        break;
        case 'retrieve_case_list' : RetrieveCaseList($_POST['project_id']);
        break;
        case 'show_transmission_log' : ShowTransmissionLog($_POST['project_id'], $_POST['begin_limit']);
        break;
        case 'sync_all_records' : ListAllLocalRecords($_POST['project_id'], $_POST['records'], $_POST['saveAsExcel']);
        break;
        case 'check_remote_record' : CheckRemoteRecordAction($_POST['project_id'], $_POST['recordId'], $_POST['eventId'], $_POST['forms'] , $_POST['checkAllFields']);
        break;
        case 'sendPendingTransmission': SendPendingTransmission($_POST['project_id'], $_POST['record']);
        break;
        case 'downloadRemoteCsv': DownloadRemoteRecords($_POST['project_id'], $_POST['queryRemote'], $_POST['formName'], $_POST['eventName'], $_POST['num']);
        break;
        case 'compareLocalRemoteValues': CompareLocalRemoteValues($_POST['project_id']);
        break;
        default: echo 'Undefined Action.';
    }
}

/**
 * Retrieve Data feature
 * Fetches a list of all record_id's for this project.
 */
function RetrieveCaseList($project_id) {

  $settings = GetTransmissionSettings();
  
  $project_api_mapping_string = $settings['transmission_remote_token'];
  $project_api_mapping_array = explode(';', $project_api_mapping_string);

  foreach ($project_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $api_token = $api_token_pair[1];
      break;
    }
  }

  $local_api_mapping_string = $settings['transmission_local_token'];
  $local_api_mapping_array = explode(';', $local_api_mapping_string);

  foreach ($local_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $local_api_token = $api_token_pair[1];
      break;
    }
  }

  $record_id_field = REDCap::getRecordIdField();
  
  $project_url = $settings['transmission_remote_url'];
  $local_url = $settings['transmission_local_url'];

  LogResult(date('Y-m-d H:i:s') . ": retrieving ID list for import.");

  $data = array(
    'token' => $api_token,
    'content' => 'record',
    'format' => 'json',
    'type' => 'flat',
    'fields' => $record_id_field,
    'rawOrLabel' => 'raw',
    'rawOrLabelHeaders' => 'raw',
    'exportCheckboxLabel' => 'false',
    'exportSurveyFields' => 'false',
    'exportDataAccessGroups' => 'false',
    'returnFormat' => 'json'
  );
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $project_url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
  $output = curl_exec($ch);
  curl_close($ch);

  $screening_ids = json_decode($output,true);

  $id_list = array();
  foreach ($screening_ids as $key => $value) {
    $id = $value['dy1_scrn_scrnid'];
    if(!in_array($id, $id_list, true)){
      array_push($id_list, $id);
    }
  }
  
  LogResult(date('Y-m-d H:i:s') . ": retrieved ids: " . count($id_list));

  echo (json_encode($id_list));
}

/**
 * Retrieves record for a record_id on remote server.
 */
function RetrieveData($record_id,$project_id) {

// Get transmission settings

  $settings = GetTransmissionSettings();

  $project_api_mapping_string = $settings['transmission_remote_token'];
  $project_api_mapping_array = explode(';', $project_api_mapping_string);

  foreach ($project_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $api_token = $api_token_pair[1];
      break;
    }
  }

  $local_api_mapping_string = $settings['transmission_local_token'];
  $local_api_mapping_array = explode(';', $local_api_mapping_string);

  foreach ($local_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $local_api_token = $api_token_pair[1];
      break;
    }
  }

  $record_id_field = REDCap::getRecordIdField();

  $project_url = $settings['transmission_remote_url'];
  $local_url = $settings['transmission_local_url'];

  $data = array(
    'token' => $api_token,
    'content' => 'record',
    'format' => 'json',
    'type' => 'flat',
    'records' => $record_id,
    'rawOrLabel' => 'raw',
    'rawOrLabelHeaders' => 'raw',
    'exportCheckboxLabel' => 'false',
    'exportSurveyFields' => 'false',
    'exportDataAccessGroups' => 'false',
    'returnFormat' => 'json'
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $project_url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
  curl_setopt($ch, CURLOPT_VERBOSE, 1);
  $verbose = fopen('log-verbose.txt', 'w+');
  curl_setopt($ch, CURLOPT_STDERR, $verbose);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
  $output = curl_exec($ch);
  curl_close($ch);

  if(curl_error($c))
{
    echo 'error:' . curl_error($c);
}

  $output_json =  explode("[", $output);
  $data_output = '[' . $output_json[1];

  $verboseLog = stream_get_contents($verbose);
  
  if ($output === FALSE) {
    printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
    rewind($verbose);
    echo "Error while importing data from " . $project_url. " Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
  } else {

  // Parse records and remove _complete field if it is the only field for a form
  // Import Data

  //  error_log("data_output : $data_output ", 0);

    $data = array('token' =>  $local_api_token, 'content' => 'record', 'format' => 'json', overwriteBehavior => 'false', 'data' => $data_output);
    error_log("Attempting to import record: " . $record_id. " to local_url: " . $local_url, 0);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $local_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    $verbose = fopen('log-verbose.txt', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    $output = curl_exec($ch);
    curl_close($ch);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);

    if ($output === FALSE) {
      error_log(date('Y-m-d H:i:s') . ": result of importing data:" . $verboseLog, 0);
      printf("cUrl error (#%d): %s<br>\n", curl_errno($ch), htmlspecialchars(curl_error($ch)));
      echo "Error while importing data to " . $local_url. " Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
      REDCap::logEvent($action_description = "Data Transmission (Retrieve)", $changes_made = "Record " . $record_id . " could not be imported from the remote server.\n Error: " . $verboseLog);
    } else {
      // rewind($verbose);
      if (strpos($output, 'error') !== false) {
        $errorMessage = "ERROR: When importimg record: " . $record_id . " Message: " . $output;
        echo $errorMessage;
        error_log("errorMessage : $errorMessage ", 0);
        REDCap::logEvent($action_description = "Data Transmission (Retrieve)", $changes_made = $errorMessage);
      } else {

        $successMessage = "Success: imported record: " . $record_id;
        echo $successMessage;
        REDCap::logEvent($action_description = "Data Transmission (Retrieve)", $changes_made = $successMessage);
      }

    }
  }
}

function CheckConnectivity($project_id) {
  $settings = GetTransmissionSettings();
  error_log("transmission_remote_url: ". $settings['transmission_remote_url'], 0);
  error_log("transmission_remote_token: ". $settings['transmission_remote_token'], 0);
  $array = get_headers($settings['transmission_remote_url'], 1);
  $result = $array[0];
  error_log("Result from checking headers of remote server: " . $settings['transmission_remote_url'] . " Result: " . print_r($result, true) );
  if(strpos($result,"501")) { // REDCap API returns 501 when reached
    echo 'CONNECTED';
  } else {
    echo 'NOT_CONNECTED';
  }
}

function LogStart($project_id) {
  global $conn; 
  $date = date('Y-m-d H:i:s');
  REDCap::logEvent($action_description = "Data Transmission", $changes_made = "Transmission initiated");
  // error_log("Don't forget to uncomment the Data Transmission log in LogStart", 0 );
}

function LogCompletion($project_id, $message) {
  global $conn; 
  $sql = "SET @log_id = (select log_event_id from redcap_log_event where project_id = '$project_id' and data_values = 'Transmission initiated' order by log_event_id desc limit 1); update redcap_log_event set data_values = '$message' where log_event_id = @log_id";
    mysqli_multi_query($conn, $sql);
}

function GetCases($project_id) {

  // This function retrieves a list of record ids from REDCap (of records that need to be transmitted to the server)
  // Currently there are two settings -- Full - all records; Incremental - records changed since the last successful transmission

  	global $conn; 
  	$ids = [];  $trans_count;


    // The record id field changes for each study -- get the appropriate field name

    $record_id_field = REDCap::getRecordIdField();
    
    // Get transmission settings (these are stored in the redcap settings table)

    $settings = GetTransmissionSettings();

    // Select records - incremental or full
    // The only difference here is the SQL query to pull the ids
    // Transmission model = 1 --> Incremental

    if ($settings['transmission_model'] == 1) {
        $sql="select count(log_event_id) as trans_count from redcap_log_event where project_id='$project_id' and data_values='Transmission completed' limit 1";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) {  
          $trans_count = $row['trans_count'];
        }

        // $trans_count is the number of successful prior transmissions
        // If 0 then we need to transmit all the data
        // If not 0 then get all the ids of records that have changed since the last successful transmission

        if ($trans_count == '0') {
          $sql = "select distinct value from redcap_data where project_id = '$project_id' and field_name = '$record_id_field'";
          // error_log("sql for GetCases trans_count 0 is $sql", 0);
        } else {
         $sql = "select pk as value from redcap_log_event where project_id='$project_id' and log_event_id > (Select log_event_id from redcap_log_event where project_id='$project_id' and data_values='Transmission completed' order by log_event_id desc limit 1) and (description like '%Update record%' or description like '%Create record%')";       
        }

    // For full transmission, get all record ids

    } else if ($settings['transmission_model'] == '2') {
      $sql = "select distinct value from redcap_data where project_id = '$project_id' and field_name = '$record_id_field'";
    }
 	
 	$result = mysqli_query($conn, $sql);

    // Push ids on to array and return json object

  	while ($row = mysqli_fetch_assoc($result)) {  
  		array_push($ids, $row['value']);
    }
    
	echo json_encode($ids);
}

function GetCaseForms($project_id) {

  // This function retrieves a list of record ids and field names from REDCap (of records that need to be transmitted to the server)
  // Currently there are two settings -- Full - all records; Incremental - records changed since the last successful transmission

  	global $conn; 
  	$ids = [];  $trans_count; $allFields = []; $formFieldsToSync = [];


    // The record id field changes for each study -- get the appropriate field name

    $record_id_field = REDCap::getRecordIdField();
    
    // Get transmission settings (these are stored in the redcap settings table)

    $settings = GetTransmissionSettings();

    // Select records - incremental or full
    // The only difference here is the SQL query to pull the ids
    // Transmission model = 1 --> Incremental

    if ($settings['transmission_model'] == 1) {
        $sql="select count(log_event_id) as trans_count from redcap_log_event where project_id='$project_id' and data_values='Transmission completed' limit 1";
        $res = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($res)) {  
          $trans_count = $row['trans_count'];
        }

        // $trans_count is the number of successful prior transmissions
        // If 0 then we need to transmit all the data
        // If not 0 then get all the ids of records that have changed since the last successful transmission

        if ($trans_count == '0') {
          // $sql = "select log_event_id, pk, event_id, data_values from redcap_data where project_id = '$project_id' and field_name = '$record_id_field'";
          // $sql = "select distinct value from redcap_data where project_id = '$project_id' and field_name = '$record_id_field'";
          if ($settings['transmission_instance_type'] == 0) {
            $sql = "select log_event_id, pk, event_id, data_values from redcap_log_event where project_id='$project_id' and (description like 'Update record' or description like 'Create record') and (description NOT like '%Update Record (API)%' or description NOT like '%Create record (API)%') and transmit_date IS NULL";       
          } else {
            $sql = "select log_event_id, pk, event_id, data_values from redcap_log_event where project_id='$project_id' and (description like '%Update record%' or description like '%Create record%') and transmit_date IS NULL";
          }
          // error_log("sql for GetCaseForms trans_count 0: $sql", 0);
        } else {
          if ($settings['transmission_instance_type'] == 0) {
            $sql = "select log_event_id, pk, event_id, data_values from redcap_log_event where project_id='$project_id' and log_event_id > IFNULL((Select log_event_id from redcap_log_event where project_id='$project_id' and data_values='Transmission completed' order by log_event_id desc limit 1), 0) and (description like 'Update record' or description like 'Create record') and (description NOT like '%Update Record (API)%' or description NOT like '%Create record (API)%') and transmit_date IS NULL";       
          } else {
            $sql = "select log_event_id, pk, event_id, data_values from redcap_log_event where project_id='$project_id' and log_event_id > IFNULL((Select log_event_id from redcap_log_event where project_id='$project_id' and data_values='Transmission completed' order by log_event_id desc limit 1), 0) and (description like '%Update record%' or description like '%Create record%') and transmit_date IS NULL";
          }
        }

    // For full transmission, get all record ids

    } else if ($settings['transmission_model'] == '2') {
      $sql = "select log_event_id, pk, event_id, data_values from redcap_data where project_id = '$project_id' and field_name = '$record_id_field'";
    }
    
  //  error_log("GetCaseForms sql: " . $sql, 0); 
 	
   $result = mysqli_query($conn, $sql);

   $pendingTransmissions = array();

   while ($row = mysqli_fetch_assoc($result)) {  
    $dataValue = $row['data_values'];
    $record_id= $row['pk'];
    $log_event_id= $row['log_event_id'];
    $event_id= $row['event_id'];
    if (!empty($dataValue)) {
      $dataValues = extractDataValues($dataValue);
    }
    $transmission = new StdClass();
    $transmission->record_id = $record_id;
    $transmission->log_event_id = $log_event_id;
    $transmission->event_id = $event_id;
    $transmission->dataValues = $dataValues;
    array_push($pendingTransmissions, $transmission);
   }

  //  error_log("Result of pendingTransmissions: Size: " . count($pendingTransmissions) . " Data: " . print_r($pendingTransmissions, TRUE));
  //  $pendingTransmissions = "\xB1\x31";
  //  $pendingTransmissions = " Temp 38.9ºc";

  // uncomment to test a serialized file
    // $file = file_get_contents("pending_india.txt");
    // $converted = mb_convert_encoding($file, 'UTF-8', 'auto');
    // $converted = mb_convert_encoding($file, 'latin1', 'UTF-8');
    // error_log("converted from file: " . $converted);
    // $pendingTransmissions =  unserialize($file);
    // error_log("pendingTransmissions from file: " . print_r($pendingTransmissions, TRUE));

    foreach ($pendingTransmissions as $transmission) {
      $record_id = $transmission->record_id;
      $dataValues = $transmission->dataValues;
      // error_log("dv encoding: $record_id dataValues: " . $dataValues, 0);
      // error_log("dv encoding: $record_id : " . mb_detect_encoding($dataValues), 0);
      $encodedTrans = json_encode($dataValues, JSON_UNESCAPED_UNICODE);
      $jsonError = getJsonErrorMessage(false);
      if ($jsonError != JSON_ERROR_NONE) {
        // error_log("ERROR with dv encoding: $record_id dataValues: " . print_r($dataValues, TRUE), 0);
        $convertedDvs = array();
        foreach ($dataValues as $dv) {
          $encodedDv = json_encode($dv, JSON_UNESCAPED_UNICODE);
          $jsonError = getJsonErrorMessage(false);
          if ($jsonError != JSON_ERROR_NONE) {
            error_log("ERROR with dv encoding: $record_id dv: $dv ", 0);
            $convertedDv = mb_convert_encoding($dv, "UTF-8", "UTF-8");
            error_log("ERROR with dv encoding: $record_id dv: $dv ; converted to UTF-8: $convertedDv", 0);
            array_push($convertedDvs, $convertedDv);
          } else {
            array_push($convertedDvs, $dv);
          }
        }
        $transmission->dataValues = $convertedDvs;
      }

    }
   $encodedPendingTransmissions = json_encode($pendingTransmissions, JSON_UNESCAPED_UNICODE);
   // uncomment for dumping data to files.
  //  $jsonError = getJsonErrorMessage();
  //  if ($jsonError != JSON_ERROR_NONE) {
  //     $error = "Error: " . $jsonError;
  //     error_log("Error in pendingTransmissions: " . $error, 0);
  //     $serialized_pending = serialize($pendingTransmissions);
  //     $file = 'pending.txt';
  //     file_put_contents($file, $serialized_pending);
  //     $converted = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
  //     $serialized_converted = serialize($converted);
  //     $file = 'converted.txt';
  //     file_put_contents($file, $serialized_converted);
  //     error_log("converted in pendingTransmissions.", 0);
  //     $encodedPendingTransmissions = json_encode($converted);
  //     $jsonError = getJsonErrorMessage();
  //     echo $encodedPendingTransmissions;
  //  }
    // $serialized_pending = serialize($encodedPendingTransmissions);
    // $file = 'pending.txt';
    // file_put_contents($file, $serialized_pending);
    echo $encodedPendingTransmissions;
}

function getJsonErrorMessage($showError) {
    $jsonError = json_last_error();
    $error = "";
    switch ($jsonError) {
    case JSON_ERROR_NONE:
        // echo ' - No errors';
    break;
    case JSON_ERROR_DEPTH:
      $error =  ' - Maximum stack depth exceeded';
    break;
    case JSON_ERROR_STATE_MISMATCH:
      $error = ' - Underflow or the modes mismatch';
    break;
    case JSON_ERROR_CTRL_CHAR:
      $error = ' - Unexpected control character found';
    break;
    case JSON_ERROR_SYNTAX:
      $error = ' - Syntax error, malformed JSON';
    break;
    case JSON_ERROR_UTF8:
      $error = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
    break;
    default:
      $error = ' - Unknown error';
    break;
    }
    if (!IS_NULL($showError) && $jsonError != JSON_ERROR_NONE) {
        error_log("jsonError: " . $jsonError . "" . $error, 0);
    }
  // echo $error;
  return $jsonError;
}


function TransmitForm($record_id, $project_id, $forms, $event_id, $logEvents, $fieldList, $checkboxesOnly) {
    
  // This function transmits all data for a specific record
  // This may be "ALL" the data, or only data that have changed since the last transmission
  // $fieldList - only upload fields in $fieldList.
    global $conn;
    global $Proj;
    $api_token = "";
    $status = "SUCCESS";

    // Get transmission settings
    // This could/should be factored out into its own function

    $settings = GetTransmissionSettings();

    $project_api_mapping_string = $settings['transmission_remote_token'];
    $project_api_mapping_array = explode(';', $project_api_mapping_string);

    foreach ($project_api_mapping_array as $value) {
      $api_token_pair = explode(":", $value);
      if ($api_token_pair[0] == $project_id) {
        $api_token = $api_token_pair[1];
        break;
      }
    }
    
    // Log error if token is not found
    // Functions that begin with REDCap:: are provided for general use (check REDCap documentation)
    error_log("TransmitForm api_token $api_token", 0);

    if ($api_token == "") {
      error_log("No api_token $api_token", 0);
      echo "ERROR: Cannot find remote API token in configuration settings.";
      REDCap::logEvent($action_description = "Data Transmission", $changes_made = "Cannot find remote API token in configuration settings.");
      return;
    }

    $api_url  = $settings['transmission_remote_url'];
    $api_phi  = $settings['transmission_phi'];

    // Data transmission

    $event_list = []; $form_field_mapping = [];   $field_names = []; $event_forms = []; $results = ""; $form_events = [];
    $form = ""; $event = "";

    // Get list of event / form mappings for existing data
    // TODO: the form/formField parts could be be a global singleton populated at startup
    // This query gets forms, fields, events, and phi
    // If $api_phi is set to 1, filter out records flagged as phi (Identifiers) so that they are not transmitted to the server.
    // Arrays: 
    //    $event_forms = a list of forms
    //    $event_list = an array where the key is the event and the value is an $event_forms array, i.e. this links forms to events
    //    $field_names = a list of fields
    //    $form_field_mapping = an array where the key is the form and the value is an array of field names, similar to above - links fields to forms

    // $forms
    // error_log( "forms: "  . print_r($forms[0], TRUE) );
    $formFieldsProj = $Proj->forms[$forms[0]]['fields'];
    // error_log( "formFieldsProj: "  . print_r($formFieldsProj, TRUE) );

    $metadata = array();
    foreach ($formFieldsProj as $this_field=>$this_label) {
      $metadata[] = $Proj->metadata[$this_field];
    }
    if ($checkboxesOnly == true) {
      error_log("checkboxesOnly are set.");
    }

    foreach ($metadata as $row) {
      // error_log( "field_name: "  . $row['field_name'] . " field_phi: "  . $row['field_phi'] );
      $phi = $row['field_phi'];
      $this_field_type = $row['element_type'];
      // error_log("this_field_type: $this_field_type,this_field: " . $this_field . " form_name: " . $form);
      $ignoreField = false;

      if ($api_phi === '1') {
        if ($phi === '1') {
          $ignoreField = true;
        }
      }
      $field_name = $row['field_name'];
      if ($ignoreField === false) {
        if ($row['event_id'] != $event) {
          if ($event != "") {
            $event_list[$event] = $event_forms;
            $event_forms = [];
          }
          $event = $row['event_id'];
        }
        if (!in_array($row['form_name'], $event_forms)) {
            array_push($event_forms, $row['form_name']);
        }
        if ($row['form_name'] != $form) {
  
          if ($form != "") {
            $form_field_mapping[$form] = $field_names;
            $field_names = []; 
          }
          $form = $row['form_name'];
        }
        if ($checkboxesOnly == true) {
          if ($this_field_type == 'checkbox') {
            // error_log("cb: " . $this_field . " form_name: " . $form);
            array_push($field_names, $field_name);
          }
        } else {
          // error_log("NOT cb: " . $this_field . " form_name: " . $form);
          array_push($field_names, $field_name);
        }
      } else {
        error_log( $field_name. " has phi and the server is set not to upload phi.", 0);
      }
    }
    $event_list[$event] = $event_forms;
    $form_field_mapping[$form] = $field_names;

    // Post form field combinations

    // This code builds an array of fields that will be retrieved from the local database for transmission
    // This works by first getting the list of events and associated forms, and then for each form, associated fields
    // Then the actual data pull gets data for all the fields collected for the form and then transmits it.

    $combined_field_list = [];
    // Loop through each form
    foreach ($forms as $index=>$form) {
      // $combined_field_list = array_merge($combined_field_list, $form_field_mapping[$form]); 
      $form_fields = $form_field_mapping[$form];
      $record_id_field = REDCap::getRecordIdField();

      if (!in_array($record_id_field, $form_fields)) {
        array_push($form_fields, $record_id_field);
      }

    // The first line pulls the data from the local database
    // The second posts the data to the external API

    // make sure the items in the $fieldList don't have any phi - verify using form_fields
    // also fox checkbox keys
    $fieldsToUpload = [];
    $phiSafeFieldsToUpload = [];
    $fieldsToRemove = [];
    if (isset($fieldList) && !empty($fieldList)) {
      error_log("fieldList is set.". $fieldList . " ; the fieldList array: " . print_r($fieldList, TRUE));
      $fieldsToUpload = $fieldList;
      //Loop through the project fields and identify the checkboxes.
      // foreach (array_keys($Proj->metadata) as $this_field) {
      foreach ($form_fields as $this_field) {
        // if (in_array($Proj->metadata[$this_field]['element_type'], array('dropdown','select','radio','checkbox'))) {
        if (in_array($Proj->metadata[$this_field]['element_type'], array('checkbox'))) {
          error_log("checkbox: $this_field ", 0);
          // now check if one of the fields in fieldList closely matches this enum - usually has __1, __2, etc appended.
          foreach ($fieldList as $changedField) {
            if (strpos($changedField, $this_field) !== false) {
              error_log("$changedField is really $this_field");
              array_push($fieldsToUpload, $this_field);
              array_push($fieldsToRemove, $changedField);
            }
          }
        }
      }

      if (!in_array($record_id_field, $fieldsToUpload)) {
        array_push($fieldsToUpload, $record_id_field);
      }

      foreach ($fieldsToUpload as $field) {
        // error_log("Is it safe to upload " .$field);
        if (in_array($field, $form_fields)) {
          // error_log("Safe to upload " .$field);
          array_push($phiSafeFieldsToUpload, $field);
        } else {
          // error_log("Not phi safe or not in form_fields: " .$field);
        }
      }
    } else {
      error_log("using form_fields instead of fieldList");
      $phiSafeFieldsToUpload = $form_fields;
    }
    $fieldsToUploadNoDuplicates = array_diff(array_unique($phiSafeFieldsToUpload), $fieldsToRemove);
    error_log("About to transmit PostData for fieldsToUploadNoDuplicates: " . print_r($fieldsToUploadNoDuplicates, TRUE), 0);
    
    // Better to use $field_names because $fieldsToUploadNoDuplicates has the identifier field added. 
    if (sizeof($field_names) > 0) {
      $information = " [Attempting to upload Record id: " . $record_id . ". Event id: " . $event_id . " form: " . $form . ".]";
      if ($checkboxesOnly == true) {
        $data = REDCap::getData('json', "$record_id", $fields = $fieldsToUploadNoDuplicates, $events = $event_id);
        // $dataArray = explode(',', $data);
        // $slicedArray = array_slice($dataArray, 2, sizeof($dataArray));
        error_log("data:  " . print_r($data, TRUE), 0);
        $hasData = false;
        $jsonArr = json_decode($data);
        foreach($jsonArr[0] as $key => $value) { 
          // error_log("data pair: $key : $value ", 0);
          if ($key == 'dy1_scrn_scrnid' || $key == 'redcap_event_name') {
            // skip
          } else {
            if ($value !== "") {
              $hasData = true;
              error_log("data pair WITH VALUE: $key : $value ", 0);
            }
          }
        }
        if ($hasData == true) {
          error_log("Sending checkboxes data to server for " . $record_id . ". Event id: " . $event_id . " form: " . $form, 0);
          $data = REDCap::getData('csv', "$record_id", $fields = $fieldsToUploadNoDuplicates, $events = $event_id);
          $results = PostData($data, $api_url, $api_token);
        }
        // error_log("slicedArray:  " . print_r($slicedArray, TRUE), 0);
        // $results = PostData($data, $api_url, $api_token);
      } else {
        $data = REDCap::getData('csv', "$record_id", $fields = $fieldsToUploadNoDuplicates, $events = $event_id);
        $results = PostData($data, $api_url, $api_token);
      }

      sleep(2);

      // error_log( "logEvents: " . print_r($logEvents, TRUE) );
      // REDCap::logEvent($action_description = "Data Transmission", $changes_made = $data . $information);
  
      // This section checks for errors, and if they occur, records them in the REDCap log
      // Check the log if data transmission issues should occur.
  
      error_log("results: $results", 0);
  
      if (strpos($results, 'ERROR') !== false) {
          $information = " [Successfullly uploaded Record id: " . $record_id . ". Event id: " . $event_id . " form: " . $form . ".]";
          REDCap::logEvent($action_description = "Data Transmission", $changes_made = $results . $information);
          $status = $results;
      } else {
        // update the transmit date for this log item.
        foreach ($logEvents as $index=>$logEventId) {
          updateLogEventTransmitDate($logEventId);
        }
        if ($checkboxesOnly == true && $hasData == false) {
          $status = " [INFO: No checkboxes to upload for Record id: " . $record_id . ". Event id: " . $event_id . " form: " . $form . ".]";
        } else {
          $status = $status . ": [INFO: data uploaded: ".print_r($data, TRUE) . "]";
        }
      }
    } else {
      $status = " [INFO: Nothing to upload for Record id: " . $record_id . ". Event id: " . $event_id . " form: " . $form . ".]";
    }
  } // end foreach loop for form processing
    error_log("Status: $status", 0);
    // echo $status;
}

function SendPendingTransmission($project_id, $record) {
  global $conn;
  global $Proj;
  $api_token = "";
  $status = "SUCCESS";

  // Get transmission settings
  // This could/should be factored out into its own function

  $settings = GetTransmissionSettings();

  $project_api_mapping_string = $settings['transmission_remote_token'];
  $project_api_mapping_array = explode(';', $project_api_mapping_string);

  foreach ($project_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $api_token = $api_token_pair[1];
      break;
    }
  }

  if ($api_token == "") {
    error_log("No api_token $api_token", 0);
    echo "ERROR: Cannot find remote API token in configuration settings.";
    REDCap::logEvent($action_description = "Data Transmission", $changes_made = "Cannot find remote API token in configuration settings.");
    return;
  }

  $api_url  = $settings['transmission_remote_url'];
  $api_phi  = $settings['transmission_phi'];

  $record_id = $record["record_id"];
  $event_id = $record["event_id"];
  $fields = $record["dataValues"];

  // Make sure that dy1_scrn_scrnid is in the list of fields
  $hasIdentifier = false;
  foreach ($fields as $field) {
    if ($field === "dy1_scrn_scrnid") {
      $hasIdentifier = true;
    }
  }
  if (!$hasIdentifier) {
    error_log("adding dy1_scrn_scrnid", 0);
    array_push($fields, 'dy1_scrn_scrnid');
  }

  // error_log( 'dataValues: ' . print_r($dataValues, TRUE));
  $uniqueEventNames = $Proj->getUniqueEventNames();
  $eventName = $uniqueEventNames[$event_id];

  $recordEvents = array();
  array_push($recordEvents, $eventName);

  // Pull the data from the local database
  // $data = REDCap::getData('csv', "$record_id", $fields = $dataValues, $events = $recordEvents);
  $data = REDCap::getData('csv', $record_id, $fields, $recordEvents);

  $xArray = implode(' ',$fields);
  error_log("fields: " . $xArray, 0);
  error_log("About to PostData for eventName: $eventName and data: " . $data , 0);

  // Post the data to the external API
  $results = PostData($data, $api_url, $api_token);
  if (startsWith($results, "ERROR")) {
    $status = "ERROR";
  } else {
      $logEventId =  $record["log_event_id"]; 
      updateLogEventTransmitDate($logEventId);
  }

  error_log("Status of SendPendingTransmission: URL: $api_url Status:  $status results $results" , 0);

  echo $results;
}

function updateLogEventTransmitDate($log_event_id)
{
  $useNOW = true;
  $ts 	 	= ($useNOW ? str_replace(array("-",":"," "), array("","",""), NOW) : date('YmdHis'));
  // Update table
  $sql = "update redcap_log_event set transmit_date = '$ts' where log_event_id = " . $log_event_id;
  error_log( "ts: " . $ts . " sql:" . $sql );
  db_query($sql);
}

function PostData($data, $url, $api_key) {

    // There are several variations on this function
    // All essentially do an API submission using CURL

        $fields = array(
        'token'   => $api_key,
        'content' => 'record',
        'format'  => 'csv',
        'returnFormat'  => 'json',
        'type'    => 'flat',
        'overwriteBehavior' => 'overwrite',
        'data'    => $data
      );

      $xArray = implode(' ',$fields);
      error_log("PostData: Fields being posted to url $url fields:  $xArray ", 0);
      // error_log("Fields being posted to url $url ", 0);

      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
      curl_setopt($ch, CURLOPT_VERBOSE, 1);
      $verbose = fopen('log-verbose.txt', 'w+');
      curl_setopt($ch, CURLOPT_STDERR, $verbose);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_AUTOREFERER, true);
      curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
      // must set CURLOPT_FAILONERROR to false so we see any errors 
      curl_setopt($ch, CURLOPT_FAILONERROR, false);

      $output = curl_exec($ch);
      $jsonResponse = json_decode($output);

      rewind($verbose); // position = 0
      $verboseLog = stream_get_contents($verbose);
      LogResult(date('Y-m-d H:i:s') . ": result of exporting data:" . $verboseLog);
      if (isset($jsonResponse->error)) {
        error_log("ERROR: " . $jsonResponse->error);
        LogResult(date('Y-m-d H:i:s') . ": ERROR: " . $jsonResponse->error);
        return "ERROR: " . $jsonResponse->error;
      } else {
        return $output;
      }
      curl_close($ch);
}

function GetTransmissionSettings() {

  // Gets transmission settings from redcap_config table

  global $conn;
  $settings = [];
   $sql = "select field_name, value from redcap_config where field_name like 'transmission_%'";
  $result = mysqli_query($conn, $sql);

  while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['field_name']] = $row['value'];
  }
  return $settings;
}

// You are now heading into less tested terrority

function UpdateStudy2($project_id) {

  // This function was for debugging only

  // This function updates the local study structure, based on what's on the server
  // Note that there are some minor API code updates made to the REDCap core to accommodate everything
  // These are noted at the appropriate places below

	global $conn;

	 // Get transmission settings

  $settings = GetTransmissionSettings();

  $project_api_mapping_string = $settings['transmission_remote_token'];
  $project_api_mapping_array = explode(';', $project_api_mapping_string);

  foreach ($project_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $api_token = $api_token_pair[1];
      break;
    }
  }

  $local_api_mapping_string = $settings['transmission_local_token'];
  $local_api_mapping_array = explode(';', $local_api_mapping_string);

  foreach ($local_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $local_api_token = $api_token_pair[1];
      break;
    }
  }

  $project_url = $settings['transmission_remote_url'];
  $local_url = $settings['transmission_local_url'];

  // Local log for debugging

	$file = 'log.txt';
	$current = file_get_contents($file);
	$current .= "Updating repeated events" . "\n";
	file_put_contents($file, $current);

 // 'repeatingEvents' is custom code added to the server
 // This is located in the redcap_v..../API folder
 // Note that the index.php file was also updated

 // This is because the current version of REDCap does not have an API call to retrieve repeated events
 // which are needed for SAEs, protocol deviations, etc.

	$output = ExportProjectData($api_token, $project_url, 'repeatingEvents');
  	$output_array = json_decode($output, true);

  	$sql = "";
  	foreach ($output_array as $row) {
	    $arm = $row['arm_name'];
	    $form_name = $row['descrip'];
	    $sql .= "SET @event_id = (select redcap_events_metadata.event_id from redcap_events_arms inner join redcap_events_metadata on redcap_events_arms.arm_id = redcap_events_metadata.arm_id where project_id='$project_id' and arm_name='$arm' and descrip='$form_name'); replace into redcap_events_repeat (event_id) values (@event_id);";
	   

		$file = 'log.txt';
		$current = file_get_contents($file);
		$current .= $sql . "\n";
		file_put_contents($file, $current);

	}

	 $result = mysqli_multi_query($conn, $sql);

}

function UpdateStudy($project_id) {

  // This function updates the local study structure, based on what's on the server
  // Note that there are some minor API code updates made to the REDCap core to accommodate everything
  // These are noted at the appropriate places below

  global $conn; $REDCAP_PROXY_HOST; $REDCAP_PROXY_PORT;

  LogResult("Updating study.");

 // Get transmission settings

  $settings = GetTransmissionSettings();

  $project_api_mapping_string = $settings['transmission_remote_token'];
  $project_api_mapping_array = explode(';', $project_api_mapping_string);

  foreach ($project_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $api_token = $api_token_pair[1];
      break;
    }
  }

  $local_api_mapping_string = $settings['transmission_local_token'];
  $local_api_mapping_array = explode(';', $local_api_mapping_string);

  foreach ($local_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $local_api_token = $api_token_pair[1];
      break;
    }
  }

  $project_url = $settings['transmission_remote_url'];
  $local_url = $settings['transmission_local_url'];

  // Get study status

  $sql = "select status from redcap_projects where project_id = '$project_id';";
  $results = mysqli_query($conn, $sql);


  // Should only be one row
  while ($row = mysqli_fetch_assoc($results)) {  
	  $project_status = $row['status'];
	  LogResult("Status for $project_id: " . $project_status);
   }


  // If not draft, set status to draft
  if ($status != '0') {
  	$sql = "update redcap_projects set status='0' where project_id = '$project_id';";
  	if (mysqli_query($conn, $sql)) {
      echo "Record updated successfully"; // Log error later
   } else {
      echo "Error updating record: " . mysqli_error($conn);
   }
  }
  
  // Update general project information

  $output = ExportProjectData($api_token, $project_url, 'project');
  LogResult("Result of updating general project information.: " . $output);

  $output_array = json_decode($output, true);
  unset($output_array['project_id']);
  $output_json = json_encode($output_array);

  $input = ImportProjectData($local_api_token, $local_url, 'project_settings', $output_json);

  // Update arms

  $output = ExportProjectData($api_token, $project_url, 'arm');
  $input = ImportProjectDataWithAction($local_api_token, $local_url, 'arm', $output);

  // Update events

  $output = ExportProjectData($api_token, $project_url, 'event');
  $input = ImportProjectDataWithAction($local_api_token, $local_url, 'event', $output);

  // Update data dictionary

  $output = ExportProjectData($api_token, $project_url, 'metadata');
  $input = ImportProjectData($local_api_token, $local_url, 'metadata', $output);

  LogResult("Result of updating data dict.: " . $input);

  // Update form/event mapping

  $output = ExportProjectData($api_token, $project_url, 'formEventMapping');
  $input = ImportProjectData($local_api_token, $local_url, 'formEventMapping', $output);

  // Fix form names

  $output = ExportProjectData($api_token, $project_url, 'instrument');
  $output_array = json_decode($output, true);

  foreach ($output_array as $form_array) {
    $form_name = $form_array['instrument_name'];
    $form_label = $form_array['instrument_label'];
    $sql = "update redcap_metadata set form_menu_description='$form_label' where project_id='$project_id' and form_name = '$form_name'";
    $result = mysqli_query($conn, $sql);
  }

  // Kevin's API

  $output = ExportProjectData($api_token, $project_url, 'repeatingEvents');
  $output_array = json_decode($output, true);

  // Remove extraneous event, fix repeated forms here, and set study status
  
   $sql = "SET @event_id = (select event_id from redcap_events_arms inner join redcap_events_metadata on redcap_events_arms.arm_id = redcap_events_metadata.arm_id where project_id='$project_id' and descrip = 'Event 1'); delete from redcap_events_metadata where event_id=@event_id;"; 

   $sql .= "update redcap_projects set status='$project_status' where project_id = '$project_id';";
  	
  	foreach ($output_array as $row) {
	    $arm = $row['arm_name'];
	    $form_name = $row['descrip'];
	    $sql .= "SET @event_id = (select redcap_events_metadata.event_id from redcap_events_arms inner join redcap_events_metadata on redcap_events_arms.arm_id = redcap_events_metadata.arm_id where project_id='$project_id' and arm_name='$arm' and descrip='$form_name'); replace into redcap_events_repeat (event_id) values (@event_id);";

	}

	$result = mysqli_multi_query($conn, $sql);
 
}

function ExportProjectData($api_token, $project_url, $project_action) {

  global $REDCAP_PROXY_HOST, $REDCAP_PROXY_PORT;

  // Gets project data from location specified by $api_token

  $data = array('token' => $api_token, 'content' => $project_action, 'format' => 'json', 'returnFormat' => 'json');
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $project_url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
  error_log("REDCAP_PROXY_HOST: ". $REDCAP_PROXY_HOST);
  if (isset($REDCAP_PROXY_HOST)) {
    error_log("Using proxy server for ExportProjectData". $REDCAP_PROXY_HOST);
    curl_setopt($ch, CURLOPT_PROXY, "$REDCAP_PROXY_HOST:$REDCAP_PROXY_PORT");
  }

  $output = curl_exec($ch);
  curl_close($ch);

  return $output;
}

function ImportProjectData($api_token, $project_url, $project_action, $data) {

  // Imports data locally using API

  $data = array('token' => $api_token, 'content' => $project_action, 'format' => 'json', 'data' => $data);
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $project_url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
  if (isset($REDCAP_PROXY_HOST)) {
    error_log("Using proxy server for ImportProjectData". $REDCAP_PROXY_HOST);
    curl_setopt($ch, CURLOPT_PROXY, "$REDCAP_PROXY_HOST:$REDCAP_PROXY_PORT");
  }
  $output = curl_exec($ch);
  curl_close($ch);

  return $output;
}

function ImportProjectDataWithAction($api_token, $project_url, $project_action, $data) {

  global $REDCAP_PROXY_HOST, $REDCAP_PROXY_PORT;

  // Imports data locally using API

  $data = array('token' => $api_token, 'content' => $project_action, 'format' => 'json', 'data' => $data, 'action' => 'import');
  
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $project_url);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
  if (isset($REDCAP_PROXY_HOST)) {
    error_log("Using proxy server". $REDCAP_PROXY_HOST);
    curl_setopt($ch, CURLOPT_PROXY, "$REDCAP_PROXY_HOST:$REDCAP_PROXY_PORT");
  }
  $output = curl_exec($ch);
  curl_close($ch);

  return $output;
}

function LogResult($text) {
  //$file = '../../../../edocs/log.txt';
  $file = 'log.txt';
  $current = file_get_contents($file);
  $current .= $text . "\n";
  file_put_contents($file, $current);
  return;
}

function ShowTransmissionLog($project_id, $begin_limit) {

  include_once APP_PATH_DOCROOT . "Logging/logging_functions.php";

  $formFieldToFormArray = populateFormFieldToFormArray($project_id);
  // error_log( 'ShowTransmissionLog projectToForm: ' . print_r($projectToForm, TRUE));
  // error_log( 'ShowTransmissionLog begin_limit: ' . $begin_limit);
  if (empty($begin_limit)) {
    // error_log( 'ShowTransmissionLog  empty begin_limit: ' . $begin_limit);
    $begin_limit = 0;
  }
  $offset = 20;
	$SQL_STRING = "SELECT * FROM redcap_log_event force index for order by (PRIMARY) WHERE project_id = $project_id and (description like '%Update record%' or description like '%Create record%')";
  $SQL_STRING .= " ORDER BY log_event_id DESC LIMIT $offset OFFSET $begin_limit";
  // $SQL_STRING .= " ORDER BY log_event_id DESC ";
  $QSQL_STRING = db_query($SQL_STRING);

  $logResults = array();
  $remoteRecordIds = array(); // We will query the remote server with this list of record id's.
  $events = array(); // We will query the remote server with this list of events. It will actually be an array with only one event.

  $tableRows = "";
  while ($row = db_fetch_assoc($QSQL_STRING))
	{
    $dv = $row['data_values'];
    $logEventId = $row['log_event_id'];
    $data = str_replace(',','<br/>',$dv);
    $ts = DateTimeRC::format_ts_from_ymd(DateTimeRC::format_ts_from_int_to_ymd($row['ts']));
    $dataValues = extractDataValues($dv);
    $fieldName; $completeField = null;
    $dataValuesSize = sizeof($dataValues);
    foreach ($dataValues as $index=>$currentFieldName) {
      // need to test for dy1_scrn_scrnid, which is used in multiple forms. We don't want to use this fieldName to calculate the formName.
      if ($dataValuesSize === 1) {
        // error_log("dataValuesSize: $dataValuesSize", 0);
        $fieldName = $currentFieldName;
      } else {
        if ($currentFieldName !== 'dy1_scrn_scrnid') {
          $fieldName = $currentFieldName;
        } else {
          // error_log("dataValuesSize: $dataValuesSize not using dy1_scrn_scrnid currentFieldName:  $currentFieldName", 0);
        }
      }
      if (strpos($currentFieldName, '_complete') !== false) {
        $completeField = $currentFieldName;
      }
    }

    if (!is_null($completeField)) {
      $formName = $formFieldToFormArray[$completeField];
      // error_log("Using completeField as formName $formName for log_event_id: $logEventId for completeField: $completeField and fieldName: $fieldName and dv: $dv", 0);
    } else {
      $formName = $formFieldToFormArray[$fieldName];
      error_log("Using fieldName to calculate formName $formName for log_event_id: $logEventId for fieldName: $fieldName", 0);
    }
  
    // error_log("$logResult->pk : fieldName: $fieldName as form $formName");

    $logResult = new stdClass();
    $logResult->formName = $formName;
    // error_log("formName:  $logResult->formName ", 0);
    $logResult->fieldName = $fieldName;
    $logResult->formName = $formName;
    $logResult->data_values = $$row['data_values'];
    $logResult->log_event_id = $logEventId;
    $logResult->ts = $ts;
    $logResult->event_id = $row['event_id'];
    $logResult->pk = $row['pk'];
    $logResult->description = $row['description'];
    $logResult->transmit_date = $row['transmit_date'];
    $logResult->data = $data;
    array_push($logResults, $logResult);
    // also populate $remoteRecordIds
    array_push($remoteRecordIds , $row['pk']);
    // also populate formNames
    $formNames = array(); // We will query the remote server with this list of formNames.
    array_push($formNames , $formName);
    // array_push($events , $logResult->event_id);

    $recordId = $logResult->pk;
    $eventId = $logResult->event_id;
    if (empty($logResult->transmit_date)) {
      $uploaded = NULL;
    } else {
      $uploaded = "Confirmed Uploaded to Server.";
    }

    if (!empty($logResult->transmit_date) && $uploaded == NULL) {
      $uploaded = "<p style='color:red'>ERROR: Not on Server</p>";
    }

     // Retrieve the records and forms from the remote server.
    $remoteRecords = RetrieveRecordFormList($project_id, $recordId, $eventId, $formNames, false, true);

    $uploaded = NULL;

    $submitted = $remoteRecords->submitted;
    foreach ($submitted as $result) {
      $remoteFormName = $result->formName;
      if ($remoteFormName === $formName) {
        if (empty($logResult->transmit_date)) {
          $uploaded = NULL;
        } else {
          $uploaded = "Confirmed Uploaded to Server.";
        }
      }
    }
    if (!empty($logResult->transmit_date) && $uploaded == NULL) {
      $uploaded = "<p style='color:red'>ERROR: Not on Server</p>";
    }
    error_log("Log ID $logResult->log_event_id For $logResult->pk eventId: $eventId remoteForm:  $remoteFormName local $logResult->formName uploaded: $uploaded", 0);


		// Render logResult values
    $tableRows =  $tableRows . "<tr>
					<td class='logt' style='width:150px;'>
						{$logResult->log_event_id}
					</td>
					<td class='logt' style='width:90px;'>
            {$logResult->ts}
					</td>
					<td class='logt' style='width:90px;'>
            {$logResult->pk}
					</td>
					<td class='logt' style='width:90px;'>
						{$logResult->formName} 
					</td>
					<td class='logt' style='width:120px;'>
						$logResult->description
					</td>
					<td class='logt' style='text-align:left;'>
            $logResult->transmit_date
            <br/>$uploaded 
            <div style='margin-left:2em;text-align:left;margin:10px 0;margin-bottom: 20px;'>
            <button class='btn btn-primary' id='force-upload-btn' name='force-upload-btn' onclick='ForceUpload(\"$logResult->pk\",\"$project_id\",\"$logResult->formName\",\"$logResult->event_id\", \"$logResult->log_event_id\")' style='margin-bottom: 2px; padding: 6px 8px; font-size: 13px;' tabindex='32'>Force Upload</button>
            <img id='connection_status' src=''></img>
            <span id='force-upload-status-$logResult->log_event_id'></span>
            </div>

          </td>
          <td class='logt' style='text-align:left;'>
						{$logResult->data}
          </td>
          </tr>
          ";          
  }

  // $xArray = implode(' ',$remoteRecords);
  // error_log("remoteRecords array:  $xArray ", 0);

  // Query for the dropdown number of pages
  $SQL_DROPDOWN_STRING = "SELECT count(1) FROM redcap_log_event force index for order by (PRIMARY) WHERE project_id = $project_id and (description like '%Update record%' or description like '%Create record%')";
  $SQL_DROPDOWN_STRING .= " ORDER BY log_event_id DESC";

  $num_total_files = db_result(db_query($SQL_DROPDOWN_STRING),0);
  $num_pages = ceil($num_total_files/$offset);
  // error_log("begin_limit: $begin_limit offset: $offset num_total_files: $num_total_files num_pages: $num_pages", 0);
  // error_log("SQL_DROPDOWN_STRING: $SQL_DROPDOWN_STRING", 0);

	// Display table
	$output =  "<div style='max-width:700px;'><h4>Transmission Log</h4>
  <table class='form_border' width=100% id='transmission_log_table'>
  <tr>
  <td colspan = '4'>
  Displaying items (by most recent): <select id='logs' class='x-form-text x-form-field' style='margin-bottom:2px;font-size:13px;height:25px;' onchange='showNext()'>
  <option value='' selected=''> select </option>";
  
  //Loop to create options for "Displaying files" dropdown
  
  for ($i = 1; $i <= $num_pages; $i++)
  {
    $end_num = $i * $offset;
    $begin_num = $end_num - ($offset-1);
    $value_num = $end_num - $offset;

    // error_log("num_total_files: $num_total_files value_num: $value_num end_num: $end_num offset: $offset", 0);

    if ($end_num > $num_total_files) $end_num = $num_total_files;
    $output .= "<option value='$value_num'" . (($begin_limit == $value_num) ? " selected " : "") . ">$begin_num - $end_num</option>";
  }
  $output .="</select>";

  $output .="
  </td>
  <td colspan='3'></td>
  </tr>
  <tr>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:150px;'>Log ID</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:150px;'>Timestamp</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:90px;'>Record ID</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:90px;'>Form Name</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;width:120px;'>Data</td>
		<td class='header' style='text-align:center;padding:2px 4px 2px 4px;'>Transmission Date</td>
    <td class='header' style='text-align:center;padding:2px 4px 2px 4px;'>Data</td></tr>";
    
  $output .=$tableRows;


  $output = $output . "</table></div>";

  // error_log("output:" .$output ,0);           
  echo $output;
  return $output;
}



/**
 * Fetches form name when given a field:
 * Usage
 * $answer = $formFieldToFormArray["dy1_ban_anmls"];
 **/        
function populateFormFieldToFormArray($project_id)
{
  global $conn; 
  $sql = "select distinct redcap_metadata.form_name, redcap_metadata.field_name, redcap_data.project_id
  from redcap_metadata
  inner join redcap_data on redcap_metadata.form_name = left(redcap_data.field_name, instr(redcap_data.field_name, '_complete')-1) and redcap_metadata.project_id = redcap_data.project_id
  where redcap_data.project_id='$project_id'
  order by project_id, form_name, field_name";
  $result = mysqli_query($conn, $sql);
  $formName;
  $formFieldToFormArray = [];
  while ($row = mysqli_fetch_assoc($result)) {  
    $fieldName = $row['field_name'];
    $currentFormName = $row['form_name'];
    if ($formName === NULL || $formName !==  $currentFormName) {
      $formName = $currentFormName;
      // error_log('formName: ' . $formName . ', fieldName: ' . $fieldName,0 );
    }
      // error_log('formName: ' . $formName . ' fieldName: ' . $fieldName).
      $formFieldToFormArray[$fieldName] = $formName;
    // $formFieldToFormArray[$formName] = $fieldName;
      // error_log( 'populateProjectToForm formFieldToFormArray: ' . print_r($formFieldToFormArray, TRUE));
      // $formFieldToFormArray[] = $row;
  }
  return $formFieldToFormArray;
}
/**
 * Populate an array when extracting from the data_values field from redcap_log_event
 */
function extractDataValues($dataValue) {
  $dataValues = [];
  $arr = explode(",", $dataValue);
  foreach ($arr as $index=>$updateString) {
    $updateStringArray = explode(" = ", $updateString);
    $fieldName = $updateStringArray[0];
    if (!empty($fieldName)) {
      $pattern = '/\(\d*\)/';
      $removedSpecialStringFieldName = preg_replace($pattern,"",$fieldName);
      $cleanedFieldName = filter_var($removedSpecialStringFieldName, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
      // error_log("cleanedFieldName: ". $cleanedFieldName);
      if (!empty($cleanedFieldName)) {
        $dataValues[] = $cleanedFieldName;
      }
    }
  }
  return $dataValues;
}

// kudos: https://stackoverflow.com/a/834355/6726094
function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}


/**
 * DownloadRemoteRecords
 *
 * @param  mixed $project_id
 * @param  mixed $queryRemote
 * @param  mixed $formName
 *
 * @return void
 */
function DownloadRemoteRecords($project_id, $queryRemote, $formName, $eventName, $num) {

  global $Proj;

  error_log("project_id: $project_id; qr: $queryRemote; formName: $formName ");

  $settings = GetTransmissionSettings();

  $project_api_mapping_string = $settings['transmission_remote_token'];
  $project_api_mapping_array = explode(';', $project_api_mapping_string);

  foreach ($project_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $api_token = $api_token_pair[1];
      break;
    }
  }

  $local_api_mapping_string = $settings['transmission_local_token'];
  $local_api_mapping_array = explode(';', $local_api_mapping_string);

  foreach ($local_api_mapping_array as $value) {
    $api_token_pair = explode(":", $value);
    if ($api_token_pair[0] == $project_id) {
      $local_api_token = $api_token_pair[1];
      break;
    }
  }

  $record_id_field = REDCap::getRecordIdField();

  $project_url = $settings['transmission_remote_url'];
  $local_url = $settings['transmission_local_url'];

  // create a file pointer connected to the output stream
  $dirSep = DIRECTORY_SEPARATOR;
  if ($dirSep == '/') {
    $os = 'linux';
  }

  if (!file_exists('.' . $dirSep . 'reports')) {
    mkdir('.' . $dirSep . 'reports', 0777, true);
  }

  // TODO: make configurable from the UI
  $useCache = true;

  error_log("num: $num", 0);
  // clear the reports dir if num === 0
  if ($useCache === false && $num === '0') {
    error_log("Clearing the reports dir");
    $dirname = './reports/';
    if (is_dir($dirname))
      $dir_handle = opendir($dirname);
    while($file = readdir($dir_handle)) {
      if ($file != "." && $file != "..") {
        if (!is_dir($dirname."/".$file))
          unlink($dirname."/".$file);
      }
    }
    closedir($dir_handle);
  }

  $timestamp = date('Y-m-d-H-i-s');
  if ($queryRemote == 'true') {
    $filename = 'redcap-remote-'. $formName . '.csv';
    $rawData = 'cache-redcap-remote-'. $formName . '.json';
    $token = $api_token;
  } else {
    $filename = 'redcap-local-'. $formName . '.csv';
    $rawData = 'cache-redcap-local-'. $formName . '.json';
    $token = $local_api_token;
  }

  // Check if raw data file is already created.
  // $rawCacheFile = fopen('./reports/' . $rawData, 'r');
  $rawCacheFile = file_get_contents('./reports/' . $rawData);
  if ($rawCacheFile && $useCache) {
    $outputJson = json_decode($rawCacheFile,true);
    // $outputJson = $rawCacheFile;
    // fclose($rawCacheFile);
    error_log("Using cache; now generating Excel Spreadsheet: $outputJson", 0);
  } else {
    $forms = array($formName);
    $data = array(
      'token' => $token,
      'content' => 'record',
      'format' => 'json',
      'type' => 'eav',
      'forms' => $formName,
      'events' => $eventName, 
      'rawOrLabel' => 'raw',
      'rawOrLabelHeaders' => 'raw',
      'exportCheckboxLabel' => 'false',
      'exportSurveyFields' => 'false',
      'exportDataAccessGroups' => 'false',
      'returnFormat' => 'json'
    );
    // error_log("data config to server:  " . print_r($data, TRUE), 0);
    $ch = curl_init();
    if ($queryRemote == 'true') {
      curl_setopt($ch, CURLOPT_URL, $project_url);
    } else {
      curl_setopt($ch, CURLOPT_URL, $local_url);
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    // $verboseLog = stream_get_contents($verbose);
    // error_log("output www:  $output ", 0);
    // LogTransmissionResult($output);
    $outputJson = json_decode($response,true);
    // var_dump(json_decode($output));
    if (property_exists($outputJson, 'error')) {
      $error = $outputJson->error;
      error_log("ERROR: $error", 0);
      return (json_decode($outputJson));
    }
        // $rawDataFile = fopen('./reports/' . $rawData, 'w+');
    // file_put_contents('./reports/' . $rawData, json_encode($response, JSON_PRETTY_PRINT));
    file_put_contents('./reports/' . $rawData, $response);
    error_log("finished fetching remote data; now generating Excel Spreadsheets  ", 0);
  }

  $filePath = './reports/' . $filename;
  error_log("report will be at: $filePath", 0);
  $output = fopen($filePath, 'w');

  // // output the column headings
  fputcsv($output, array('RecordId', 'Event', 'Field', 'localValue', 'instance','id'));

  foreach ($outputJson as $row) {
    $fieldName = $row['field_name'];
    $value = $row['value'];
    $recordId = $row['record'];
    $redcap_event_name = $row['redcap_event_name'];
    $redcap_repeat_instance = $row['redcap_repeat_instance'];
    // WORKAROUND: if redcap_repeat_instance is empty, set as 1. 
    if (empty($redcap_repeat_instance)) {
      $redcap_repeat_instance = "1";
    }
    if ($Proj->isCheckbox($fieldName)) {
      error_log("checkbox: ". $fieldName + " value: " . $value);
      $id = $recordId . "_" . $redcap_event_name . "_" . $fieldName . "_" . $redcap_repeat_instance . "_" . $value;
    } else {
      $id = $recordId . "_" . $redcap_event_name . "_" . $fieldName . "_" . $redcap_repeat_instance;
    }

      // error_log("$record has event $redcap_event_name with $field_name: $value");
      fputcsv($output, array($recordId, $redcap_event_name, $fieldName, $value, $redcap_repeat_instance, $id));
  }

  fclose($output);
  // $contLength = ob_get_length();
  // header( 'Content-Length: '.$contLength);
  $dirSep = DIRECTORY_SEPARATOR;
  if ($dirSep == '/') {
    $os = 'linux';
  }

  $reportsPath = realpath(__DIR__ . $dirSep . 'reports');
  $downloadPath = $reportsPath . $dirSep . $filename;
  $wwwPath = $filePath;
  error_log("redcapPath: $reportsPath");
  if ($queryRemote == 'true') {
    $message = "<p>Remote CSV is available at <a href=\"$wwwPath\">$downloadPath</a></p>";
  } else {
    $message = "<p>Local CSV is available at <a href=\"$wwwPath\">$downloadPath</a></p>";
  }
  error_log("sleeping 2 seconds", 0);
  sleep(2); 
  // echo json_encode($message);
  echo $message;

}

/**
 * Sorts the list of files in the reports directory and compares the two most recent local and remote files.
 */
function CompareLocalRemoteValues($project_id) {

  global $Proj;

  // detect on Mac to correct PHP's detection of line endings
  ini_set('auto_detect_line_endings', TRUE);
  // Also, we need more memory.
  ini_set('memory_limit', '1500M');

  $localPrefix = 'redcap-local*';
  $remotePrefix = 'redcap-remote*';

  $filePath = './reports/';

  // Sort the files so that we can process the two most recent ones.
  $localFiles = glob($filePath . $localPrefix);
  $remoteFiles = glob($filePath . $remotePrefix);
  function sort_by_mtime($file1,$file2) {
    $time1 = filemtime($file1);
    $time2 = filemtime($file2);
    if ($time1 == $time2) {
        return 0;
    }
    return ($time1 < $time2) ? 1 : -1;
    }

  usort($localFiles,"sort_by_mtime");
  usort($remoteFiles,"sort_by_mtime");

  // error_log("remoteFiles: " . print_r($remoteFiles, TRUE));
  // error_log("remoteFiles[0]: " . $remoteFiles[0]);

  $localVaues; $remoteValues;

  // $formFieldToFormArray = populateFormFieldToFormArray($project_id);

  // Checking if localExists and remoteExists is only useful for the Manual mode Excel comparison.
  $localExists = file_exists($localFiles[0]);
  if (!$localExists) {
    header('HTTP/1.0 400 Bad error');
    exit("ERROR: Local excel file does not exist. Please click Download Local Excel");
  }

  $remoteExists = file_exists($remoteFiles[0]);
  if (!$remoteExists) {
    header('HTTP/1.0 400 Bad error');
    exit("ERROR: Remote excel file does not exist. Please click Download Remote Excel");
  }

  $localBaseName = basename($localFiles[0]);
  $baseNameEnd = ltrim($localBaseName, 'redcap-local'); 
  $baseName = rtrim($baseNameEnd, '.csv'); 

  $remoteRows = array_map('str_getcsv', file($remoteFiles[0]));
  // error_log("remoteRows: " . print_r($remoteRows, TRUE));

  $remoteHeader = array_shift($remoteRows);
  $remoteValues = array();
  foreach ($remoteRows as $row) {
    try {
      $remoteValues[] = array_combine($remoteHeader, $row);
    } catch (Exception $e) {
      error_log("error processing remoterows: ".$e->getMessage() );
      echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
  }
  error_log("populated remoteValues ");
  $remoteKVPs = array();
  foreach ($remoteValues as $row) {
    $recordId = $row["RecordId"];
    $eventId = $row["Event"];
    $fieldId = $row["Field"];
    $remoteValue = $row["localValue"];
    $instance = $row["instance"];

    // $rmtKey = $row["RecordId"] . "_" . $row["Event"] . "_" . $row["Field"] . "_" . $row["instance"];
    if ($Proj->isCheckbox($fieldId)) {
      error_log("checkbox: ". $fieldId);
      $rmtKey = $recordId . "_" . $eventId . "_" . $fieldId . "_" . $instance . "_" . $remoteValue;
    } else {
      $rmtKey = $recordId . "_" . $eventId . "_" . $fieldId . "_" . $instance;
    }

    // error_log("key: $key; value: $localValue");
    $remoteKVPs[$rmtKey] = $row["localValue"];
    // array_push($localKVPs, $localValue);
  }

  $hashFile = $filePath . "remoteHash-" . $baseName . ".json";
  file_put_contents($hashFile, json_encode($remoteKVPs, JSON_PRETTY_PRINT));

  // error_log("remoteKVPs: " . print_r($remoteKVPs, TRUE));

  // error_log("localFiles: " . print_r($localFiles, TRUE));
  // error_log("localFiles[0]: " . $localFiles[0]);

  // Now populate $localValues
  $rows = array_map('str_getcsv', file($localFiles[0]));
  $header = array_shift($rows);
  $localValues = array();
  foreach ($rows as $val) {
    $localValues[] = array_combine($header, $val);
  }

  $allValues = array();
  $unmatchedValues = array();

  // error_log("localKVPs: " . print_r($localKVPs, TRUE));
  $localKVPs = array();
  $localRecordIds = array();

  // lookup values from remoteKVPs and compare to localValues.
  foreach ($localValues as $row) {
    $recordId = $row["RecordId"];
    $eventId = $row["Event"];
    $fieldId = $row["Field"];
    $localValue = $row["localValue"];
    $instance = $row["instance"];

    array_push($localRecordIds, $recordId);

    // $localKey = $row["RecordId"] . "_" . $row["Event"] . "_" . $row["Field"] . "_" . $row["instance"];
    if ($Proj->isCheckbox($fieldId)) {
      error_log("checkbox: ". $fieldId);
      $localKey = $recordId . "_" . $eventId . "_" . $fieldId . "_" . $instance . "_" . $localValue;
    } else {
      $localKey = $recordId . "_" . $eventId . "_" . $fieldId . "_" . $instance;
    }
    $localKVPs[$localKey] = $localValue;
    $remoteKVPValue = $remoteKVPs[$localKey];

    if ($Proj->isCheckbox($fieldId)) {
      error_log("checkbox: ". $fieldId . " remoteKVPValue: $remoteKVPValue");
    }
    //if ($remoteKVPValue != $localValue) {
    if (strcmp(trim($remoteKVPValue), trim($localValue)) !== 0)  {
      error_log("Not Matched! key:$localKey;localValue:$localValue;remoteKVPValue:$remoteKVPValue",0);
      $unmatchedRow = (object)$row;
      $unmatchedRow->id = $localKey;
      $unmatchedRow->remoteValue = $remoteKVPValue;
      array_push($unmatchedValues, $unmatchedRow);
      // also add the unmatched row to the allValues array and provide the unmatched label to it.
      $unmatchedRow->unmatched = $fieldId;
      array_push($allValues, $unmatchedRow);
    } else {
      $matchedRow = (object)$row;
      $matchedRow->id = $localKey;
      $matchedRow->remoteValue = $remoteKVPValue;
      $matchedRow->unmatched = null;
      array_push($allValues, $matchedRow);
    }
  }

  // error_log("localRecordIds: " . print_r($localRecordIds, TRUE));

  // also should loop through the remote values and see if there were any deletions. This is important if a checkbox was deleted locally.
  foreach ($remoteValues as $row) {
    $recordId = $row["RecordId"];
    $eventId = $row["Event"];
    $fieldId = $row["Field"];
    $remoteValue = $row["localValue"];
    $instance = $row["instance"];

    // $rmtKey = $row["RecordId"] . "_" . $row["Event"] . "_" . $row["Field"] . "_" . $row["instance"];
    if ($Proj->isCheckbox($fieldId)) {
      error_log("checkbox: ". $fieldId);
      $rmtKey = $recordId . "_" . $eventId . "_" . $fieldId . "_" . $instance . "_" . $remoteValue;
    } else {
      $rmtKey = $recordId . "_" . $eventId . "_" . $fieldId . "_" . $instance;
    }

    $localRecordId = array_search($recordId, $localRecordIds);
    $localValue = $localKVPs[$rmtKey];

    // check if we have any records for this recordId locally, and then check if the local value exists
    if (($localRecordId !== FALSE) && is_null($localValue) ) {
      error_log("Missing locally! key:$rmtKey;localValue:$remoteValue; localRecordId: $localRecordId",0);
      $row["localValue"] = null;
      $unmatchedRow = (object)$row;
      $unmatchedRow->id = $rmtKey;
      $unmatchedRow->remoteKVPValue = $remoteValue;
      array_push($unmatchedValues, $unmatchedRow);
    }
  }

  $localHashFile = $filePath . "localHash-" . $baseName . ".json";
  file_put_contents($localHashFile, json_encode($localKVPs, JSON_PRETTY_PRINT));
  $unMatchedFile = $filePath . "unmatched-" . $baseName . ".json";
  file_put_contents($unMatchedFile, json_encode($unmatchedValues, JSON_PRETTY_PRINT));
  $allValuesFile = $filePath . "allValues-" . $baseName . ".json";
  file_put_contents($allValuesFile, json_encode($allValues, JSON_PRETTY_PRINT));
  // convert json to csv
  $cfilename = $filePath . "allValues-" . $baseName . ".csv";
  // $data = json_decode($allValues, true);
  // $data = json_encode($allValues);
  $json = file_get_contents($allValuesFile);
  $data = json_decode($json, true);
  $fp = fopen($cfilename, 'w');
  $header = false;
  foreach ($data as $row)
  {
      if (empty($header))
      {
          $header = array_keys($row);
          fputcsv($fp, $header);
          $header = array_flip($header);
      }
      fputcsv($fp, array_merge($header, $row));
  }
  fclose($fp);

  echo json_encode($unmatchedValues);

    // [RecordId] => 2-TMK1-1
    // [Event] => 112
    // [Field] => dy1_scrn_scrnid
    // [localValue] => 2-TMK1-1
    // [instance] =>


}
