var showTimeTracker = function(clientId) {
    var divId = 'timeTrackerTabForm-' + clientId;

    var el = $('#' + divId);
    if(el.length) {
        // Clear loading image
        el.empty();

        // Generate panel
        new ClientTrackerPanel({
            booCompanies: false,
            clientId: clientId,
            renderTo: divId,
            autoWidth: true,
            height: 600
        });
    }
};