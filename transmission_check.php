<?php
require_once "../../../redcap_connect.php";
// require_once "../transmission.php";
// require_once "./event_stream.php";
 
/**
 * RetrieveRecordFormList
 * Fetches forms that are on the local (and remote if $checkRemote === true) server for *one* record.
 * Create the  response->result object of forms that are on the server in the format:
 *   $response = new stdClass();
 *   $response->submitted = $submitted; - array of submitted result objects
 *   $response->missing = $missing; - array of missing result objects
 *   $response->remoteResults = $output; - array of remote result objects - used for field comparison
 *   $response->localResults = $localData; - array of local result objects - used for field comparison
 *  Each of these result objects takes the properties
 *   $result = new stdClass();
 *   $result->id = $id;
 *   $result->formName = $formName;
 *   $result->complete = $complete;
 * 
 * @param  mixed $project_id
 * @param  mixed $recordId
 * @param  mixed $eventId
 * @param  mixed $formNames
 * @param  mixed $checkAllFields
 * @param  mixed $checkRemote
 *
 * @return void
 */
function RetrieveRecordFormList($project_id, $recordId, $eventId, $formNames, $checkAllFields, $checkRemote) {

  global $conn; 
  global $Proj;

  $recordIds = array();
  array_push($recordIds , $recordId);
  $events = array();
  array_push($events , $eventId);


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

  // $xArray = implode(' ',$recordIds);
  // error_log("RetrieveRecordFormList remoteRecordIds array:  $xArray ", 0);

  // error_log( "formNames: "  . print_r($formNames, TRUE) );

	$uniqueEventNames = $Proj->getUniqueEventNames();
  $eventName = $uniqueEventNames[$eventId];
  // error_log("eventName: $eventName", 0);
  $eventArray = array();
  array_push($eventArray, $eventName);

  $fieldNames = array();
  array_push($fieldNames, 'dy1_scrn_scrnid');
  foreach ($formNames as $formName) {
      $completeFormname = $formName.'_complete';
      array_push($fieldNames, $completeFormname);
  }

  if ($checkAllFields) {
    // error_log("RetrieveRecordFormList: checkAllFields is set", 0);
      $phi_safe_field_names = [];
      foreach ($formNames as $formName) {
        $formFieldsProj = $Proj->forms[$formName]['fields'];
        // error_log( "formFieldsProj: "  . print_r($formFieldsProj, TRUE) );

        $metadata = array();
        foreach ($formFieldsProj as $this_field=>$this_label) {
            $metadata[] = $Proj->metadata[$this_field];
        }
        foreach ($metadata as $row) {
          // error_log("field_name: "  . $row['field_name'] . " field_phi: "  . $row['field_phi']);
          $phi = $row['field_phi'];
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
                      $form_field_mapping[$form] = $phi_safe_field_names;
                      $phi_safe_field_names = [];
                  }
                  $form = $row['form_name'];
              }
              array_push($phi_safe_field_names, $field_name);
          } else {
              error_log($field_name. " has phi and the server is set not to upload phi.", 0);
          }
        }
      }
      $fieldNamesToCompare = array_merge($fieldNames, $phi_safe_field_names);
    } else {
      $fieldNamesToCompare = $fieldNames;
    }
  // error_log( "fieldNamesToCompare: "  . print_r($fieldNamesToCompare, TRUE) );
  $localData = REDCap::getData('json', "$recordId", $fields = $fieldNamesToCompare, $events = $eventId);
  // error_log("Local data for event_id: $event_id, form: $form,  data:  " . print_r($localData, TRUE), 0);

  $response = new stdClass();
  $response->localResults = json_decode($localData);

  // Query the remote server and add the remote data to $response.
  if ($checkRemote === true) {
    error_log("checkRemote:  true". $checkRemote, 0);
    $data = array(
      'token' => $api_token,
      'content' => 'record',
      'format' => 'json',
      'fields' => $fieldNamesToCompare,
      'records' => $recordIds,
      'events' => $eventArray,
      // 'forms' => $formNames,
      'rawOrLabel' => 'raw',
      'rawOrLabelHeaders' => 'raw',
      'exportCheckboxLabel' => 'false',
      'exportSurveyFields' => 'false',
      'exportDataAccessGroups' => 'false',
      'returnFormat' => 'json'
    );
  
      // error_log("data config to server:  " . print_r($data, TRUE), 0);
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $project_url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
  
    // curl_setopt($ch, CURLOPT_VERBOSE, 1);
    // $verbose = fopen('log-verbose.txt', 'w+');
    // curl_setopt($ch, CURLOPT_STDERR, $verbose);
  
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    $output = curl_exec($ch);
    curl_close($ch);
  
    // $verboseLog = stream_get_contents($verbose);
  
    // error_log("finished fetching remote data  ", 0);
  
    // LogTransmissionResult($output);
    $outputJson = json_decode($output,true);
  
    // var_dump(json_decode($output));
    // error_log("output www:  $output ", 0);
    if (property_exists($outputJson, 'error')) {
      $error = $outputJson->error;
      error_log("ERROR: $error", 0);
      $response = new stdClass();
      // $response->submitted = $results;
      $response->error = $value;
      return (json_decode($outputJson));
    }
  
    // Create the  result object in the format:
    //  $result->id 
    //  $result->$formName
    $submitted = array();
    $missing = array();
    $id;$repeatInstance;
    foreach ($outputJson as $key => $value) {
      if (isset($value['dy1_scrn_scrnid'])) {
          $id = $value['dy1_scrn_scrnid'];
      }
      if (isset($value['redcap_repeat_instance'])) {
        $repeatInstance = $value['redcap_repeat_instance'];
      }
      // error_log("id:  $id ", 0);
      foreach ($formNames as $formName) {
          $completeFormname = $formName.'_complete';
          $result = new stdClass();
          $result->id = $id;
          // error_log("id:  $id, formname: $formName ", 0);
          
          if (isset($value[$completeFormname])) {
              $complete = $value[$completeFormname];
              // error_log("id:  $id, formname: $formName, complete: $complete", 0);
  
              // $complete can be 0,1,2. When a form is initially submitted, $complete is set to 0 (Incomplete).
              if ($complete !== '') {
                  error_log("id:  $id formName:  $formName completeFormname: $completeFormname was submitted; complete: $complete ", 0);
                  $result->formName = $formName;
                  array_push($submitted, $result);
              } else {
                $ignore = false;
                // special cases
                if ($repeatInstance > 1) {
                  if ($formName === 'form_14_verbal_and_social_autopsy') {
                    // this is an error - is not a repeating form. the RESt API returns this for no good reason.
                    $ignore = true;
                    error_log("IGNORING id: $id formName:  $formName  has empty complete:  $complete and repeatInstance is $repeatInstance ", 0);
                  }
                }
                if (!$ignore) {
                  error_log("id: $id formName:  $formName  has empty complete:  $complete ", 0);
                  $result->formName = $formName;
                  $result->complete = $complete;
                  array_push($missing, $result);
  
                  // LogTransmissionResult($output);
                }
              }
          } else {
            error_log("id: $id formName:  $formName  is not submitted ", 0);
            $result->formName = $formName;
            $result->complete = $complete;
            array_push($missing, $result);
          }
      }
    }
  
    // It looks redundant, but it fixes a bug wherein results and missing are empty.
    $allForms = array();
    foreach ($formNames as $formName) {
      $result = new stdClass();
      $result->id = $recordId;
      $result->formName = $formName;
      array_push($allForms, $result);
    }

    $response->submitted = $submitted;
    $response->missing = $missing;
    $response->remoteResults = json_decode($output);
    
    if (sizeof($missing) === 0 && sizeof($submitted)  === 0) {
      $response->createRecord = true;
      error_log("No Results or Missing records; copying all formNames to missing for: $recordId");
      $response->created = $allForms;
      $response->mmissing = null;
      foreach ($allForms as $result) {
        // $result = new stdClass();
        // $result->id = $recordId;
        // $result->formName = $formName;
        // array_push($allForms, $result);
        // $project_id, $recordId, $eventId, $formNames, $checkAllFields, $checkRemote)
        $formName = $result->formName;
        $message = TransmitForm($recordId, $project_id, [$formName], $eventId, null, null, null);
        error_log("Transmittted form $formName. Result: $message");
      }
    }
  }
  
  // $response->fieldNames = $fieldNames;
  // echo (json_encode($response));
  // $xArray = implode(' ',$response->results);
  // error_log("remoteRecords response array:  $xArray ", 0);
  // error_log("remoteRecords response:  ", 0);
  // error_log( print_r($response->submitted, TRUE) );

  // return (json_decode($response));
  return ($response);
}


/**
 * ListAllLocalRecords
 *
 * @param  mixed $project_id
 * @param  mixed $records
 * @param  mixed $saveAsExcel
 * @param  mixed $queryRemote
 *
 * @return void
 */
function ListAllLocalRecords($project_id, $records, $saveAsExcel, $queryRemote) {

  global $Proj;

  if ($saveAsExcel === "true") {
    error_log("Generating Excel Spreadsheet");
    // create a file pointer connected to the output stream
    $dirSep = DIRECTORY_SEPARATOR;
    if ($dirSep == '/') {
      $os = 'linux';
    }
    $timestamp = date('Y-m-d-H-i-s');
    $filename = 'redcap-local-'. $timestamp . '.csv';
    if (!file_exists('.' . $dirSep . 'reports')) {
      mkdir('.' . $dirSep . 'reports', 0777, true);
    }
    $filePath = './reports/' . $filename;
    error_log("Local report will be at: $filePath", 0);
    // $output = fopen('php://output', 'w');
    
    $output = fopen($filePath, 'w');

    // get field information from metadata
    // $fieldData = MetaData::getFields($project_id, $longitudinal, $primaryKey, false, $hasSurveys, $fields, $rawOrLabel, $exportDags, $exportSurveyFields);
    
    // output the column headings
    fputcsv($output, array('RecordId', 'Event', 'Field', 'localValue', 'instance', 'id'));

    // $result = array();
    $sql = "SELECT record, event_id, field_name, value, instance
        FROM redcap_data
        WHERE project_id = $project_id
        ORDER BY record, event_id";
	
    $result = db_query($sql);

    // error_log("output: " . print_r(db_fetch_array($result)));

    $count = 0; // Counting how many rows are output for debugging.
    while ($row = db_fetch_assoc($result)) {
        $recordId = $row['record'];
        $eventId = $row['event_id'];
        $fieldName = $row['field_name'];
        $instance = $row['instance'];
        $redcap_event_name = $Proj->getUniqueEventNames($eventId);
        // # ignore blank values
        // if ($row['value'] == "") {
        //     continue;
        // }
        $value = $row['value'] ;
        $id = $recordId . "_" . $redcap_event_name . "_" . $fieldName . "_" . $instance;
        $row = array($recordId, $redcap_event_name, $fieldName, $value, $instance, $id);
        $count++;
        fputcsv($output, $row);
    }

    fclose($output);

    error_log("Rows output from local db: $count");
    // $contLength = ob_get_length();
    // header( 'Content-Length: '.$contLength);
    $dirSep = DIRECTORY_SEPARATOR;
    if ($dirSep == '/') {
      $os = 'linux';
    }
    $versionFile = $redcapPath . $dirSep . 'myversion.txt';
    $reportsPath = realpath(__DIR__ . $dirSep . 'reports');
    $downloadPath = $reportsPath . $dirSep . $filename;
    $wwwPath = $filePath;
    error_log("redcapPath: $reportsPath");
    $message = "<p>Local report is available at <a href=\"$wwwPath\">$downloadPath</a></p>";
    // echo json_encode($message);
    echo $message;
    sleep(2);
    // error_log("Sending Final SSE");
    // $serverTime = time();
    // sendSSEmsg($serverTime, 'Added row at server time: ' . date("h:i:s", time()));

  } else {

  if (is_null($records)) {
    $recordNames = array_values(Records::getRecordList(PROJECT_ID, ($user_rights['group_id'] != '' ? $user_rights['group_id'] : (isset($_SESSION['dag_' . PROJECT_ID]) ? $_SESSION['dag_' . PROJECT_ID] : null)), true));
  } else {
    $recordNames = explode(' ', $records);
  }
  // Get list of all records (or specific ones) with their Form Status for all forms/events
  $formStatusValues = Records::getFormStatus(PROJECT_ID, $recordNames);

  // $xArray = implode(' ',$formStatusValues);
  // error_log("formStatusValues array:  $xArray ", 0);
    // var_dump(json_decode($formStatusValues));
    // error_log("saveAsExcel: $saveAsExcel");

  // echo json_encode($formStatusValues);
    echo json_encode($formStatusValues);
  }
}



function download_send_headers($filename) {
  // disable caching
  $now = gmdate("D, d M Y H:i:s");
  header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
  header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
  header("Last-Modified: {$now} GMT");

  // force download  
  header("Content-Type: application/force-download");
  header("Content-Type: application/octet-stream");
  header("Content-Type: application/download");

  // disposition / encoding on response body
  header("Content-Disposition: attachment;filename={$filename}");
  header("Content-Transfer-Encoding: binary");
}



/**
 * CheckRemoteRecord
 *
 * @param  mixed $project_id
 * @param  mixed $recordId
 * @param  mixed $eventId
 * @param  mixed $formNames
 * @param  mixed $checkAllFields
 * @param  mixed $checkRemote
 *
 * @return void
 */
function CheckRemoteRecord($project_id, $recordId, $eventId, $formNames, $checkAllFields, $checkRemote) {

  $remoteRecords = RetrieveRecordFormList($project_id, $recordId, $eventId, $formNames, $checkAllFields, $checkRemote);

  if (property_exists($remoteRecords, 'error')) {
    $error = $remoteRecords->error;
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error: '. $error, true, 500);
    error_log("ERROR: $error", 0);
    return (json_decode($error));
  }

  $remoteRecordsResponse = $remoteRecords->remoteResults;
  // $remoteRecordsMissing = $remoteRecords->missing;
  // error_log( "remoteRecordsResponse remoteResults: "  . print_r($remoteRecordsResponse, TRUE) );
  return ($remoteRecords);
}

function CheckRemoteRecordAction($project_id, $recordId, $eventId, $formNames, $checkAllFields)
{
  $remoteResult = CheckRemoteRecord($project_id, $recordId, $eventId, $formNames, $checkAllFields, true);
  $response = json_encode($remoteResult);
  // error_log( "remoteResult: "  . print_r($remoteResult, TRUE) );
  error_log("error: $json_last_error_msg()");
  echo $response;
}

function LogTransmissionResult($text) {
  $file = 'log-verbose.txt';
  $current = file_get_contents($file);
  $current .= $text . "\n";
  file_put_contents($file, $current);
  return;
}

