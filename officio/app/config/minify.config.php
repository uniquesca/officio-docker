<?php
/**
 * Groups configuration for default Minify implementation
 * @package Minify
 */

// Must be pointed to the public folder
$publicPath       = realpath('public/');
$topJsUrl         = $publicPath . '/js/';
$superadminJsUrl  = $publicPath . '/superadmin/js/';
$cssUrl           = $publicPath . '/styles/';
$assetsUrl        = $publicPath . '/assets/plugins/';
$jqueryPluginsUrl = $topJsUrl . 'jquery/';
$jQueryUiUrl      = $assetsUrl . 'jquery-ui/';
$extJsUrl         = $topJsUrl . 'ext/';

return array(
    'jq_login' => array(
        $assetsUrl . 'jquery/dist/jquery.min.js',
        $assetsUrl . '@brandextract/jquerytools/src/overlay/overlay.js',
    ),

    'jq' => array(
        $assetsUrl . 'jquery/dist/jquery.min.js',
        $assetsUrl . 'js-cookie/src/js.cookie.js',
        $jqueryPluginsUrl . 'jquery.json.js', // TODO Consider upgrades
        $assetsUrl . 'jquery-form/jquery.form.js',
        $assetsUrl . '@brandextract/jquerytools/src/overlay/overlay.js',
        $assetsUrl . 'colresizable/colResizable-1.6.min.js',
        $jqueryPluginsUrl . 'jquery.metadata.js', // TODO Outdated plugin
        $assetsUrl . 'jquery-file-download/src/Scripts/jquery.fileDownload.js',
    ),

    'jquery-ui' => array(
        $jQueryUiUrl . 'ui/version.js',
        $jQueryUiUrl . 'ui/data.js',
        $jQueryUiUrl . 'ui/disable-selection.js',
        $jQueryUiUrl . 'ui/focusable.js',
        $jQueryUiUrl . 'ui/form.js',
        $jQueryUiUrl . 'ui/ie.js',
        $jQueryUiUrl . 'ui/keycode.js',
        $jQueryUiUrl . 'ui/escape-selector.js',
        $jQueryUiUrl . 'ui/labels.js',
        $jQueryUiUrl . 'ui/jquery-1-7.js',
        $jQueryUiUrl . 'ui/plugin.js',
        $jQueryUiUrl . 'ui/safe-active-element.js',
        $jQueryUiUrl . 'ui/safe-blur.js',
        $jQueryUiUrl . 'ui/scroll-parent.js',
        $jQueryUiUrl . 'ui/tabbable.js',
        $jQueryUiUrl . 'ui/unique-id.js',
        $jQueryUiUrl . 'ui/widget.js',
        $jQueryUiUrl . 'ui/widgets/mouse.js',
        $jQueryUiUrl . 'ui/widgets/draggable.js',
        $jQueryUiUrl . 'ui/widgets/droppable.js',
        $jQueryUiUrl . 'ui/widgets/sortable.js',
        $jQueryUiUrl . 'ui/widgets/datepicker.js',
        $jQueryUiUrl . 'ui/widgets/tabs.js',
    ),

    'jquery-ui-css' => array(
        $jQueryUiUrl . 'themes/base/theme.css',
        $jQueryUiUrl . 'themes/base/core.css',
        $jQueryUiUrl . 'themes/base/draggable.css',
        $jQueryUiUrl . 'themes/base/sortable.css',
        $jQueryUiUrl . 'themes/base/datepicker.css',
        $jQueryUiUrl . 'themes/base/tabs.css',
    ),

    'ext' => array(
        $extJsUrl . 'adapter/ext/ext-base.js',
        $extJsUrl . 'ext-all.js',
        $extJsUrl . 'widgets/FileUploadField.js',
        $extJsUrl . 'widgets/Ext.ux.form.FroalaEditor.js',
        $extJsUrl . 'widgets/Ext.ux.CalendlyButton.js',
        $extJsUrl . 'widgets/ColumnNodeUI.js',
        $extJsUrl . 'widgets/TabCloseMenu.js',
        $extJsUrl . 'widgets/MoneyFormatter.js',
        $extJsUrl . 'widgets/customVTypes.js',
        $extJsUrl . 'widgets/Ext.ux.MoneyField.js',
        $extJsUrl . 'widgets/Ext.ux.StatusBar.js',
        $extJsUrl . 'widgets/Ext.ux.PasswordMeter.js',
        $extJsUrl . 'widgets/Ext.ux.MonthPicker.js',
        $extJsUrl . 'widgets/Ext.ux.RowExpander.js',
        $extJsUrl . 'widgets/Ext.ux.state.LocalStorageProvider.js',
        $extJsUrl . 'widgets/Ext.ux.VerticalTabPanel.js',
        $extJsUrl . 'widgets/Ext.ux.TabCustomRightSection.js',
        $extJsUrl . 'widgets/Ext.ux.TabUniquesMenu.js',
        $extJsUrl . 'widgets/Ext.ux.TabUniquesMenuSimple.js',
        $extJsUrl . 'widgets/Ext.ux.TabUniquesNavigationMenu.js',
        $extJsUrl . 'widgets/Ext.ux.QuickSearchField.js',
        $extJsUrl . 'widgets/htmleditor.plugins.js',
        $extJsUrl . 'widgets/Ext.ux.data.PagingStore.js',
        $extJsUrl . 'widgets/Ext.ux.AddTabButton.js',
        $extJsUrl . 'widgets/miframe-min.js',
        $extJsUrl . 'widgets/MsgConfirmation.js',
        $extJsUrl . 'widgets/Ext.ux.Popup.js',
        $extJsUrl . 'widgets/Ext.ux.form.NormalNumberField.js',
        $extJsUrl . 'widgets/Ext.ux.Spinner.js',
        $extJsUrl . 'widgets/Ext.ux.form.SpinnerField.js',
        $extJsUrl . 'widgets/Ext.ux.PopupMessage.js',
        $extJsUrl . 'widgets/gridSearch.js',
        $extJsUrl . 'widgets/Ext.ux.util.js',
        $extJsUrl . 'widgets/Ext.ux.form.LovCombo.js',
        $extJsUrl . 'widgets/Ext.ux.form.ThemeCombo.js',
        $extJsUrl . 'widgets/Ext.ux.form.MultipleTextFields.js',
        $extJsUrl . 'widgets/Ext.ux.grid.RowActions.js',
        $extJsUrl . 'widgets/Ext.ux.grid.RowEditor.js',
        $extJsUrl . 'widgets/CheckColumn.js',
        $extJsUrl . 'widgets/superboxselect/super-box-select.js',
    ),

    'ext_css' => array(
        $extJsUrl . 'resources/css/ext-all.css',
        $extJsUrl . 'widgets/passwordmeter.css',
        $extJsUrl . 'widgets/htmleditor.plugins/htmleditor.plugins.css',
        $extJsUrl . 'widgets/Ext.ux.form.MultipleTextFields.css',
        $extJsUrl . 'widgets/Ext.ux.form.LovCombo.css',
        $extJsUrl . 'widgets/Ext.ux.grid.RowActions.css',
        $extJsUrl . 'widgets/Ext.ux.grid.RowEditor.css',
        $extJsUrl . 'widgets/Ext.ux.VerticalTabPanel.css',
        $extJsUrl . 'widgets/column-tree.css',
        $extJsUrl . 'widgets/superboxselect/super-box-select.css',
    ),

    'pdf_css' => array(
        $extJsUrl . 'resources/css/ext-all.css',
        $jQueryUiUrl . 'themes/base/theme.css',
        $jQueryUiUrl . 'themes/base/core.css',
        $jQueryUiUrl . 'themes/base/datepicker.css',
        $cssUrl . 'pdf.css'
    ),

    'pdf_js' => array(
        $assetsUrl . 'jquery/dist/jquery.min.js',
        $jqueryPluginsUrl . 'jquery.json.js', // TODO Consider upgrades
        $assetsUrl . 'jquery-file-download/src/Scripts/jquery.fileDownload.js',

        $jQueryUiUrl . 'ui/jquery-1-7.js',
        $jQueryUiUrl . 'ui/widget.js',
        $jQueryUiUrl . 'ui/widgets/datepicker.js',

        $extJsUrl . 'adapter/ext/ext-base.js',
        $extJsUrl . 'ext-all.js',
        $extJsUrl . 'widgets/MsgConfirmation.js',
        $extJsUrl . 'widgets/Ext.ux.Popup.js',
        $topJsUrl . 'pdf/init.js',
    ),

    'pdf2_css' => array(
        $extJsUrl . 'resources/css/ext-all.css',
        $jQueryUiUrl . 'themes/base/theme.css',
        $jQueryUiUrl . 'themes/base/core.css',
        $jQueryUiUrl . 'themes/base/datepicker.css',
        $cssUrl . 'pdf.css'
    ),

    'pdf2_js'    => array(
        $assetsUrl . 'jquery/dist/jquery.min.js',
        $jqueryPluginsUrl . 'jquery.json.js', // TODO Consider upgrades
        $assetsUrl . 'jquery-file-download/src/Scripts/jquery.fileDownload.js',

        $jQueryUiUrl . 'ui/jquery-1-7.js',
        $jQueryUiUrl . 'ui/widget.js',
        $jQueryUiUrl . 'ui/widgets/datepicker.js',

        $extJsUrl . 'adapter/ext/ext-base.js',
        $extJsUrl . 'ext-all.js',
        $extJsUrl . 'widgets/MsgConfirmation.js',
        $extJsUrl . 'widgets/Ext.ux.Popup.js',
        $topJsUrl . 'pdf/pdf2html-nav.js',
        $topJsUrl . 'pdf/pdf2html-form.js',
    ),

    // Latest uForms-based forms
    'uforms_js'  => array(
        $assetsUrl . 'jquery/dist/jquery.min.js',
        $assetsUrl . 'bootstrap/dist/js/bootstrap.min.js',
        $assetsUrl . 'moment/min/moment.min.js',
        $assetsUrl . '@uniquesca/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js',

        $assetsUrl . 'angular/angular.min.js',
        $assetsUrl . 'angular-animate/angular-animate.min.js',
        $assetsUrl . 'angular-ui-bootstrap/dist/ui-bootstrap.js',
        $assetsUrl . 'angular-ui-bootstrap/dist/ui-bootstrap.tplsjs',
        $assetsUrl . 'lodash/dist/lodash.min.js',
        $assetsUrl . 'modernizr/src/Modernizr.js',
        $assetsUrl . 'moment/min/moment.min.js',
        $assetsUrl . 'inputmask/dist/min/jquery.inputmask.bundle.min.js',

        $assetsUrl . '@uniquesca/uforms/ufTemplates.js',
        $assetsUrl . '@uniquesca/uforms/ufCore.js',
        $assetsUrl . '@uniquesca/uforms/ufPlugin.js',
    ),
    'uforms_css' => array(
        $assetsUrl . 'angular-ui-bootstrap/dist/ui-bootstrap-csp.css',
        $assetsUrl . 'bootstrap/dist/css/boostrap.min.css',
        $assetsUrl . '@uniquesca/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css',

        $assetsUrl . '@uniquesca/uforms/main.css',
    ),

    'user_min_js' => array(
        $topJsUrl . 'home/ChangePasswordDialog.js',
        $topJsUrl . 'home/user/UserPingStatus.js',
        $topJsUrl . 'home/user/UserProfileDialog.js',

        $topJsUrl . 'home/expiration/global.js',
        $topJsUrl . 'home/expiration/trial.js',
        $topJsUrl . 'home/expiration/renew.js',
        $topJsUrl . 'home/expiration/interrupted.js',
        $topJsUrl . 'home/expiration/suspended.js',

        $topJsUrl . 'home/dashboard/DashboardBlock.js',
        $topJsUrl . 'home/dashboard/DashboardContainer.js',
        $topJsUrl . 'home/quick_links.js',
        $topJsUrl . 'home/homepage.js',
        $topJsUrl . 'home/links.js',
        $topJsUrl . 'home/rss.js',
        $topJsUrl . 'home/calendar.js',

        $topJsUrl . 'help/HelpContextWindow.js',
        $topJsUrl . 'help/HelpSupportWindow.js',
        $topJsUrl . 'help/main.js',
        $topJsUrl . 'task.js',
        $topJsUrl . 'notes.js',
        $topJsUrl . 'UploadNotesAttachmentsDialog.js',
        $topJsUrl . 'home/notes.js',
        $topJsUrl . 'tasks/Toolbar.js',
        $topJsUrl . 'clients/notes.js',


        $topJsUrl . 'tasks/Toolbar.js',
        $topJsUrl . 'tasks/ReplyDialog.js',
        $topJsUrl . 'tasks/NewTaskDialog.js',
        $topJsUrl . 'tasks/TasksGrid.js',
        $topJsUrl . 'tasks/ThreadsGrid.js',
        $topJsUrl . 'tasks/Panel.js',
        $topJsUrl . 'tasks/Init.js',

        $topJsUrl . 'mail/MailChecker.js',
        $topJsUrl . 'mail/MailToolbar.js',
        $topJsUrl . 'mail/MailFolders.js',
        $topJsUrl . 'mail/MailGridSearch.js',
        $topJsUrl . 'mail/MailGrid.js',
        $topJsUrl . 'mail/MailGridWithPreview.js',
        $topJsUrl . 'mail/MailTabPanel.js',
        $topJsUrl . 'mail/MailCreateDialog.js',
        $topJsUrl . 'mail/MailAttachFromDocumentsDialog.js',
        $topJsUrl . 'mail/MailSettingsDialog.js',
        $topJsUrl . 'mail/MailSettingsTokensDialog.js',
        $topJsUrl . 'mail/MailSettingsGrid.js',
        $topJsUrl . 'mail/MailImapFoldersDialog.js',
        $topJsUrl . 'mail/MailImapFoldersTree.js',
        $topJsUrl . 'mail/MailMassSender.js',

        $topJsUrl . 'fine-uploader/fine-uploader.js',
        $topJsUrl . 'mail/init.js',

        $topJsUrl . 'prospects/profile/documents/ProspectsProfileDocumentsTabPanel.js',
        $topJsUrl . 'prospects/profile/ProspectsProfileToolbar.js',
        $topJsUrl . 'prospects/profile/ProspectsProfileTabPanel.js',
        $topJsUrl . 'prospects/left_section/ProspectsLeftSectionGrid.js',
        $topJsUrl . 'prospects/left_section/ProspectsLeftSectionPanel.js',
        $topJsUrl . 'prospects/advanced_search/prospectsAdvancedSearchForm.js',
        $topJsUrl . 'prospects/advanced_search/prospectsAdvancedSearchGrid.js',
        $topJsUrl . 'prospects/advanced_search/prospectsAdvancedSearchPanel.js',
        $topJsUrl . 'prospects/search/ProspectsSearchPanel.js',
        $topJsUrl . 'prospects/today/ProspectsTodayGrid.js',
        $topJsUrl . 'prospects/today/ProspectsTodayPanel.js',
        $topJsUrl . 'prospects/tasks/ProspectsTasksGrid.js',
        $topJsUrl . 'prospects/tasks/ProspectsTasksPanel.js',
        $topJsUrl . 'prospects/ProspectsGrid.js',
        $topJsUrl . 'prospects/ProspectsGridPanel.js',
        $topJsUrl . 'prospects/ProspectsPanel.js',
        $topJsUrl . 'prospects/ProspectsTabPanel.js',
        $topJsUrl . 'prospects/init.js',
        $extJsUrl . 'widgets/Ext.ux.NOCSearchField.js',
        $topJsUrl . 'qnr/global.js',

        $assetsUrl . '/chart.js/dist/chart.min.js',
        $topJsUrl . 'chart.js',
        $topJsUrl . 'applicants/analytics/ApplicantsAnalyticsPanel.js',
        $topJsUrl . 'applicants/analytics/ApplicantsAnalyticsWindow.js',

        $topJsUrl . 'applicants/ApplicantsPanel.js',
        $topJsUrl . 'applicants/ApplicantsTabPanel.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileTabPanel.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileToolbar.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileForm.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileChangePasswordDialog.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileEditOptionsDialog.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileGenerateConDialog.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileGeneratePdfLetterDialog.js',
        $topJsUrl . 'applicants/profile/client-referrals/ApplicantsProfileClientReferralsDialog.js',
        $topJsUrl . 'applicants/profile/client-referrals/ApplicantsProfileClientReferralsGrid.js',
        $topJsUrl . 'applicants/profile/linked-cases/ApplicantsProfileLinkedCasesGrid.js',
        $topJsUrl . 'applicants/profile/linked-cases/ApplicantsProfileLinkedCasesLinkCaseDialog.js',
        $topJsUrl . 'applicants/profile/file-status-history/ApplicantsProfileFileStatusHistoryDialog.js',
        $topJsUrl . 'applicants/cases/ApplicantsCasesAssignDialog.js',
        $topJsUrl . 'applicants/cases/ApplicantsCasesAssignToEmployerDialog.js',
        $topJsUrl . 'applicants/cases/ApplicantsCasesGrid.js',
        $topJsUrl . 'applicants/cases/navigation/ApplicantsCasesNavigationGrid.js',
        $topJsUrl . 'applicants/cases/navigation/ApplicantsCasesNavigationPanel.js',
        $topJsUrl . 'applicants/search/ApplicantsSavedSearchWindow.js',
        $topJsUrl . 'applicants/search/ApplicantsSearchPanel.js',
        $topJsUrl . 'applicants/search/ApplicantsSearchGrid.js',
        $topJsUrl . 'applicants/search/favorite/ApplicantsSearchFavoriteGrid.js',
        $topJsUrl . 'applicants/search/favorite/ApplicantsSearchFavoritePanel.js',
        $topJsUrl . 'applicants/advanced_search/ApplicantsAdvancedSearchGrid.js',
        $topJsUrl . 'applicants/advanced_search/ApplicantsAdvancedSearchPanel.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfilePaymentDialog.js',
        $topJsUrl . 'applicants/profile/ApplicantsProfileSubmitDialog.js',
        $topJsUrl . 'applicants/queue/left_section/ApplicantsQueueLeftSectionGrid.js',
        $topJsUrl . 'applicants/queue/left_section/ApplicantsQueueLeftSectionPanel.js',
        $topJsUrl . 'applicants/visa_survey/ApplicantsVisaSurveyEditDialog.js',
        $topJsUrl . 'applicants/visa_survey/ApplicantsVisaSurveyDialog.js',

        $topJsUrl . 'applicants/queue/ApplicantsQueueChangeOptionsWindow.js',
        $topJsUrl . 'applicants/queue/ApplicantsQueueApplyChangesWindow.js',
        $topJsUrl . 'applicants/queue/ApplicantsQueuePullFromWindow.js',
        $topJsUrl . 'applicants/queue/ApplicantsQueueGrid.js',
        $topJsUrl . 'applicants/queue/ApplicantsQueuePanel.js',

        $topJsUrl . 'applicants/tasks/ApplicantsTasksPanel.js',
        $topJsUrl . 'applicants/tasks/ApplicantsTasksGrid.js',
        $topJsUrl . 'applicants/init.js',

        $topJsUrl . 'applicants/profile/vevo/ApplicantsProfileVevoCheckDialog.js',
        $topJsUrl . 'applicants/profile/vevo/ApplicantsProfileVevoInfoDialog.js',
        $topJsUrl . 'applicants/profile/vevo/ApplicantsProfileVevoSender.js',

        $topJsUrl . 'clients/main.js',

        $topJsUrl . 'clients/accounting.js',
        $topJsUrl . 'clients/accounting/scheduler/ManageScheduleRecordDialog.js',
        $topJsUrl . 'clients/accounting/payment/CreditCardIframeDialog.js',
        $topJsUrl . 'clients/accounting/payment/CreditCardPaymentDialog.js',
        $topJsUrl . 'clients/accounting/MarkAsPaidDialog.js',
        $topJsUrl . 'clients/accounting/invoices/GenerateInvoiceFromTemplateDialog.js',
        $topJsUrl . 'clients/accounting/invoices/AccountingInvoicesAssignDialog.js',
        $topJsUrl . 'clients/accounting/invoices/AccountingInvoicesGrid.js',
        $topJsUrl . 'clients/accounting/invoices/AccountingInvoicesLegacyInvoiceDialog.js',
        $topJsUrl . 'clients/accounting/invoices/AccountingInvoicesNotesDialog.js',
        $topJsUrl . 'clients/accounting/fees/AccountingFeesAssignDialog.js',
        $topJsUrl . 'clients/accounting/fees/AccountingFeesManageTemplatesDialog.js',
        $topJsUrl . 'clients/accounting/fees/AccountingFeesManageDialog.js',
        $topJsUrl . 'clients/accounting/fees/AccountingFeesDialog.js',
        $topJsUrl . 'clients/accounting/fees/AccountingFeesGrid.js',
        $topJsUrl . 'clients/accounting/trust_account_summary/AssignDepositDialog.js',
        $topJsUrl . 'clients/accounting/trust_account_summary/TrustAccountSummaryDialog.js',
        $topJsUrl . 'clients/accounting/AssignAccountDialog.js',
        $topJsUrl . 'clients/accounting/AccountingToolbar.js',
        $topJsUrl . 'clients/accounting/AccountingPanel.js',

        $topJsUrl . 'documents/DocumentsPanel.js',
        $topJsUrl . 'documents/DocumentsToolbar.js',
        $topJsUrl . 'documents/DocumentsTree.js',
        $topJsUrl . 'documents/DocumentsPreviewPanel.js',

        $topJsUrl . 'documents/MultiUploadDialog.js',
        $topJsUrl . 'documents/FilesPreviewDialog.js',
        $topJsUrl . 'documents/documents.js',
        $topJsUrl . 'clients/documents.js',

        $topJsUrl . 'documents/checklist/DocumentsChecklistTagsDialog.js',
        $topJsUrl . 'documents/checklist/DocumentsChecklistTagPercentageDialog.js',
        $topJsUrl . 'documents/checklist/DocumentsChecklistReassignDialog.js',
        $topJsUrl . 'documents/checklist/DocumentsChecklistTree.js',
        $topJsUrl . 'documents/checklist/DocumentsChecklistUploadDialog.js',

        $topJsUrl . 'clients/time_tracker/ClientTrackerAddDialog.js',
        $topJsUrl . 'clients/time_tracker/ClientTrackerInit.js',
        $topJsUrl . 'clients/time_tracker/ClientTrackerPanel.js',
        $topJsUrl . 'clients/time_tracker/ClientTrackerGrid.js',
        $topJsUrl . 'clients/time_tracker/ClientTrackerToolbar.js',
        $topJsUrl . 'clients/time_tracker/ClientTrackerFilter.js',
        $topJsUrl . 'clients/time_tracker/ClientTrackerMarkDialog.js',

        $topJsUrl . 'clients/time_log_summary/init.js',
        $topJsUrl . 'clients/time_log_summary/TimeLogSummaryFilter.js',
        $topJsUrl . 'clients/time_log_summary/TimeLogSummaryGrid.js',
        $topJsUrl . 'clients/time_log_summary/TimeLogSummaryToolbar.js',
        $topJsUrl . 'clients/time_log_summary/TimeLogSummaryPanel.js',

        $topJsUrl . 'clients/forms/init.js',
        $topJsUrl . 'clients/forms/grid.js',
        $topJsUrl . 'clients/forms/assign_dialog.js',
        $topJsUrl . 'clients/forms/upload_form_dialog.js',

        $topJsUrl . 'plugin_detector.js',
        $topJsUrl . 'browser_detector.js',

        $topJsUrl . 'trust_account/filter.js',
        $topJsUrl . 'trust_account/showGrid.js',
        $topJsUrl . 'trust_account/assignDeposit.js',
        $topJsUrl . 'trust_account/assignWithdrawal.js',
        $topJsUrl . 'trust_account/editTransaction.js',
        $topJsUrl . 'trust_account/importManualDialog.js',
        $topJsUrl . 'trust_account/import.js',
        $topJsUrl . 'trust_account/history.js',
        $topJsUrl . 'trust_account/delete.js',
        $topJsUrl . 'trust_account/print.js',
        $topJsUrl . 'trust_account/export_imported_transactions.js',
        $topJsUrl . 'trust_account/ClientsAccountingGrid.js',
        $topJsUrl . 'trust_account/main.js',

        $topJsUrl . 'mydocs/main.js',

        $topJsUrl . 'templates/ProspectTemplatesGrid.js',
        $topJsUrl . 'templates/DocumentTemplatesTree.js',
        $topJsUrl . 'templates/UploadAttachmentsDialog.js'
    ),

    'superadmin_min_js' => array(
        $topJsUrl . 'home/expiration/suspended.js',

        // Tasks
        $topJsUrl . 'tasks/Toolbar.js',
        $topJsUrl . 'tasks/ReplyDialog.js',
        $topJsUrl . 'tasks/NewTaskDialog.js',
        $topJsUrl . 'tasks/TasksGrid.js',
        $topJsUrl . 'tasks/ThreadsGrid.js',
        $topJsUrl . 'tasks/Panel.js',
        $topJsUrl . 'tasks/Init.js',

        // Tickets
        $superadminJsUrl . 'tickets/grid.js',
        $superadminJsUrl . 'tickets/ticket.js',
        $superadminJsUrl . 'tickets/panel.js',
        $superadminJsUrl . 'tickets/filter_panel.js',

        $topJsUrl . 'documents/DocumentsPanel.js',
        $topJsUrl . 'documents/DocumentsToolbar.js',
        $topJsUrl . 'documents/DocumentsTree.js',
        $topJsUrl . 'documents/DocumentsPreviewPanel.js',
        $topJsUrl . 'documents/MultiUploadDialog.js',
        $topJsUrl . 'documents/FilesPreviewDialog.js',
        $topJsUrl . 'documents/documents.js',
        $topJsUrl . 'mydocs/main.js',

        $topJsUrl . 'templates/DocumentTemplatesTree.js',
        $topJsUrl . 'templates/UploadAttachmentsDialog.js',


        // Companies
        $superadminJsUrl . 'company/search.js',
        $superadminJsUrl . 'company/advanced_search.js',
        $superadminJsUrl . 'company/mass_email_sender.js',
        $superadminJsUrl . 'company/mass_email_dialog.js',
        $superadminJsUrl . 'company/grid.js',
        $superadminJsUrl . 'company/tab_panel.js',
        $superadminJsUrl . 'company/failed_invoices.js',

        $topJsUrl . 'clients/time_tracker/ClientTrackerAddDialog.js',

        $topJsUrl . 'mail/MailChecker.js',
        $topJsUrl . 'mail/MailToolbar.js',
        $topJsUrl . 'mail/MailFolders.js',
        $topJsUrl . 'mail/MailGridSearch.js',
        $topJsUrl . 'mail/MailGrid.js',
        $topJsUrl . 'mail/MailGridWithPreview.js',
        $topJsUrl . 'mail/MailTabPanel.js',
        $topJsUrl . 'mail/MailCreateDialog.js',
        $topJsUrl . 'mail/MailAttachFromDocumentsDialog.js',
        $topJsUrl . 'mail/MailSettingsDialog.js',
        $topJsUrl . 'mail/MailSettingsTokensDialog.js',
        $topJsUrl . 'mail/MailSettingsGrid.js',
        $topJsUrl . 'mail/MailImapFoldersTree.js',
        $topJsUrl . 'mail/MailImapFoldersDialog.js',
        $topJsUrl . 'fine-uploader/fine-uploader.js',
        $topJsUrl . 'mail/init.js',

        $topJsUrl . 'home/user/UserPingStatus.js',
        $topJsUrl . 'home/user/UserProfileDialog.js',
    )
);
