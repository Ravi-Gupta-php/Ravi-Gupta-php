/*jshint esversion: 6 */

$(function () {
    $("#progressbar").progressbar({
        value: 0,
        max: 100
    });
});
$(function () {
    $("#progressbar2").progressbar({
        value: 0,
        max: 100
    });
});
$(function () {
    $("#progressbarfiles").progressbar({
        value: 0,
        max: 100
    });
});
$(function () {
    $("#progressbar4").progressbar({
        value: 0,
        max: 100
    });
});
$(function () {
    $("#progressbar_record_id").progressbar({
        value: 0,
        max: 100
    });
});

var cases = []; var n = 0; var num_cases = 0;
// $('#progressbar').width(500);

$(document).ready(function () {
    $('#update_study').click(function () {
        if (confirm("Updating the study structure can be destructive and may result in data loss if applied incorrectly. Click OK to continue or cancel to stop.")) {
            $('.progress-label-2').text('Updating study structure. Please be patient.');
            $.ajax({
                url: "transmit.php?pid=" + RTI.projectId,
                type: 'post',
                data: { action: 'update_study', project_id: RTI.projectId },
                success: function (result) {
                    $('.progress-label-2').text('The study structure has been updated successfully.');
                }
            });
        }
    });

    $('#update_files').click(function () {
        if (confirm("Updating the study files can be destructive and may result in data loss if applied incorrectly. Click OK to continue or cancel to stop.")) {
            $('.progress-label-files').text('Updating study files. Please be patient.');
            $.ajax({
                url: "updater.php?pid=" + RTI.projectId,
                type: 'post',
                data: { action: 'update_files', project_id: RTI.projectId },
                success: function (result) {
                    $('.progress-label-files').html(result);
                    $('.progress-label-files').css('backgroundColor', 'white');
                    $('.progress-label-files').css('text-align', 'left');
                },
                error: function (error) {
                    $('.progress-label-files').html(error.responseText);
                    $('.progress-label-files').css('backgroundColor', 'pink');
                    $('.progress-label-files').css('text-align', 'left');
                }
            });
        }
    });

    $('#retrieve_data').click(function () {
        // Get list of cases to be retrieved (just api call with record id field)
        progressLabel = $(".progress-label-4");
        progressLabel.text("Retrieving list of records");
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'retrieve_case_list', project_id: RTI.projectId },
            success: function (result) {
                id_list = JSON.parse(result);
                if (id_list.length == 0) {
                    progressLabel.text("There are no records to retrieve");
                } else {
                    progressLabel.text("There are " + id_list.length + " records to retrieve");
                    if (confirm("There are " + id_list.length + " records to retrieve. WARNING: Retrieving records will OVERWRITE data on this device. Click OK to continue or cancel to stop.")) {
                        progressLabel.text("Retrieving records");
                        var n = 0; var num_cases = id_list.length; var error_occurred = false;
                        RetrieveAndImportData(id_list, 50, n, num_cases, error_occurred);
                    } else {
                        progressLabel.text("No records retrieved");
                    }
                }
            }, error: function (result) {
                alert("Error: " + result);
            }
        });
    });

    $('#transmit').click(function () {
        $("#transmit").attr("disabled", "disabled");
        var error_occurred = false;
        var progressbar = $("#progressbar"), progressLabel = $(".progress-label");
        progressbar.progressbar("value", 0);
        progressLabel.text('Preparing records for transmission');
        // num_cases = cases.length;

        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'log_start', project_id: RTI.projectId },
            success: function (result) {
                // SubmitData(cases, 50, n, num_cases, error_occurred);
                TransmitData(RTI.pendingTransmissions);
            }
        });
    });

    $('#display_record_id_input').click(function () {
        $('#record_id_input').show();
    });

    $('#display_force_sync_records_input').click(function () {
        $('#force_sync_records_input').show();
        $('#transmission_progress_log').html("");
        $('#initiateTransmission').html('Refresh the page to use the Initiate Transmission feature.');
    });
    
    $('#retrieve_record_id').click(function () {
        var record_id = $('#record_id_to_retrieve').val();
        var num_cases = 1;
        if (record_id == '') {
            alert("Enter Record ID");
        } else {
            console.log("record_id: " + record_id)
            // $('#record_id_to_retrieve').val('');
            var progressbar = $("#progressbar_record_id");
            var progressLabel = $(".progress-label-record-id");
            progressbar.progressbar("value", 0);
            progressLabel.text('');
            $.ajax({
                url: "transmit.php?pid=" + RTI.projectId,
                type: 'post',
                data: { action: 'retrieve', record_id: record_id, project_id: RTI.projectId },
                error: function (result) {
                    alert('Error: ' + result.statusText);
                },
                success: function (result) {
                    console.log("result: " + result)
                    if (result.toUpperCase().includes("ERROR")) {
                        transmit_message = "Transmission completed with errors: please check log.";
                        alert("Error: " + result);
                    } else {
                        transmit_message = 'Retrieved record: ' + record_id + ' (' + n + ' of ' + num_cases + ')';
                    }
                    var val = progressbar.progressbar("value") || 0;
                    progressLabel.text('Retrieved record: ' + record_id);
                    progressbar.progressbar("value", val + parseFloat(100) / num_cases);
                }
            });
            $('#record_id_to_retrieve').val('');
        }
    });


    $('#show_transmission_log').click(function () {

        openLoader($(".progress-label-5"));
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'show_transmission_log', project_id: RTI.projectId },
            success: function (result) {
                ShowTransmissionLog(result);
                closeLoader($(".progress-label-5"));
            }
        });
    });

    $('#sync_all_records').click(function () {
        syncAllRecords();
    });


    $('#force_sync_records_ids').click(async function () {
        $(".progress-label-force-sync").hide()
        var record_ids = $('#records_to_force_sync').val();
        var form_id_field = $('#form_to_force_sync').val();
        var project_id = $('#project_id').val();
        var form_id, event_id;
        if (form_id_field !== '') {
            var arr = form_id_field.split("|");
            form_id = arr[0];
            event_id = arr[1];
        }
        var num_cases = 1;
        if (record_ids == '' || form_id_field == '') {
            alert("Enter Record ID's, each one separated by a space. Also, please select a form from the dropdown.");
            return false
        }
        if (record_ids.indexOf(",") > -1) {
            alert("You must separate Record ID's by a space.");
            return false
        }
        //  else {
            var recordsArray = trim(record_ids).split(" ");
            RTI.current_record = 0
            document.querySelector(".syncbar").style.height = "15em";
            document.querySelector("#force-upload-status-sync").style.height="10em";
            document.querySelector(".syncbar").style.overflowY="scroll";
            for (var recordId of recordsArray) {
                RTI.current_record++
                console.log("record_id: " + recordId + " formId: " + form_id + " event_id: " + event_id + " project_id: " + project_id);
                var isBulk = true;
                await forceUpload(recordId,project_id,form_id,event_id, null, isBulk);
                console.log("Waiting 5 seconds to allow processing on server.")
                await delay(5000);
            }
            // CompareLocalToRemote(record_ids, form_id);
        
            $('#records_to_force_sync').val('');
        // }
    });

    $('#show_compare_remote_fields_dialog_button').click(function () {
        $('#compare_remote_fields_output_div').show();
        $('#transmission_progress_log').html("");
        $('#initiateTransmission').html('Refresh the page to use the Initiate Transmission feature.');
        $(".progress-label-compare_remote_fields").hide()
    });

    $('#download_excel_button').click(function () {
        $('#download_excel_button_div').show();
        $('.progress-label-download-excel').hide();
        $('#manual_compare_auto_force_upload').hide();
        $('#compare_auto_force_upload').hide();
    });

    $('#compare_remote_fields_button').click(async function () {
        var record_ids = $('#records_to_compare_fields').val();
        var form_id_field = $('#form_to_compare_fields').val();
        var project_id = $('#project_id_compare_fields').val();
        var form_id, event_id;
        if (form_id_field !== '') {
            var arr = form_id_field.split("|");
            form_id = arr[0];
            event_id = arr[1];
        }
        var num_cases = 1;
        if (record_ids == '' || form_id_field == '') {
            alert("Enter Record ID's, each one separated by a space. Also, please select a form from the dropdown.");
            return false
        }
        if (record_ids.indexOf(",") > -1) {
            alert("You must separate Record ID's by a space.");
            return false
        }
        //  else {
            var recordsArray = trim(record_ids).split(" ");
            RTI.current_record = 0
            document.querySelector(".syncbar").style.height = "15em";
            document.querySelector("#force-upload-status-sync").style.height="10em";
            document.querySelector(".syncbar").style.overflowY="scroll";
            // for (var recordId of recordsArray) {
            //     RTI.current_record++
            //     console.log("record_id: " + recordId + " formId: " + form_id + " event_id: " + event_id + " project_id: " + project_id);
            //     var isBulk = true;
            //     await forceUpload(recordId,project_id,form_id,event_id, null, isBulk);
            //     console.log("Waiting 5 seconds to allow processing on server.")
            //     await delay(5000);
            // }
            CompareLocalToRemote(record_ids, form_id);
        
            $('#records_to_force_sync').val('');
        // }
    });

    $('#force_sync_all_fields').click(async function () {
        var project_id = $('#project_id_compare_fields').val();
        var form_id, event_id, saveAsExcel = false;
        
        document.querySelector(".syncbar").style.height = "15em";
        document.querySelector("#force-upload-status-sync").style.height="10em";
        document.querySelector(".syncbar").style.overflowY="scroll";
        let a;  // setup element for saveAsExcel.

        $('#records_to_force_sync').val('');
        if ($('#saveExcel').prop('checked')) {
            console.log("saveAsExcel checked")
            saveAsExcel = true;
            a = document.createElement("a");
            document.body.appendChild(a);
            a.style = "display: none";
        }
        if (confirm("Compare and Sync All should only be used in rare situations. Click OK to list all local records or cancel to stop.")) {
            openLoader($("#center"));
            $.ajax({
                url: "transmit.php?pid=" + RTI.projectId,
                type: 'post',
                data: { action: 'sync_all_records', project_id: RTI.projectId, saveAsExcel: saveAsExcel },
                success: function (result) {
                    closeLoader($("#center"));
                    var records = JSON.parse(result);
                    if (records.length == 0) {
                        // $("#transmit").attr("disabled", "disabled");
                        $('.progress-label').text('No data to sync.');
                    } else {
                        var details = `<h4>Records to Sync</h4>
                        <p>This process will compare fields on your local and remote Redcap instance.  
                        <p>Important: If you check "Overwrite data on remote server" it will change any values on the server that are different from the local redcap instance. 
                        If you're running this process from a tracker, it would erase data uploaded from other trackers. </p>
                        <div id="syncButtons">
                            <button id="sync_remote_fields" class="btn btn-default btn-xs fs13">Compare Local to Remote</button>
                            <input type="checkbox" id="overwriteRemoteServer">Overwrite data on remote server
                            <p>Enter "RTI.stopSync=true" in the js console to stop the sync while it is in progress. Either refresh the page or set the value to true to be able to resume.</p>
                        </div>
                        <div id="syncProgress"></div>
                        <table class="transmission">\n<tr><th>Record</th><th>Event</th><th>Forms</th></tr>`;
                        for (const key of Object.keys(records)) {
                            var formsToString = "";
                            var record_id = key;
                            var events = records[key];
                            var event;
                            for (const key of Object.keys(events)) {
                                event = key;
                                formStatusObject = events[key];
                                var size = Object.keys(formStatusObject).length;
                                if (size > 0) {
                                    var display = false;
                                    formTable = "<table><tr><td id='recordEventStatus_" + record_id + "_" + event + "' colspan='3'></td></tr>\n<tr><th>Form</th><th>Status</th></tr>\n";
                                    for (const key of Object.keys(formStatusObject)) {
                                        var form = key;
                                        var status = formStatusObject[key]["1"];
                                        if (status !== '') {
                                            display = true;
                                            // console.log("form: " + form + "status: " + JSON.stringify(status));
                                            formTable = formTable + '<tr><td id="form_' + form + '_' + event + '_' + record_id + '">' + form + '</td><td id="formStatus_' + record_id + '_' + event + '_' + form + '"></td></tr>\n';
                                        }
                                    }
                                    ;
                                    formTable = formTable + '</table>';
                                    if (display) {
                                        details = details + '<tr id="' + record_id + '-' + event + '-row"><td>' + record_id + '</td><td>' + event + '</td><td id="' + record_id + '-' + event + '-formInfo">' + formTable + '</td></tr>\n';
                                    }
                                }
                            }
                        }
                        details = details + '</table>';
                        $('.progress-label').text('0 of ' + RTI.num_records + ' records transmitted');
                        $('#transmission_progress_log').html(details);
                    }
                    
                    $('#sync_remote_fields').click(async function () {
                        let message = "Click OK to compare fields for *all* records and sync any missing data from the local instance to the remote server.";
                        let overwriteRemoteServer = false;
                            if ($('#overwriteRemoteServer').prop('checked')) {
                                overwriteRemoteServer = true;
                                message = "Click OK to compare fields for *all* records from the local instance to the remote server.";
                                console.log("overwriteRemoteServer");
                            }
                        if (confirm(message)) {
                            for (const key of Object.keys(records)) {
                                let formsToString = "";
                                let record_id = key;
                                let events = records[key];
                                let event;
                                for (const key of Object.keys(events)) {
                                    event = key;
                                    formStatusObject = events[key];
                                    let size = Object.keys(formStatusObject).length;
                                    if (size > 0) {
                                        let display = false;
                                        formTable = "<table><tr><td id='recordEventStatus_" + record_id + "_" + event + "' colspan='3'></td></tr>\n<tr><th>Form</th><th>Status</th></tr>\n";
                                        for (const key of Object.keys(formStatusObject)) {
                                            let form = key;
                                            let status = formStatusObject[key]["1"];
                                            if (status !== '') {
                                                display = true;
                                                if (RTI.stopSync === true) {
                                                    console.log("Stopping Sync at " + record_id)
                                                    break;
                                                }
                                                var checkAllFields = true;
                                                var formsToCheck = [];
                                                formsToCheck.push(form);
                                                $('#syncProgress').text("Processing record " + record_id + " for event " + event + ". Form(s): " + formsToCheck);
                                                try {
                                                    var remoteResult = await CheckRemoteRecord(project_id, record_id, event, formsToCheck,  checkAllFields, true)
                                                    if (overwriteRemoteServer) {
                                                        if (remoteResult.fieldList.length > 0) {
                                                            // check value of sync_all_missing_fields
                                                            // if ($('#sync_all_missing_fields').prop('checked')) {
                                                                console.log("force upload missing form: " + formsToCheck[0]);
                                                                let uploadProgressDiv = `checkRemote-${formsToCheck[0]}-${remoteResult.event_id}-${remoteResult.record_id}`;
                                                                await ForceUpload(remoteResult.record_id,remoteResult.projectId,formsToCheck[0],remoteResult.event_id,uploadProgressDiv,true,remoteResult.fieldList)
                                                            // }
                                                        }
                                                    }

                                                } catch(err) {
                                                    console.log("ERROR: " + JSON.stringify(err))
                                                }
                                            }
                                        };
                                        // formTable = formTable + '</table>';
                                        // if (display) {
                                        //     details = details + '<tr id="' + record_id + '-' + event + '-row"><td>' + record_id + '</td><td>' + event + '</td><td id="' + record_id + '-' + event + '-formInfo">' + formTable + '</td></tr>\n';
                                        // }
                                    }
                                }
                            };
                        }
                    });
                }
            });
        }


    });
    $('#download_local').click(async function () {
        var form_event_id_field = $('#form_excel_download').val();
        openLoader($("#manual_compare_section"));
        await downloadForm(form_event_id_field, false, 0);
        closeLoader($("#manual_compare_section"));
    });
    $('#download_remote').click(async function () {
        var form_event_id_field = $('#form_excel_download').val();
        openLoader($("#manual_compare_section"));
        await downloadForm(form_event_id_field, true);
        closeLoader($("#manual_compare_section"));
    });

    $('#compare_records').click(async function () {
        // var project_id = $('#project_id_compare_fields').val();
        var form_event_id_field = $('#form_excel_download').val();
        await compareFormRecords(form_event_id_field);
        $('#manual_compare_auto_force_upload').show();
    });

    $('#compare_records_automatic').click(async function () {
        // var project_id = $('#project_id_compare_fields').val();
        // var form_event_id_field = $('#form_excel_download').val();
        $('#transmission_progress_log').html('');
        let select = document.querySelector('#form_excel_download')
        let i = 0;
        for (const option of select) {
            let value = option.value
            if (value !== '') {
                openLoader($("#dlExcelResults"));
                await downloadForm(value, false, i);
                closeLoader($("#dlExcelResults"));
                i++
                let message = "Currently at: " + i + " of " + select.length;
                console.log(message)
                $('#dlExcelResults').html(message);
                openLoader($("#dlExcelResults"));
                await downloadForm(value, true);
                closeLoader($("#dlExcelResults"));
                await compareFormRecords(value);
                let lastElement = select[select.length - 1];
                if (value === lastElement.value) {
                    alert("Done! Click the Automatic Force Upload button to automatically upload any unmatched values.")
                    // reveal the auto-force-upload
                    // document.querySelectorAll('.transmission a')
                    $('#compare_auto_force_upload').show();
                }
                await delay(2000);
            }
        }
    });

    $('#manual_compare_auto_force_upload').click(async function () {
        compareForceUploadLinks(false)
    });
    $('#compare_auto_force_upload').click(async function () {
        compareForceUploadLinks(true)
    });

    async function compareForceUploadLinks(automatic) {
        let links = document.querySelectorAll('#transmission_progress_log a')
        let i = 0, currentLinkId;
        let len = links.length;
        for (const link of links) {
            ++i
            let linkId = link.id;
            // ${record.RecordId}|${RTI.projectId}|${formName}|${event_id}'
            let linkArray = linkId.split('|');
            let recordId = linkArray[0];
            let projectId = linkArray[1];
            let formName = linkArray[2];
            let event_id = linkArray[3];
            // openLoader($("'#formStatus_" + recordId + "_" + event_id + "_" + formName + "'"));
            // todo: implement new await ForceUpload nethod
            if (typeof currentLinkId !== 'undefined' && currentLinkId.toString() !== linkId.toString()) {
                console.log("linkFunction: " + linkId)
                currentLinkId = linkId;
                await ForceUpload(recordId,projectId,formName,event_id,null,true)
                if (automatic) {
                    console.log("i: " + i + " len: " + len);
                    if (i === len) {
                        alert("Force uploads are complete.")
                    }
                }
            } else if (typeof currentLinkId === 'undefined' ) {
                console.log("linkFunction: " + linkId)
                currentLinkId = linkId;
                await ForceUpload(recordId,projectId,formName,event_id,null,true)
                if (automatic) {
                    console.log("i: " + i + " len: " + len);
                    if (i === len) {
                        alert("Force uploads are complete.")
                    }
                }
            } else {
                console.log("next")
            }
            // closeLoader($("'#formStatus_" + recordId + "_" + event_id + "_" + formName + "'"));
            await delay(2000);
        }
    }

    
});

const delay = ms => new Promise(res => setTimeout(res, ms));

async function compareFormRecords(form_event_id_field) {
    console.log("form_event_id_field: " + form_event_id_field)
    var formName, event_id;
    if (form_event_id_field !== '') {
        var arr = form_event_id_field.split("|");
        formName = arr[0];
        event_id = arr[1];
        // let a = document.createElement("a");
        // document.body.appendChild(a);
        // a.style = "display: none";
        // openLoader($("#center"));
        await $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'compareLocalRemoteValues', project_id: RTI.projectId, },
            success: await function (result) {
                // closeLoader($("#center"));
                // if (result)
                result = JSON.parse(result);
                let output = `<table class="transmission">
                        <tr><th colspan="6" class="compare_form_header">Form: ${formName} Event: ${event_id}</th></tr>
                        <tr><td colspan="6" id="force-upload-status-1"></td></tr>
                        <tr>
                            <th>Record ID</th>
                            <th>Event</th>
                            <th>Field</th>
                            <th>Instance</th>
                            <th>Local Value</th>
                            <th>Remote Value</th>
                        </tr>
                    `;
                for (var record of result) {
                    output = output + `<tr>
                            <td><a id='${record.RecordId}|${RTI.projectId}|${formName}|${event_id}' onclick="ForceUpload('${record.RecordId}', '${RTI.projectId}', '${formName}', '${event_id}', null);">${record.RecordId}</a></td>
                            <td>${record.Event}</td>
                            <td>${record.Field}</td>
                            <td>${record.instance}</td>
                            <td>${record.localValue}</td>
                            <td>${record.remoteKVPValue}</td>
                        </tr>
                        <tr>
                            <td colspan="6" id='${'formStatus_' + record.RecordId + '_' + event_id + '_' + formName}'></td>
                        </tr>
                        `;
                }
                output = output + "</table>";
                $('#transmission_progress_log').append( output );
            },
            error: function (error) {
                console.log("Error: " + error);
                // alert(error);
            }
        });
    }
    else {
        alert("Choose a form!");
    }
}

async function downloadForm(form_event_id_field, downloadRemote, num) {
    var formName, event_id;
    if (form_event_id_field !== '') {
        var arr = form_event_id_field.split("|");
        formName = arr[0];
        event_id = arr[1];
        eventName = arr[2];
        console.log("downloadForm form: " + formName + " remote? " + downloadRemote + " eventName: " + eventName)
        // let a = document.createElement("a");
        // document.body.appendChild(a);
        // a.style = "display: none";
        // openLoader($("#center"));
        await $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'downloadRemoteCsv', project_id: RTI.projectId, queryRemote: downloadRemote, formName: formName, eventName: eventName, num: num },
            success: function (result) {
                // closeLoader($("#center"));
                $('#dlExcelResults').html(result);
            },
            error: function (error) {
                console.log("Error: " + error);
                // alert(error);
            }
        });
    }
    else {
        alert("Choose a form!");
    }
}

async function forceUpload(record_id,projectId,formName, event_id, isBulk ) {
    await ForceUpload(record_id,projectId,formName,event_id,'sync', isBulk);
}

function initDashboard() {
    $.ajax({
        url: "transmit.php?pid=" + RTI.projectId,
        type: 'post',
        data: { action: 'check_connectivity', project_id: RTI.projectId },
        success: function (result) {
            if (result == "CONNECTED") {
                $("#connection_status").attr("src", "/redcap/redcap_v8.1.1/ExternalLinks/transmission/connected.png");
                $('.progress-label-2').text('Click button to update study structure.');
                $('.progress-label-files').text('Click button to update study files.');
                $('.progress-label-4').text('Click button to retrieve data.');
                $('.progress-label-6').text('Click button to enter Record ID and retrieve from the server.');
                $('.progress-label-force-sync').text('Click button to enter Record IDs to force sync to the server.');
                $('.progress-label-compare_remote_fields').text('Click button to compare field values from local and remote server.');
                $('.progress-label-download-excel').text('Click button to generate CSVs and compare field values from local and remote server.');
            } else {
                $("#connection_status").attr("src", "/redcap/redcap_v8.1.1/ExternalLinks/transmission/not_connected.png");
                $('.progress-label').text('REDCap sever cannot be reached: verify connectivity and reload page.');
                $('.progress-label-2').text('REDCap sever cannot be reached: verify connectivity and reload page.');
                $('.progress-label-files').text('REDCap sever cannot be reached: verify connectivity and reload page.');
                $('.progress-label-4').text('REDCap sever cannot be reached: verify connectivity and reload page.');
                $('.progress-label-6').text('REDCap sever cannot be reached: verify connectivity and reload page.');
                $("#transmit").attr("disabled", "disabled");
                $("#update_study").attr("disabled", "disabled");
                $("#setup_users").attr("disabled", "disabled");
                $("#retrieve_data").attr("disabled", "disabled");
                $("#retrieve_record_id").attr("disabled", "disabled");
            }
        }
    });

    $.ajax({
        url: "transmit.php?pid=" + RTI.projectId,
        type: 'post',
        data: { action: 'getcases', project_id: RTI.projectId },
        success: function (result) {
            RTI.pendingTransmissions  = JSON.parse(result)
            RTI.num_records = 0
            if (RTI.pendingTransmissions.length == 0) {
                $("#transmit").attr("disabled", "disabled");
                $('.progress-label').text('No data to transmit.');
            } else {
                var details = `<h4>Transmission Details:</h4>
                <span id="transmissionErrors"></span>
                <table class="transmission">
                <tr><th>Log Id</th><th>Record</th><th>Event</th><th>Status</th></tr>`;
                for (var record of RTI.pendingTransmissions) {
                    RTI.num_records++;
                    details = details + '<tr id="' + record.log_event_id + '-row"><td id="' + record.record_id + '-' + record.event_id + '-formInfo">' + record.log_event_id + '</td><td>' + record.record_id + '</td><td>' + record.event_id + '</td><td id="' + record.log_event_id + '-status">Pending</td></tr>\n';
                }
                details = details + '</table>';
                $('.progress-label').text('0 of ' + RTI.num_records + ' records transmitted');
                $('#transmission_progress_log').html(details);
            }
        }
    });
}

async function TransmitData(pendingTransmissions) {
    RTI.current_record = 0
    for (var record of pendingTransmissions) {
        RTI.current_record++
        const result = await SendPendingTransmission(record)
        .then(function(value) {
            console.log(value);
          })
        .catch(function(reason) {
            console.log(reason);
            $("#transmissionErrors").text(reason);
            $("#transmissionErrors").css('color', 'red');
        });
    }
}

async function SendPendingTransmission(record) {
    return new Promise(function (resolve, reject) {
        console.log('POSTING: record_id: ' + record.record_id + ' event_id: ' + record.event_id + ' dataValues: ' + JSON.stringify(record.dataValues) + ' RTI.current_record: ' + RTI.current_record + ' out of RTI.num_records: ' + RTI.num_records);
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'sendPendingTransmission', project_id: RTI.projectId, record: record},
            error: function (error) {
                console.log('Error: ' + JSON.stringify(error));
                alert('Error: ' + JSON.stringify(error));
                reject(error)
            },
            success: function (result) { // On success, update progress bar
                var error_occurred = false;
                var record_id = record.record_id;
                var event_id =  record.event_id;
                var transEl = $("#" + record.log_event_id + '-status')
                // If not an error, the result is number of records imported - usually 1.
                if (result.startsWith("ERROR")) {
                    error_occurred = true;
                    console.log('ERROR: ' + result + ' for record_id: ' + record.record_id + ' event_id: ' + record.event_id + ' dataValues: ' + JSON.stringify(record.dataValues) + ' RTI.current_record: ' + RTI.current_record + ' out of RTI.num_records: ' + RTI.num_records);
                    transEl.html(" Error");
                    transEl.css('color', 'red');
                    const errorMessage = result + ' for record_id: ' + record_id + ' event_id: ' + event_id + ' dataValues: ' + JSON.stringify(record.dataValues) + ' RTI.current_record: ' + RTI.current_record + ' out of RTI.num_records: ' + RTI.num_records;
                    reject(errorMessage)
                } else {
                    console.log('SUCCESS: record_id: ' + record.record_id + ' event_id: ' + record.event_id + ' dataValues: ' + JSON.stringify(record.dataValues) + ' RTI.current_record: ' + RTI.current_record + ' out of RTI.num_records: ' + RTI.num_records);
                    transEl.html(" Uploaded");
                }
                var progressbar = $("#progressbar"), progressLabel = $(".progress-label");
                    var val = progressbar.progressbar("value") || 0;
                    const successMessage = 'Transmitted record: ' + record.record_id + ' (' + RTI.current_record + ' of ' + RTI.num_records + ')';
                    progressLabel.text(successMessage);
                    progressbar.progressbar("value", val + parseFloat(100) / num_cases);
        
                    if (RTI.current_record == RTI.num_records) { // if we are done, then set "done" message
                        var transmit_message = "Transmission completed";
                        if (error_occurred) {
                            transmit_message = "Transmission completed with errors: please check log.";
                            progressLabel.text(transmit_message);
                        } else {
                            $.ajax({
                                url: "transmit.php?pid=" + RTI.projectId,
                                type: 'post',
                                data: { action: 'log_completion', project_id: RTI.projectId, message: transmit_message },
                                success: function (result) {
                                    progressLabel.text(transmit_message);
                                    resolve(successMessage + ". " + transmit_message);
                                    // return;
                                }
                            });
                        }
                    } else {
                        resolve(successMessage) 
                    }
            }
        })
    })
}

function RetrieveAndImportData(cases, batch_size, n, num_cases, error_occurred) {
    if (cases.length === 0) return;
    if (cases.length < batch_size) {
        batch_cases = cases;
    } else {
        batch_cases = cases.slice(0, batch_size);
        cases = cases.slice(batch_size);
    }
    $.each(batch_cases, function (index, record_id) {
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'retrieve', record_id: record_id, project_id: RTI.projectId },
            error: function (result) {
                alert('Error');
            },
            success: function (result) {
                n++;
                if (result.includes("ERROR")) {
                    error_occurred = true;
                }
                var progressbar = $("#progressbar4"), progressLabel = $(".progress-label-4");
                var val = progressbar.progressbar("value") || 0;
                progressLabel.text('Retrieved record: ' + record_id + ' (' + n + ' of ' + num_cases + ')');
                progressbar.progressbar("value", val + parseFloat(100) / num_cases);
                if (n % batch_size == 0) {
                    RetrieveAndImportData(cases, batch_size, n, num_cases, error_occurred);
                }
            }
        });
    });
}

function ShowTransmissionLog(results) {
    $('#transmission_log').html(results);
}

function showNext() {
    var beginLimit = $('#logs').find(":selected").val();
    console.log("beginLimit: " + beginLimit)
    openLoader($("#transmission_log_table"));

    $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'show_transmission_log', project_id: RTI.projectId, begin_limit:  beginLimit},
            success: function (result) {
                closeLoader($("#transmission_log_table"));
                ShowTransmissionLog(result);
            }
        });
}

/**
 * openLoader and closeLoader are used to (un)cover a target jQuery element
 * with a loading overlay. Resizing/positioning is done automatically.
 *
 * EXAMPLE:
 *
 * openLoader($("#container"));
 * $.ajax(
 *	url: foo.php
    *	complete: function() {
    *		closeLoader($("#container"));
    *	}
    * );
    */
openLoader =  function(target) {
    // create the overlay layer
    var overlay = $("<div></div>");
    overlay.addClass("redcapLoading");
    // insert the overlay into the target
    target.prepend(overlay);
    // make the overlay cover the target
    overlay.height(target.height());
    overlay.width(target.width());
    // create the loading spinner
    var spinner = $('<img src="' + app_path_images + 'loader.gif" />');
    var spinnerWidth = 220; // having trouble getting this dynamically
    spinner.addClass("redcapLoading");
    // insert the spinner into the overlay
    overlay.append(spinner);
    // position the spinner 30% down the overlay and in the center
    spinner.css({
        top: 200,
        left: Math.floor((overlay.width() - spinnerWidth) * 0.5)
    });
    overlay.show();
};

closeLoader =  function(target) {
    target.children(".redcapLoading").first().remove();
};

function syncAllRecords() {
    if (confirm("Sync All Records should only be used in rare situations Click OK to continue or cancel to stop.")) {
        openLoader($("#center"));
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'sync_all_records', project_id: RTI.projectId },
            success: function (result) {
                closeLoader($("#center"));
                var records = JSON.parse(result);
                if (records.length == 0) {
                    // $("#transmit").attr("disabled", "disabled");
                    $('.progress-label').text('No data to sync.');
                }
                else {
                    var details = `<h4>Records to Sync</h4>
                    <div id="syncButtons">
                        <button id="check_remote" class="btn btn-default btn-xs fs13">Check Remote</button>
                        <input type="checkbox" id="sync_all_missing"><label for="sync_all_missing">Sync all missing</label>
                        <input type="checkbox" id="sync_only_checkboxes"><label for="sync_only_checkboxes">Sync only Checkboxes</label>
                        <p>Enter "RTI.stopSync=true" in the js console to stop the sync while it is in progress. Either refresh the page or set the value to true to be able to resume.</p>
                    </div>
                    <div id="syncProgress"></div>
                    <table class="transmission">\n<tr><th>Record</th><th>Event</th><th>Forms</th></tr>`;
                    // Object.keys(records).forEach(function(key) {
                    for (const key of Object.keys(records)) {
                        var formsToString = "";
                        var record_id = key;
                        var events = records[key];
                        var event;
                        for (const key of Object.keys(events)) {
                            event = key;
                            formStatusObject = events[key];
                            var size = Object.keys(formStatusObject).length;
                            if (size > 0) {
                                var display = false;
                                formTable = "<table><tr><td id='recordEventStatus_" + record_id + "_" + event + "' colspan='3'></td></tr>\n<tr><th>Form</th><th>Status</th></tr>\n";
                                for (const key of Object.keys(formStatusObject)) {
                                    var form = key;
                                    var status = formStatusObject[key]["1"];
                                    if (status !== '') {
                                        display = true;
                                        // console.log("form: " + form + "status: " + JSON.stringify(status));
                                        formTable = formTable + '<tr><td id="form_' + form + '_' + event + '_' + record_id + '">' + form + '</td><td id="formStatus_' + record_id + '_' + event + '_' + form + '"></td></tr>\n';
                                    }
                                }
                                ;
                                formTable = formTable + '</table>';
                                if (display) {
                                    details = details + '<tr id="' + record_id + '-' + event + '-row"><td>' + record_id + '</td><td>' + event + '</td><td id="' + record_id + '-' + event + '-formInfo">' + formTable + '</td></tr>\n';
                                }
                            }
                        }
                    }
                    ;
                    details = details + '</table>';
                    $('.progress-label').text('0 of ' + RTI.num_records + ' records transmitted');
                    $('#transmission_progress_log').html(details);
                    $('#check_remote').click(async function () {
                        if (confirm("This will query the remote server record-by-record. Click OK to continue or cancel to stop.")) {
                            // $('.progress-label-files').text('Updating study files. Please be patient.');
                            try {
                                await CheckRemote(records, null, false);
                            } catch(err) {
                                console.log("ERROR: " + err)
                            }
                            if (!RTI.sync10buttonDisplayed) {
                                var sync10button = `<button id="sync_10_missing" class="btn btn-default btn-xs fs13">Sync 10 missing starting from </button><input type="text" id="sync_start" value="0" size="4">`;
                                $("#syncButtons").append(sync10button);
                                $('#sync_10_missing').click(async function () {
                                    if (confirm("This will force upload 10 missing forms. Click OK to continue or cancel to stop.")) {
                                        // fetch the next ten records
                                        var increment = 10;
                                        var start = parseInt($("#sync_start").val());
                                        var remoteResultsLength = RTI.remoteResults.length;
                                        console.log("remoteResultsLength: " + remoteResultsLength)
                                        if (RTI.remoteResults.length > start) {
                                            var end = start + increment
                                            if (remoteResultsLength < end) {
                                                end = remoteResultsLength + 1
                                            }
                                            console.log("ForceUpload starting at " + start + " end: " + end);
                                            var currentResults = RTI.remoteResults.slice(start, end)
                                            for (const remoteResult of currentResults) {
                                                if (remoteResult.missing.length > 0) {
                                                    for (const form of remoteResult.missing) {
                                                        console.log("force upload form: " + form);
                                                        await ForceUpload(remoteResult.record_id,remoteResult.projectId,form,remoteResult.event_id)
                                                     }
                                                }
                                            }
                                            $("#sync_start").val(end)
                                        }
                                    }
                                });
                                RTI.sync10buttonDisplayed = true
                            }
                        }
                    });
                }
            }
        });
    }
}

function CompareLocalToRemote(records, formId) {
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'sync_all_records', project_id: RTI.projectId, records: records },
            success: function (result) {
                var records = JSON.parse(result);
                if (records.length == 0) {
                    // $("#transmit").attr("disabled", "disabled");
                    $('.progress-label').text('No data to sync.');
                }
                else {
                    var details = `<h4>Records to Sync</h4>
                    <p>Important: this process will change any values on the server that are different from the local redcap instance. 
                    If you're running this process from a tracker, it would erase data uploaded from other trackers. </p>
                    <div id="syncButtons">
                        <button id="check_remote_force" class="btn btn-default btn-xs fs13">Check Remote</button>
                        <input type="checkbox" id="sync_all_missing_fields">Sync all missing/unsync'd fields
                    </div>
                    <div id="syncProgress"></div>
                    <table class="transmission">\n<tr><th>Record</th><th>Event</th><th>Forms</th></tr>`;
                    // Object.keys(records).forEach(function(key) {
                    for (const key of Object.keys(records)) {
                        var formsToString = "";
                        var record_id = key;
                        var events = records[key];
                        var event;
                        for (const key of Object.keys(events)) {
                            event = key;
                            formStatusObject = events[key];
                            var size = Object.keys(formStatusObject).length;
                            if (size > 0) {
                                var display = false;
                                formTable = "<table><tr><td id='recordEventStatus_" + record_id + "_" + event + "' colspan='3'></td></tr>\n<tr><th>Form</th><th>Status</th></tr>\n";
                                for (const key of Object.keys(formStatusObject)) {
                                    var form = key;
                                    if (form === formId) {
                                        var status = formStatusObject[key]["1"];
                                        if (status !== '') {
                                            display = true;
                                            // console.log("form: " + form + "status: " + JSON.stringify(status));
                                            formTable = formTable + '<tr><td id="form_' + form + '_' + event + '_' + record_id + '">' + form + '</td><td id="formStatus_' + record_id + '_' + event + '_' + form + '"></td></tr>\n';
                                        }
                                    }
                                }
                                ;
                                formTable = formTable + '</table>';
                                if (display) {
                                    details = details + '<tr id="' + record_id + '-' + event + '-row"><td>' + record_id + '</td><td>' + event + '</td><td id="' + record_id + '-' + event + '-formInfo">' + formTable + '</td></tr>\n';
                                }
                            }
                        }
                    }
                    ;
                    details = details + '</table>';
                    $('.progress-label').text('0 of ' + RTI.num_records + ' records transmitted');
                    $('#transmission_progress_log').html(details);
                    $('#check_remote_force').click(async function () {
                        if (confirm("This will query the remote server record-by-record. Click OK to continue or cancel to stop.")) {
                            // $('.progress-label-files').text('Updating study files. Please be patient.');
                            try {
                                var fieldTableEls= document.querySelectorAll('.comparison-table');
                                fieldTableEls.forEach((fieldTableEl) => {
                                    fieldTableEl.parentNode.removeChild(fieldTableEl);
                                  });
                                var  checkAllFields = true;
                                await CheckRemote(records, formId,  checkAllFields);
                            } catch(err) {
                                console.log("ERROR: " + err)
                            }
                        }
                    });
                }
            }
        });
}

async function CheckRemote(records, formId, checkAllFields) {
    RTI.remoteResults = [];
    for (const key of Object.keys(records)) {
        var record_id = key;
        var events = records[key];    
        if (RTI.stopSync === true) {
            console.log("Stopping Sync at " + record_id)
            break;
        }
        for (const key of Object.keys(events)) {
            var event = key;
            if (RTI.stopSync === true) {
                console.log("Stopping Sync at " + record_id + " event id: " + event)
                break;
            }
            formStatusObject = events[key];
            var size = Object.keys(formStatusObject).length;
            if (size > 0) {
                var formsToCheck = [];
                var display = false;
                for (const key of Object.keys(formStatusObject)) {
                    var form = key;
                    var status = formStatusObject[key]["1"];
                    if (status !== '') {
                        display = true;
                        var processRecord = false;
                        if (formId) {
                            if (form === formId) {
                                processRecord = true; 
                            }
                        }
                        if (!formId) {
                            processRecord = true;  
                        }
                        if (processRecord) {
                            formsToCheck.push(form);
                            // $('#formStatus_' + record_id + '_' + event + '_' + form).text("Processing.");
                        }
                    }
                }
                // formTable = formTable + '</table>';
                if (display) {
                    if (formsToCheck.length > 0) {
                        $('#syncProgress').text("Processing record " + record_id + " for event " + event + ". Form(s): " + formsToCheck);
                        try {
                            var remoteResult = await CheckRemoteRecord(RTI.projectId, record_id, event, formsToCheck,  checkAllFields, false)
                            if (remoteResult.missing.length > 0) {
                                if (! $('#sync_all_missing').prop('checked')) {
                                    RTI.remoteResults.push(remoteResult);
                                }
                                console.log("missing" + JSON.stringify(remoteResult.missing))
                                // check value of sync_all_missing
                                if ($('#sync_all_missing').prop('checked')) {
                                    var checkboxesOnly = false
                                    if ($('#sync_only_checkboxes').prop('checked')) {
                                        checkboxesOnly = true
                                    }
                                    // loop thrugh forms and pass on to force upload
                                    for (const form of remoteResult.missing) {
                                        console.log("force upload missing form: " + form);
                                        await ForceUpload(remoteResult.record_id,remoteResult.projectId,form,remoteResult.event_id, null, null, checkboxesOnly)
                                        // .catch((err) => { 
                                        //     const error = JSON.stringify(err);
                                        //     console.log(error); 
                                        //     $('#syncProgress').text("ERROR uploading record " + record_id + " for event " + event + " form " + form + " error: " + error);
                                        // });
                                    }
                                }
                            }
                            if (checkAllFields) {
                                if (remoteResult.fieldList.length > 0) {
                                    // check value of sync_all_missing_fields
                                    if ($('#sync_all_missing_fields').prop('checked')) {
                                        console.log("force upload missing form: " + formsToCheck[0]);
                                        let uploadProgressDiv = `checkRemote-${formsToCheck[0]}-${remoteResult.event_id}-${remoteResult.record_id}`
                                        await ForceUpload(remoteResult.record_id,remoteResult.projectId,formsToCheck[0],remoteResult.event_id,uploadProgressDiv,true,remoteResult.fieldList)
                                    }
                                }
                            }
                            if ($('#sync_only_checkboxes').prop('checked')) {
                                // loop thrugh forms and pass on to force upload
                                for (const form of formsToCheck) {
                                    console.log("force upload checkboxes form: " + form);
                                    await ForceUpload(remoteResult.record_id,remoteResult.projectId,form,remoteResult.event_id, null, null, null, true)
                                }
                            }

                        } catch(err) {
                            console.log("ERROR: " + JSON.stringify(err))
                        }
                    }
                }
            }
        }
    }
    return { record_id, events, event, size, display, form, status, event, display };
}

async function CheckRemoteRecord(projectId, record_id, event_id, forms,  checkAllFields, showOnlyMissingFields) {
    return new Promise(function (resolve, reject) {
        // Scroll to this item in the list
        // var rowEl = $('#' + record_id + '-' + event_id + '-row');
        // $('html,body').animate({scrollTop: rowEl.offset().top});
        console.log('CHECKING REMOTE RECORD: record_id: ' + record_id + ' event_id: ' + event_id + ' forms: ' + JSON.stringify(forms));
        $.ajax({
            url: "transmit.php?pid=" + RTI.projectId,
            type: 'post',
            data: { action: 'check_remote_record', project_id: projectId, recordId: record_id, eventId: event_id, forms: forms, checkAllFields: checkAllFields},
            error: function (error) {
                console.log('Error: ' + JSON.stringify(error));
                // alert('Error: ' + JSON.stringify(error));
                var errorEl = $('#recordEventStatus_' + record_id + '_' + event_id);
                errorEl.css('color', 'red');
                errorEl.html("Error: " + error.statusText);
                reject(error)
            },
            success: function (result) { // On success, update progress bar
                var results = JSON.parse(result);
                var submitted = results.submitted;
                var missing = results.missing;
                var createRecord = results.createRecord;
                var circle_green = '<img src="' + app_path_images + 'circle_green.png" style="height:16px;width:16px" />';
                var icon_question= '<img src="' + app_path_images + 'default/window/icon_question.gif" style="height:16px;width:16px" />';
                if (missing.length > 0) {
                    console.log('MISSING RESULTS: record_id: ' + record_id + ' event_id: ' + event_id + ' MISSING: ' + JSON.stringify(missing));
                }
                if (submitted.length > 0) {
                    console.log('SUBMITTED RESULTS: record_id: ' + record_id + ' event_id: ' + event_id + ' SUBMITTED: ' + JSON.stringify(submitted));
                }
                if (createRecord === true) {
                    console.log('CREATED RECORD: Creating record for: record_id: ' + record_id + ' event_id: ' + event_id + ' SUBMITTED: ' + JSON.stringify(submitted));
                    var formStatusEl = $("#recordEventStatus_" + record_id + "_" + event_id);
                    formStatusEl.html('Creating record: pushing all forms.')
                    formStatusEl.css('color', 'red');
                    var created = results.created;
                    for (const item of created) {
                        var form = item.formName;
                        var formStatusEl = $('#formStatus_' + record_id + '_' + event_id + '_' + form)
                        formStatusEl.html(circle_green)
                        formStatusEl.css('color', 'red');
                    }
                }
                var missingForms = [];
                // var submittedForms = [];
                for (const item of missing) {
                    var form = item.formName;
                    missingForms.push(form);
                    var formStatusEl = $('#formStatus_' + record_id + '_' + event_id + '_' + form)
                    formStatusEl.html('Missing')
                    formStatusEl.css('color', 'red');
                }
                for (const item of submitted) {
                    var form = item.formName;
                    // submittedForms.push(form);
                    var formStatusEl = $('#formStatus_' + record_id + '_' + event_id + '_' + form)
                    formStatusEl.html(circle_green)
                }
                if (checkAllFields) {
                    // form_' + form
                    const remoteResults = results.remoteResults[0]
                    let remoteEntries;
                    if (typeof remoteResults !== 'undefined') {
                        remoteEntries = Object.entries(remoteResults)
                    }
                    const localResults = results.localResults[0]
                    const localEntries = Object.entries(localResults)
                    var fieldList = []
                    let table = "<table class='comparison-table'><tr><th>Field</th><th>Local</th><th>Remote</th></tr>\n"
                    if (typeof remoteEntries !== 'undefined') {
                        for (const [field, remoteValue] of remoteEntries) {
                            let localValue = localResults[field]
                            // var response = "<p>" + field + ":" + value + "</p>\n";
                            var localDisplay = ""
                            var remoteDisplay = ""
                            var rowClass = ""
                            localDisplay = (typeof localValue !== 'undefined' && localValue !== '')? localValue:"empty"
                            remoteDisplay = (typeof remoteValue !== 'undefined' && remoteValue !== '')? remoteValue:"empty"
                            var cssStyle = "no_bgcolor"
                            if (localDisplay !== remoteDisplay) {
                                cssStyle = "red_highlight"
                                fieldList.push(field)
                            }
                            if (!(localDisplay === 'empty' && remoteDisplay === 'empty')) {
                                if (showOnlyMissingFields && cssStyle === "no_bgcolor") {
                                    // Don't display fields which match. 
                                } else {
                                    table = table + `<tr class='${cssStyle}'><td>${field}</td><td>${localDisplay}</td><td>${remoteDisplay}</td></tr>\n`
                                }
                            }
                            // submittedForms.push(form);
                        }
                    }
                    // add force-upload-status- field
                    table = table + `<tr><td colspan="3" id="force-upload-status-checkRemote-${form}-${event_id}-${record_id}"></td></tr>`
                    table = table + `</table>`
                    var formStatusEl = $('#form_' + form + '_' + event_id + '_' + record_id)
                    formStatusEl.append(table)
                }
                var remoteResult = {
                    projectId: projectId,
                    record_id: record_id,
                    event_id: event_id,
                    missing: missingForms,
                    fieldList: fieldList
                }
                resolve(remoteResult);
            }
        }).always(function() {
            // console.log( "complete" );
        });
    });
}