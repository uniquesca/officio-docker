/**
 * This js file is used in Uniques PDF forms (barcoded)
 * (when user can fill the form and submit data to server)
 *
 * @version 1.0.0
 */

// 1. Set up form1::ready:layout
// officioUtils.showMessageOnReady();

// 2. In 'color' or 'Utils_Color_Engine' javascript module find function markRequired
// and insert such code at the beginning:
//    if(officioUtils.skipValidation) {
//        return true;
//    }

var skipValidation = false;

function isUrl(someString) {
    var regexp = /(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?/
    return (regexp.test(someString));
}

function showMessageOnReady() {
    var errorMessage = form1.Page1.Header.Officio.OfficioErrorMessage.rawValue;
    if(errorMessage != null && errorMessage !== '') {
        if(form1.Page1.Header.Officio.OfficioErrorCode.rawValue == 0) {
            icon = 3;
        } else {
            icon = 0;
        }
    
        app.alert({
            cMsg: errorMessage,
            nIcon: icon,
            cTitle: "Message from Server"
        });
        // Show only once
        form1.Page1.Header.Officio.OfficioErrorMessage.rawValue = '';
    }
}

function submitFilledForm() {
    try{
        if(!officioUtils.isUrl(form1.Page1.Header.Officio.OfficioSubmitURL.rawValue)) {
            app.alert('Officio data was not loaded successfully.');
        } else {
            // Mark all fields as not required - to make possible submit them
            officioUtils.ProcessAllFields(form1);
            
            // Prevent mark other fields as required
            officioUtils.skipValidation = true;
            
            // Submit the form
            form1.Page1.Header.Officio.OfficioErrorCode.rawValue = 0;
            form1.Page1.Header.Officio.OfficioErrorMessage.rawValue = '';
            
            var oSubmit = form1.Page1.Header.Officio.OfficioSubmitFormHidden.resolveNode("$..#submit");
            
            // Update attributes
            oSubmit.target = form1.Page1.Header.Officio.OfficioSubmitURL.rawValue;
            
            oSubmit.textEncoding = "UTF-8";
            oSubmit.format = "xdp";
            oSubmit.xdpContent = "*";
            
            // Force the Submit Action to Run by calling the Click
            // event on the hidden submit button
            form1.Page1.Header.Officio.OfficioSubmitFormHidden.execEvent("click");
        }
    }
    catch(e)
    {
        console.show();
        console.println(e.toString());
        throw e;
    }
}

// Returns true if the field is mandatory and is not filled.
function IsRequired(oField)
{
    if (oField.validate.nullTest == "error" && (oField.rawValue == null || oField.rawValue.length === 0))
        return true; // mandatory field
    // If the field's parent is an exclusion group, the exclusion group's mandatory setting applies to all fields it contains.
    if (oField.parent.className == "exclGroup" && oField.parent.validate.nullTest == "error" && (oField.parent.rawValue == null || oField.parent.rawValue.length === 0))
        return true; // parent is a mandatory exclusion group (e.g. radio button list)
    
    if (oField.ui.oneOfChild.className == "checkButton" && oField.validate.nullTest == "error")
    {
        // Check boxes always have a default value (so it will be null neither will it be an empty string).
        //  If a check box is mandatory, we will assume that means its value should either be "on" or "neutral" but not "off".
        // (Note that while check boxes can't be made mandatory via Designer's UI, they can be via script: See the Initialize script of the
        //  CheckBox3 field.)
        
        // By definition (in the XFA 2.2 spec), the "off" value is the second value in the first <items> child node.
        
        var oItemsList = oField.resolveNodes("#items"); // note the "#" prefix to access the <items> child nodes explicitely

        if (oItemsList.length > 0)
        {
            var oValueList = oItemsList.item(0).nodes;

            if (oValueList.length > 1)
            {
                var sOffValue = oValueList.item(1).value;
                
                if (oField.rawValue == sOffValue)
                    return true; // found a mandatory check box whose value is still set to "off"
            }
        }
    }

    return false;
}

function markFieldAsOptional(oField) {
    oField.mandatory = "disabled";
    oField.access = "readOnly";
}

function ProcessAllFields(oNode) {
    if (oNode.className == "exclGroup" || oNode.className == "subform"  || oNode.className == "subformSet" || oNode.className == "area")
    {
        // Look for child fields.
        for (var i = 0; i < oNode.nodes.length; i++) {
            var oChildNode = oNode.nodes.item(i);
            officioUtils.ProcessAllFields(oChildNode);
        }
    }
    else if (oNode.className == "field" )
    {
        officioUtils.markFieldAsOptional(oNode);
        
        if(oNode.ui.oneOfChild.className == "checkButton") {
            officioUtils.markFieldAsOptional(oNode.parent);
        }
    }
}
