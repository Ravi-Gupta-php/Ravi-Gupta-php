/**
 * Uploads a form to the remote server. Sends status to $('#force-upload-status-' + divId);
 * ForceUpload(record,project_id,instrument,event_id, divId)
 * @param {*} record 
 * @param {*} project_id 
 * @param {*} instrument 
 * @param {*} event_id 
 * @param {*} divId - if undefined, sends messages to $('#formStatus_' + record_id + '_' + event_id + '_' + instrument)
 * @param {*} isBulk - if null/undefined, will display confirmation message
 * @param {*} fieldList
 * @param {*} checkboxesOnly - upload only checkboxes
 */
function ForceUpload(record, project_id, instrument, event_id, divId, isBulk, fieldList, checkboxesOnly) {

    function showConfirm(divId) {
        var response = false;
        if (divId && (typeof isBulk === 'undefined' || isBulk === null)) {
            response = confirm('This will force an update for this form of this record to the server. Click OK to continue or cancel to stop.');
        } else {
            response = true;
        }
        return response;
    }

    console.log("ForceUpload record: " + record + " project_id: " + project_id + " formName: " + instrument + " event_id: " + event_id);
    $('#force-upload-status').text('Force Upload Status');
    var proceed = false;
    if (showConfirm(divId)) {
        console.log('Uploading ' + record);
        $.ajax({
           // url: '/redcap/rti/transmission/transmit.php?pid=' + project_id,

           // new code ravi kumar
           url: '/redcap/redcap_v8.1.1/ExternalLinks/transmission/transmit.php?pid=' + project_id,
            type: 'post',
            data: { 'action': 'transmitForm', 'record_id': record, 'project_id': project_id, 'forms': [instrument], 'event_id': event_id, 'logEvents': null, 'fieldList': fieldList, 'checkboxesOnly': checkboxesOnly},
            error: function (error) {
                const errorMessage = ' Error uploading ' + instrument + ' for record ' + record + ' event_id: ' + event_id + ' ERROR: ' + error;
                console.log('Error: ajax error ' + JSON.stringify(errorMessage));
                // alert('Error: ' + JSON.stringify(error));
            },
            success: function (result) { 
                var error_occurred = false;
                var info = false;
                // console.log('Done Uploading ' + record + ', Result: ' + result);
                if (result.includes('ERROR')) {
                    error_occurred = true;
                }
                if (result.includes('[INFO:')) {
                    info = true;
                }
                // this function is used in different tables, so adjust the target for transEl:
                var transEl;
                if (typeof divId !== 'undefined' && divId !== null) {
                    transEl = $('#force-upload-status-' + divId);
                } else {
                    transEl = $('#formStatus_' + record + '_' + event_id + '_' + instrument);
                }
                // var formStatusEl = $('#formStatus_' + record_id + '_' + event_id + '_' + instrument)
                if (error_occurred === true) {
                    let errorMessage = '<p> Error uploading ' + instrument + ' for record ' + record + ' event_id: ' + event_id + ' ERROR: ' + result + '</p>';
                    console.log(errorMessage);

                    if(errorMessage.length > 300) {
                        errorMessage = errorMessage.substring(0,299)+" ...";
                    }
                    transEl.css('color', 'red');
                    transEl.append(errorMessage);
                } else {
                    console.log('SUCCESS: record_id: ' + result + ' for record_id: ' + record + ' event_id: ' + event_id +  ' form: ' + instrument);
                    transEl.css('color', 'blue');
                    if (info) {
                        transEl.append('<p>' + result + '</p>');
                    } else {
                        transEl.append(' Uploaded ' + instrument + ' for ' + record + '.<br/>');
                    }
                }
            }
        });
    }
}