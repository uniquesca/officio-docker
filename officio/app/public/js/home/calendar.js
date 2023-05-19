function activateCalendarTab()
{
    var tabPanel = Ext.getCmp('main-tab-panel');
    var tab = Ext.getCmp('calendar-tab');
    if(tabPanel && tab) {
        tabPanel.setActiveTab('calendar-tab');
    }
}