$(document).ready(function() {
    
    
    // validate signup form on keyup and submit
    var validator = $("#editSubofficeForm").validate({
        rules: {
            companyID: {
                required: true
            },
            subOfficeName: {
                required: true
            },
            address: {
                required: true
            },
            city: {
                required: true
            },
            state: {
                required: true
            },
            country: {
                required: true
            },
            contact: {
                required: true
            }
        },

        /*
        submitHandler: function() { 
            alert("submitted!"); 
        },
        */         
        
        // set this class to error-labels to indicate valid fields
        success: function(label) {
            // set   as text for IE
            label.html(" ").addClass("checked");
            
        }
    });

}); 
