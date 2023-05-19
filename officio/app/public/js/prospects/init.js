function initProspectsPanel(panelType, tab, prospectId, subTab) {
    var panelId = panelType + '-panel';
    var panel = Ext.getCmp(panelId);

    if (!panel) {
        Ext.getDom(panelType + '-tab').innerHTML = '';

        panel = new ProspectsPanel({
            id: panelId,
            panelType: panelType,
            renderTo: panelType + '-tab',
            initSettings: {
                tab: tab,
                prospectId: prospectId,
                subTab: subTab
            }
        });
    } else {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel.items.getCount() > 1) {
            var oTab = tabPanel.items.get(1);
            tabPanel.setActiveTab(oTab);
        }

        if (!empty(tab)) {
            var prospectsTodayGrid = Ext.getCmp(panelType + '-tgrid');
            if (prospectsTodayGrid) {
                prospectsTodayGrid.selectActiveFilter(tab);
            }
        }
    }
}

function initCustomProspectToolbar(panelType, pid) {
    pid = empty(pid) ? 0 : pid;

    var toolbarSectionId = 'button-' + panelType + '-section-' + pid;
    var toolbarId = 'button-' + panelType + '-toolbar-' + pid;

    // Check if toolbar is already created
    if ($('#' + toolbarId).length) {
        return Ext.getCmp(toolbarId);
    }

    var appendTo = $($('#' + panelType + '-sub-tab-panel-' + pid + ' .x-tab-panel-body-top')[0]);

    appendTo.prepend(
        '<div style="position: relative; float: left; z-index: 10; width: 100%;">' +
            '<div style="position: absolute; top: 0; left: 0; width: 100%;">' +
                '<div id="' + toolbarSectionId + '" style="background-color: #FFF; padding: 17px 20px 10px; margin-right: 20px"></div>' +
            '</div>' +
        '</div>'
    );

    var profileToolbar = new ProspectsProfileToolbar({
        id: toolbarId,
        panelType: panelType,
        prospectId: pid,
        style: 'margin-bottom: 0'
    }, this);

    profileToolbar.render(toolbarSectionId);

    return profileToolbar;
}

var initProspectsTasks = function(panelType, prospectId, taskId) {
    var divId = panelType + '-tasksTabProspectsForm-' + prospectId;

    var el = $('#' + divId);
    if(el.length) {
        // Clear loading image
        el.empty();

        // Generate panel
        new TasksPanel({
            clientId: prospectId,
            panelType: panelType,
            taskId: taskId,
            booProspect: true,
            renderTo: divId,
            autoWidth: true,
            height: initPanelSize() - 27
        });
    }
};

function initProspectsNotes(panelType, prospectId) {
    var divId = panelType + '-prospectNotesTabForm-' + prospectId;

    var el = $('#' + divId);
    if (el.length) {
        // Clear loading image
        el.empty();

        // Generate panel
        new ActivitiesGrid({
            member_id: prospectId,
            panelType: panelType,
            userType: 'prospect',
            renderTo: divId,
            storeAutoLoad: true,
            notesOnPage: 20,
            height: initPanelSize() - 47
        });
    }
}

function initProspectsDocuments(panelType, prospectId, tabPanel) {
    var divId = panelType + '-prospectDocumentsTabForm-' + prospectId;

    var el = $('#' + divId);
    if (el.length) {
        // Clear loading image
        el.empty();

        // Generate panel
        var oTabPanel = new ProspectsProfileDocumentsTabPanel({
            prospectId: prospectId,
            panelType:  panelType
        }, tabPanel);
        oTabPanel.render(divId);
    }

    var elems = el.find('.x-tab-panel-body');
    var newDocumentsMinHeight = initPanelSize() - 84;
    elems.css('cssText', 'min-height:' + newDocumentsMinHeight + 'px !important;');
}
