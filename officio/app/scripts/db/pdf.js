/**
 * This js file is used in Uniques PDF files
 * (when user can fill the form and submit data to server)
 *
 * @version 1.3.4
 */
 
// These fields names are internal and can be changed (both in pdf and in js)
var srvSubmitBtn = 'server_submit_button'; // MUST BE CREATED MANUALLY

// These fields names are global and cannot be changed
var srvUrlField          = 'server_url'; // MUST BE CREATED MANUALLY
var srvAssignedIdField   = 'server_assigned_id'; // MUST BE CREATED MANUALLY
var srvLockedForm        = 'server_locked_form'; // MUST BE CREATED MANUALLY
var srvResultField       = 'server_result';
var srvResultCodeField   = 'server_result_code';
var srvCheckXfdfField    = 'server_xfdf_loaded'; // MUST BE CREATED MANUALLY
var srvFormVersion       = 'server_form_version'; // MUST BE CREATED MANUALLY
var srvConfirmationField = 'server_confirmation'; // MUST BE CREATED MANUALLY
var srvFormTimeStamp     = 'server_time_stamp'; // MUST BE CREATED MANUALLY

var timeoutSubmit;
var timeoutConfirmationHide;

var attemptSubmit;
var attemptXFDF;
var timeoutCheckXFDF;
var maxAttemptsCount = 10;
var checkInterval = 1000;

// Set to true if you want see debug messages in console
var DEBUG = false;

var inch = 72;

function debug(text) {
    if(DEBUG) {
        console.println(text);
    }
}

if (this.external) {
    // Viewing from a browser
    disableSubmit();
    
    // Start checking if xfdf was received
    attemptXFDF = 1;
    timeoutCheckXFDF = app.setInterval("checkReceivedXFDF()", checkInterval);
} else {
    enableSubmit();
}


function showMessage(strFieldName, strMessage) {
    if(strMessage != '') {
        var oFld = this.getField(strFieldName);
        if(oFld) {
            oFld.value = strMessage;
            oFld.display = display.visible;
        } else {
            for (var p = 0; p < this.numPages; p++) {
                var aRect = this.getPageBox( {nPage: p} );
                var width = aRect[2] - aRect[0];
                var heigth = aRect[1] - aRect[3];
                var msgWidth = 4*inch;
                var msgHeight = 24;

                aRect[0] += (width / 2) - (msgWidth/2); // x1
                aRect[1] -= 30; // y1
                aRect[2] = aRect[0] + msgWidth; // x2
                aRect[3] = aRect[1] - msgHeight;// y2

                var f = this.addField(strFieldName, "text", p, aRect);
                f.delay = true;
                f.value = strMessage;
                f.readonly = true;
                f.textColor = color.white;
                f.fillColor = ["RGB", 0.25098, 0.25098, 0.66667];
                f.alignment = "center";
                f.delay = false;
            }
        }
    }
}


var confirmationMsgField = 'server_confirmation_message_dialog';

function showConfirmation() {
    var confirmationMsg = this.getField(srvConfirmationField).value;
    showMessage(confirmationMsgField, confirmationMsg);

    // Automatically hide for 4 seconds
    timeoutConfirmationHide = app.setInterval("hideConfirmation()", 4000);
}

function hideConfirmation() {
    var oFld = this.getField(confirmationMsgField);
    
    if(oFld) { 
        oFld.display = display.hidden;
        app.clearInterval(timeoutConfirmationHide);
    }
}


var waitMsgField = 'srv_tmp_status';
function showPleaseWait(txtMsg) {
    debug('Show please wait');  
    
    if(txtMsg == null)
        txtMsg = "Processing, please wait...";

    showMessage(waitMsgField, txtMsg);
}

function updatePleaseWait() {
    debug('Update please wait');
    var oFld = this.getField(waitMsgField);
    if(oFld) {
        oFld.value += '.';
    }
}

function hidePleaseWait() {
    debug('Hide please wait');
    var oFld = this.getField(waitMsgField);
    if(oFld) { 
        oFld.display = display.hidden;
    }
}

function hideSubmitButton() {
    debug('Hide submit button');
    var oFld = this.getField(srvSubmitBtn);
    if(oFld) { 
        oFld.display = display.hidden;
    }
}

// Function to enable form fields
function enableFormFieldFromGray(cFieldName)
{// First acquire the field that will be enabled
   var oFld = this.getField(cFieldName);
   if(oFld)
   { // Make field interactive  
     oFld.display = display.noPrint;
     oFld.readonly = false;
     
     // Restore Normal Colors
     oFld.fillColor = ["RGB", 0.25098, 0.25098, 0.66667];
     oFld.borderColor = ["RGB", 1, 1, 1];
     oFld.textColor = ["RGB", 1, 1, 1];
   }
}

// Function to disable form fields
function disableFormFieldToGray(cFieldName) {
// First acquire the field that will be disabled
   var oFld = this.getField(cFieldName);
   if(oFld)
   { 
     oFld.display = display.noPrint;
     
     // Make field Read-Only
     oFld.readonly = true;

     // Set Grayed out colors
     oFld.fillColor = ["G", 0.75];
     oFld.borderColor = ["G", 2/3];
     oFld.textColor = ["G", 0.5];
   }
}

function disableSubmit() {
    debug('Disable submit');
    disableFormFieldToGray(srvSubmitBtn);
}

function enableSubmit(){
    debug('Enable submit');
    enableFormFieldFromGray(srvSubmitBtn);
}

function showLockedMessage() {
    app.alert('The forms are locked by the office. If you need to make any changes, please contact them for assistance.'); 
}

function showFormVersion() {
    debug('Show version');
    var version = '';
    var fieldVersion = this.getField(srvFormVersion);
    if(fieldVersion)
        version = fieldVersion.value;
        
    if (version != '') {
        // Delete version of assigned pdf form
        this.removeField("tmp_form_version");
        
        debug('Create version field');
        debug(version);
        // Coordinates for new field
        var aRect = this.getPageBox( {nPage: 0} );
        var width  = aRect[2] - aRect[0];
        var height = aRect[1] - aRect[3];
        var msgWidth = 2*inch;
        var msgHeight = 15;
        aRect[0] = aRect[2] - msgWidth; // x1
        //aRect[1] = aRect[1]; // y1
        aRect[2] = aRect[0] + msgWidth; // x2
        aRect[3] = aRect[1] - msgHeight;// y2
        
        f = this.addField( 'tmp_form_version', "text", 0, aRect ); 
        f.delay = true;
        f.value = version;
        f.readonly = true;
        f.alignment = "right";
        f.textSize = 6;
        f.textColor = color.red;
        f.delay = false;
    }
}

function checkReceivedXFDF(){
    debug('checkReceivedXFDF');

    var oFieldXfdf = createField(this, srvCheckXfdfField);
    switch(oFieldXfdf.value) {
        case 1:
            // Xfdf received, form can be submitted
            enableSubmit();
            showFormVersion();
            
            // Show confirmation if needed
            showConfirmation();
            
            // Automatically hide for 3 seconds
            timeoutConfirmationHide = app.setInterval("hideConfirmation()", 3000);
            
            app.clearInterval(timeoutCheckXFDF);
            break;
        case 2:
            // Xfdf received, but form cannot be submitted
            disableSubmit();
            showFormVersion();
            
            // Show message
            showLockedMessage();
            app.clearInterval(timeoutCheckXFDF);
            break;
        default:
            // Xfdf not received
            if(attemptXFDF >= maxAttemptsCount) {
                // Timeout
                disableSubmit();
                app.alert('Previously saved information was not properly loaded. Please check your Internet connection and try again.'); 
                app.clearInterval(timeoutCheckXFDF);
            }
            attemptXFDF++;
    }
}
// Create field if it was not created before 
function createField(doc, fieldName) {
    var oField; 
    try { 
        oField  = doc.getField(fieldName); 
    } catch(e) {}

    if(oField == null) {
        // Coordinates for new field
        var aRect = new Array();
        aRect[0] = 1; aRect[1] = 1;
        aRect[2] = 2; aRect[3] = 2;
             
        oField = doc.addField( fieldName, "text", 0, aRect ); 
        oField.hidden = true; 
    }
    
    return oField;
}

var loginDialog = {
    validate : function(dialog) { // Check info before submitting
        var results = dialog.store();
        if(results["flog"] == '') { 
            app.alert('Please enter your Officio username.'); 
            return false; 
        }
        
        if(results["fpas"] == '') { 
            app.alert('Please enter your Officio password.'); 
            return false; 
        }
        
        return true;
    },
    
    commit : function(dialog) { // called when OK pressed
        var results = dialog.store();

        // Get/create fields
        var oFieldLogin = createField(this.doc, "server_login"); 
        var oFieldPass = createField(this.doc, "server_pass"); 

        // Update values 
         oFieldLogin.value = results["flog"]; 
         oFieldPass.value = results["fpas"];
        
        // Submit info
        _submit();
    },

    description : {
        name : "Please enter login information", // Dialog box title
        align_children : "align_left",
        width : 350,
        height : 200,
        elements : [{
            type : "cluster",
            name : "Login to Officio to save",
            align_children : "align_left",
            elements : [{
                type : "view",
                align_children : "align_row",
                elements : [{
                    type : "static_text",
                    width : 100,
                    name : "Username: "
                }, {
                    item_id : "flog",
                    type : "edit_text",
                    alignment : "align_fill",
                    width : 300,
                    height : 20
                }]
            }, {
                type : "view",
                align_children : "align_row",
                elements : [{
                    type : "static_text",
                    width : 100,
                    name : "Password: "
                }, {
                    item_id : "fpas",
                    type : "edit_text",
                    password : true,
                    alignment : "align_fill",
                    width : 300,
                    height : 20
                }]
            }]
        }, {
            alignment : "align_right",
            type : "ok_cancel",
            ok_name : "OK",
            cancel_name : "Cancel"
        }]
    }
};

function _stopChecking() {
    debug('stop checking');
    try {
        app.clearInterval(timeoutSubmit);
        hidePleaseWait();
    } catch (e){}
}

function checkReceivedInfo(){
    debug('check received info');
    var result = this.getField(srvResultField).value;
    var resultCode = this.getField(srvResultCodeField).value;
    
    if(result != '') {
        // Received result
        _stopChecking();
        if(resultCode == 0) {
            icon = 3;
        } else {
            icon = 0;
        }

        var xfdf_result = this.getField(srvCheckXfdfField).value;
        if(xfdf_result == 1)
            enableSubmit();
        
        app.alert({
            cMsg: result,
            nIcon: icon,
            cTitle: "Message from Server"
        });
    } else {
        // Still there is no result
        debug("Attempt:" + attemptSubmit);
        if(attemptSubmit == maxAttemptsCount) {
            // Timeout
            _stopChecking();

            enableSubmit();
            app.alert({
                cMsg: 'Cannot send/receive information. Please try again later.',
                cTitle: "Timeout"
            });
        } else {
            attemptSubmit++;
            updatePleaseWait();
        }
    }
}

function _submit() {
    var url = this.getField(srvUrlField).value;
    
    if (url != '') {
        // Show Progress Indicator
        debug("Start submitting");
        
        
        // Prepare fields
        var oFieldResult = createField(this, srvResultField);
        var oFieldResultCode = createField(this, srvResultCodeField);
        oFieldResult.value = '';
        oFieldResultCode.value = '';
        
        // Reset result field
        var oFieldXfdf = createField(this, srvCheckXfdfField);
        oFieldXfdf.value = 0;
        
        attemptSubmit = 1;
        
        // Check for server response
        if(timeoutSubmit != null) {
            _stopChecking();
        }
        showPleaseWait("Saving to Officio. Please wait...");
        
        timeoutSubmit = app.setInterval("checkReceivedInfo()", checkInterval);
        
        disableSubmit();

        var oFieldGen = createField(this, "server_generate");
        oFieldGen.value = Math.random();

        this.submitForm({
            cURL : url,
            cSubmitAs : "XFDF",
            bAnnotations: true,
            bEmpty:true  
        });
    } else {
        app.alert('URL and Client Login is not set. Please login to Officio first and download the PDF form for a specific client, then you will be able to save the changes to the same client.');
    }
}

function submitInfo() {
    if (this.external) {
        // Viewing from a browser
        _submit();
    } else {
        // Viewing in the Acrobat application.
        var booSubmit = true;
        
        var oFld = this.getField(srvLockedForm);
        if(oFld) {
            var form_locked = this.getField(srvLockedForm).value;
            if(form_locked != '') {
                // Form is locked, show alert message
                showLockedMessage();
                booSubmit = false;
            }
        }
        
        if(booSubmit) {
            loginDialog.doc = this;
            app.execDialog(loginDialog);
        }
    }
}

