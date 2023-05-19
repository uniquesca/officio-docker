var ConditionalFieldsPanel = function (config) {
    Ext.apply(this, config);

    this.conditionsGrid = new ConditionalFieldsGrid({
        field_id: this.field_id,
        field_options: this.field_options,
        field_type: this.field_type,
        case_template_id: this.case_template_id
    });

    ConditionalFieldsPanel.superclass.constructor.call(this, {
        height: 350,
        items:  [
            {
                html: '<div style="padding-bottom: 10px; font-size: larger">' +
                          _('NOTE: If this field is hidden by another conditional field, the fields from "Not Selected" option will be hidden automatically.') +
                          '</div>'
            },
            this.conditionsGrid
        ]
    });
};

Ext.extend(ConditionalFieldsPanel, Ext.Panel, {});