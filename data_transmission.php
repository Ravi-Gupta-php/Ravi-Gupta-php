<?php
require_once("../../../redcap_connect.php");
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
if (!VerifyTransmissionSettings()) {
    print "<h4>Transmission not enabled for current project. Please contact your REDCap administrator.";
    return ;
}

// Obtain a list of all instruments used for all events (used to iterate over header rows and status rows)
$formsEvents = array();
// Loop through each event and output each where this form is designated
foreach ($Proj->eventsForms as $this_event_id=>$these_forms) {
	// Loop through forms
	foreach ($these_forms as $form_name) {
		// If user does not have form-level access to this form, then do not display it
		if (!isset($user_rights['forms'][$form_name]) || $user_rights['forms'][$form_name] < 1) continue;
		// Add to array
		$formsEvents[] = array('form_name'=>$form_name, 'event_id'=>$this_event_id, 'form_label'=>$Proj->forms[$form_name]['menu']);
	}
}
// Get the array of eventNames.
$eventNames = $Proj->getUniqueEventNames();
// error_log( "eventNames: "  . print_r($eventNames, TRUE) );

$formDropdownOptions = "";
foreach ($formsEvents as $formEvent) {
  // error_log("formEvent: " . print_r($formEvent['form_name'], TRUE));
  $formLabel = $formEvent['form_label'];
  $formName = $formEvent['form_name'];
  $eventId = $formEvent['event_id'];
  $eventName = $eventNames[$eventId];
  $combined = $formName . "|" . $eventId . "|" . $eventName;
  $formDropdownOptions = $formDropdownOptions . "\n<option value = '$combined'>$formLabel: $eventId</option>";
}
// error_log( "formDropdownOptions: "  . print_r($formDropdownOptions, TRUE) );

?>
 <link rel="stylesheet" href="jquery-ui.css">
 <link rel="stylesheet" href="style.css">
<script>

  $( document ).ready(function() {
    window.RTI = {}
    RTI.projectId = '<?php echo $project_id;?>'
    var app_path_images = '<?php echo APP_PATH_IMAGES ?>';
    initDashboard();
  })

</script>

<h3 style="color:#800000;">
  Data Transmission
</h3>

<p>
    Transmission settings for this project are shown below. Click the Initiate Transmission button to transmit data.
</p>

<br/>

<div id="transmission-panel">

	<div class="item-transmission-menu">Connection status:</div>
	<div class="content-transmission-menu"><img id="connection_status" src=""/></div>

  <div class="item-transmission-menu">
      <button id='transmit' class='btn btn-default btn-xs fs13'>
        <img src='transmission.png' style='vertical-align:middle;position:relative;top:-1px;'>
        <span style='vertical-align:middle;'>&nbsp;Initiate Transmission</span>
      </button>
  </div>
  <div class="content-transmission-menu" >
    <div id="progressbar">
      <div id="initiateTransmission"></div>
      <div class="progress-label"></div>
    </div>
  </div>

  <div class="item-transmission-menu">
      <button id='update_files' class='btn btn-default btn-xs fs13'>
        <img src='update_study.png' style='vertical-align:middle;position:relative;top:-1px;'>
        <span style='vertical-align:middle;'>&nbsp;Update Files</span>
      </button>
  </div>
  <div class="content-transmission-menu" id="progressbarfiles">
    <div class="progress-label-files"></div>
  </div>

  <div class="item-transmission-menu">
      <button id='update_study' class='btn btn-default btn-xs fs13'>
        <img src='update_study.png' style='vertical-align:middle;position:relative;top:-1px;'>
        <span style='vertical-align:middle;'>&nbsp;Update Study</span>
      </button>
  </div>
  <div class="content-transmission-menu" id="progressbar2">
    <div class="progress-label-2"></div>
  </div>
  
  <div class="item-transmission-menu">
      <button id='retrieve_data' class='btn btn-default btn-xs fs13'>
        <img src='retrieve_data.png' style='vertical-align:middle;position:relative;top:-1px;'>
        <span style='vertical-align:middle;'>&nbsp;Retrieve Data</span>
      </button>
  </div>
  <div class="content-transmission-menu"  id="progressbar4">
    <div class="progress-label-4"></div>
  </div>

  <div class="item-transmission-menu"> 
      <button id='display_record_id_input' class='btn btn-default btn-xs fs13'>
        <!-- <img src='retrieve_data.png' style='vertical-align:middle;position:relative;top:-1px;'> -->
        <span style='vertical-align:middle;'>&nbsp;Retrieve Record ID</span>
      </button>
  </div>
  <div id="progressbar6" class="content-transmission-menu ui-progressbar ui-widget ui-widget-content ui-corner-all">
    <div id="record_id_input">
      <input type="text" id="record_id_to_retrieve" placeholder="Enter Record ID"/>
      <button id='retrieve_record_id' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Go!</span>
      </button>
      <span id="progressbar_record_id" class="ui-progressbar ui-widget ui-widget-content ui-corner-all">
        <span class="progress-label-record-id"></span>
      </span>
    </div>
    <div class="progress-label-6"></div>
  </div>

  <div class="item-transmission-menu"> 
      <button id='show_transmission_log' class='btn btn-default btn-xs fs13'>
        <!-- <img src='retrieve_data.png' style='vertical-align:middle;position:relative;top:-1px;'> -->
        <span style='vertical-align:middle;'>&nbsp;Show Transmisson Log</span>
      </button>
  </div>
  <div id="progressbar5" class="content-transmission-menu ui-progressbar ui-widget ui-widget-content ui-corner-all">
    <div class="progress-label-5">View Transmission Log and Force Upload each log item</div>
  </div>
 
  <div class="item-transmission-menu">
      <button id='show_compare_remote_fields_dialog_button' class='btn btn-default btn-xs fs13'>
        <!-- <img src='retrieve_data.png' style='vertical-align:middle;position:relative;top:-1px;'> -->
        <span style='vertical-align:middle;'>&nbsp;Compare/Sync local & remote</span>
      </button>
  </div>
  <div id="compare_remote_fields_div" class="content-transmission-menu syncbar ui-widget ui-widget-content ui-corner-all">
    <div id="compare_remote_fields_output_div">
      <h4>Compare and Sync by Record ID and Form</h4>
      Record ID's: <textarea id="records_to_compare_fields" rows="4" cols="60" placeholder="Enter Record IDs to Compare, each one separated by a space."></textarea>
      <br/>Select Form to Compare: 
      <select id="form_to_compare_fields">
        <option value = ''>Select one</option>
        <?php echo $formDropdownOptions ?>
      </select>
      <button id='compare_remote_fields_button' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Go!</span>
      </button>
      <input type="hidden" id="project_id_compare_fields" value="<?php echo $project_id;?>"/>
      <br/>
      <span id="progressbar_force_sync_records_input" class="ui-widget ui-widget-content ui-corner-all">
        <span class="progress-label-force_sync_records_input"></span>
      </span>
      <h4>Compare and Sync All Record Fields</h4>
      <p>Please note - this feature places a heavy load on both the local and remote Redcap instances. </p>
      <button id='force_sync_all_fields' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Compare and Sync all</span>
      </button>
    </div>
    <div class="progress-label-compare_remote_fields"></div>
    <div id="force-upload-status"></div>
    <div id="force-upload-status-sync"></div>
  </div>
  
  <div class="item-transmission-menu">
      <button id='download_excel_button' class='btn btn-default btn-xs fs13'>
        <!-- <img src='retrieve_data.png' style='vertical-align:middle;position:relative;top:-1px;'> -->
        <span style='vertical-align:middle;'>&nbsp;Compare fields via Excel files</span>
      </button>
  </div>
  <div id="download_excel_button_container" class="content-transmission-menu syncbar ui-widget ui-widget-content ui-corner-all">
    <div id="download_excel_button_div">
    The files will be saved in the rti/transmission/reports directory.
    <h3>Manual mode</h3>
    <p>
      Select Form to Excel Compare:
        <select id="form_excel_download">
          <option value = ''>Select one</option>
          <?php echo $formDropdownOptions ?>
        </select>
    </p>
    <p>
      <input type="hidden" id="project_id_excel_download" value="<?php echo $project_id;?>"/>
      <button id='download_local' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Download Local Excel</span>
      </button> | 
      <button id='download_remote' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Download Remote Excel</span>
      </button>
    </p>
      <p id='manual_compare_section'>After generating the local and remote files, you may compare them:  
      <button id='compare_records' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Compare Local and Remote Values</span>
      </button>
      <button id='manual_compare_auto_force_upload' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Automatic Force Upload</span>
      </button>
      </p>
      <h3>Automatic mode</h3>
      <p>This mode will loop through all forms and display any unmatched values: </p>
      <p> 
      <button id='compare_records_automatic' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Automatic Comparison</span>
      </button>
      <button id='compare_auto_force_upload' class='btn btn-default btn-xs fs13'>
        <span style='vertical-align:middle;'>&nbsp;Automatic Force Upload</span>
      </button>
      </p>
      <div id="dlExcelResults"></div>
    </div>
    <div class="progress-label-download-excel"></div>
  </div>
</div>

<div id='transmission_progress_log'></div>
<div id='transmission_log'></div>

 <script type="text/javascript" src="transmission.js"></script>
 <script type="text/javascript" src="force_upload.js"></script>
<?php

// READ TRANSMISSION SETTINGS, ENSURE TRANSMISSION IS ENABLED

function VerifyTransmissionSettings()
{
    global $conn;
    $settings = [];

    $project_id = $_GET['pid'] or die('<strong>Fatal error:</strong> Transmission requires passing of pid parameter. Please contact your REDCap administrator.');
    //echo $project_id;

   echo $sql = "select field_name, value from redcap_config where field_name like 'transmission_%'";
    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['field_name']] = $row['value'];
    }

    $transmission_model = $settings['transmission_model'];
    $transmission_primary_token = $settings['transmission_remote_token'];
    $transmission_secondary_token = $settings['transmission_local_token'];
    $transmission_for_current_project = false;

    $p = explode(";", $transmission_primary_token);
    $s = explode(";", $transmission_secondary_token);

    $projects = array_merge($p, $s);

    foreach ($projects as $value) {
        $project = explode(":", $value);
        if (trim($project[0]) == $project_id && strlen(trim($project[1])) == 32) {
            $transmission_for_current_project = true;
            break;
        }
    }

    if ($transmission_model != '0' && $transmission_for_current_project == true) {
        return true;
    } else {
        return false;
    }
}

function GetTransmissionSettings()
{
    global $conn;
    $settings = [];
    $sql = "select field_name, value from redcap_config where field_name like 'transmission_%'";
    $result = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['field_name']] = $row['value'];
    }
    return $settings;
}
   
?>

<?php

// OPTIONAL: Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
