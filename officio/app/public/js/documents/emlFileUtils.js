function showTabWasClosed() {
    alert('Parent Officio page was closed. Please close this page and try again.');
}

function showPleaseSwitchToTab() {
    alert('Email dialogue box will open in your main Officio browser tab. Please switch to Officio tab to continue.');
}

function thisPrintEml(obj) {
    if (typeof printEmlFile === 'function') {
        printEmlFile(obj);
    } else {
        if (window.opener) {
            if (!window.opener.closed) {
                window.opener.printEmlFile(obj);
            } else {
                showTabWasClosed();
            }
        } else if (window.parent) {
            window.parent.printEmlFile(obj);
        }
    }

    return false;
};

function thisForwardEmlFile(obj) {
    if (typeof forwardEmlFile === 'function') {
        forwardEmlFile(obj);
    } else {
        if (window.opener) {
            if (!window.opener.closed) {
                window.opener.forwardEmlFile(obj);
                showPleaseSwitchToTab();
            } else {
                showTabWasClosed()
            }
        } else if (window.parent) {
            window.parent.forwardEmlFile(obj);
        }
    }

    return false;
};

function thisReplyEmlFile(obj, booReplyAll) {
    if (typeof replyEmlFile === 'function') {
        replyEmlFile(obj, booReplyAll);
    } else {
        if (window.opener) {
            if (!window.opener.closed) {
                window.opener.replyEmlFile(obj, booReplyAll);
                showPleaseSwitchToTab();
            } else {
                showTabWasClosed()
            }
        } else if (window.parent) {
            window.parent.replyEmlFile(obj, booReplyAll);
        }
    }

    return false;
};

function thisSaveAttachments(file_id, destination, file_name) {
    if (typeof saveEmlFileAttachment === 'function') {
        saveEmlFileAttachment(file_id, destination, file_name);
    } else {
        if (window.opener) {
            if (!window.opener.closed) {
                window.opener.saveEmlFileAttachment(file_id, destination, file_name);
            } else {
                showTabWasClosed()
            }
        } else if (window.parent) {
            window.parent.saveEmlFileAttachment(file_id, destination, file_name);
        }
    }

    return false;
};