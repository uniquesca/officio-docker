var ProspectsProfileToolbar = function(config, owner) {
    Ext.apply(this, config);
    this.owner = owner;

    var pid = this.prospectId;
    var booNewProspect = empty(this.prospectId);
    ProspectsProfileToolbar.superclass.constructor.call(this, {
        items: [
            {
                id: 'button-save-prospect-' + pid,
                text: '<i class="las la-save"></i>' +_('Save'),
                tooltip: 'Save this prospect.',
                cls: 'orange-btn',
                style: 'margin-right: 10px;',
                hidden: !arrProspectSettings.arrAccess[config.panelType].save_info,
                handler: function() {
                    Ext.getCmp(config.panelType + '-grid').saveProspectForm(pid, false);
                }
            }, {
                id: 'button-assess-prospect-' + pid,
                text: '<i class="las la-universal-access"></i>' +_('Assess'),
                tooltip: _("Automatically assess this prospect's information to determine eligibility."),
                style: 'padding-right: 10px;',
                hidden: !arrProspectSettings.arrAccess[config.panelType].assess,
                handler: function() {
                    Ext.getCmp(config.panelType + '-grid').saveProspectForm(pid, true);
                }
            }, {
                text: '<i class="lar la-envelope"></i>' +_('Email'),
                tooltip: 'Email this prospect.',
                style: 'padding-right: 10px;',
                hidden: booNewProspect || !allowedPages.has('email') || !arrProspectSettings.arrAccess[config.panelType].email,
                handler: function() {
                    var options = {
                        member_id: pid,
                        booProspect: true,
                        booNewEmail: true,
                        templates_type: 'Prospect',
                        save_to_prospect: true,
                        panelType: config.panelType,
                        toName: config.panelType === 'marketplace' ? Ext.getCmp(config.panelType + '-ptab-' + pid).title.replace(/Not specified/gi, '') : null,
                        hideDraftButton: config.panelType === 'marketplace',
                        hideTo: config.panelType === 'marketplace',
                        hideCc: config.panelType === 'marketplace',
                        hideBcc: config.panelType === 'marketplace'
                    };

                    show_email_dialog(options);
                }
            }, {
                text: '<i class="las la-print"></i>' +_('Print'),
                tooltip: _("Print this prospect's questionnaire."),
                style: 'padding-right: 10px;',
                hidden: booNewProspect || !arrProspectSettings.arrAccess[config.panelType].print,
                handler: function() {
                    window.open(baseUrl + '/prospects/index/export-to-pdf/Questionnaire_Summary.pdf?pid=' + pid + '&panelType=' + config.panelType);
                }
            }, {
                text: '<i class="las la-user-check"></i>' + _('Convert to Client'),
                tooltip: _('Convert this prospect to a client.'),
                style: 'padding-right: 10px;',
                hidden: booNewProspect || !arrProspectSettings.arrAccess[config.panelType].convert_to_client,
                handler: function() {
                    Ext.getCmp(config.panelType + '-grid').convertProspectToClient([pid], Ext.getCmp(config.panelType + '-ptab-' + pid).title);
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                tooltip: _('Delete this prospect.'),
                hidden: booNewProspect || !arrProspectSettings.arrAccess[config.panelType].delete_prospect,
                handler: function() {
                    Ext.getCmp(config.panelType + '-grid').deleteProspect([pid], true);
                }
            }, '->', {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    var contextId = config.panelType === 'marketplace' ? 'marketplace-prospect' : 'prospects-prospect';
                    showHelpContextMenu(this.getEl(), contextId);
                }
            }
        ]
    });
};

Ext.extend(ProspectsProfileToolbar, Ext.Toolbar, {
    toggleThisToolbar: function(booShow) {
        this.setVisible(booShow);

        // Hide the parent container (if we hide the toolbar) to avoid UI issues
        var $toolbar = $('#' + this.getId()).parent().toggle(booShow);
    }
});
