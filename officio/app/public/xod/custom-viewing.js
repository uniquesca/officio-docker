const queryParams = new URLSearchParams(window.location.search);

var docType = queryParams.get('doctype') || 'xod';
var pdfId = queryParams.get('p') || 0;
var memberId = queryParams.get('m') || 0;
var helpAricleId = queryParams.get('h') || 0;
var booLatest = parseInt(queryParams.get('l') || 0, 10) === 1;
var booEnableAnnotations = parseInt(queryParams.get('a') || 0, 10) === 1;

var openXodUrl = '/forms/index/open-xod?pdfid=' + pdfId + '&latest=' + (booLatest ? '1' : '0') + '&' + (new Date()).getTime();
var saveXodUrl = '/forms/sync/save-xod';
var printXodUrl = '/forms/index/print?member_id=' + memberId + '&pdfid=' + pdfId;
var openXfdfUrl = '/forms/index/open-assigned-xfdf?pdfid=' + pdfId;
var openXfdfUrl = '/forms/index/open-assigned-xfdf?pdfid=' + pdfId;
var helpAricleUrl = helpAricleId == 0 ? '' : '/help/index/index?type=help#' + helpAricleId;

var booDefaultForms = parseInt(queryParams.get('df') || 0, 10) === 1;
if (booDefaultForms) {
    openXodUrl = '/superadmin/forms-default/open-xod?formId=' + pdfId + '&' + (new Date()).getTime();
    openXfdfUrl = '/superadmin/forms-default/open-xfdf?formId=' + pdfId;
    saveXodUrl = '/superadmin/forms-default/save-xod';
    printXodUrl = '/superadmin/forms-default/print-xod?formId=' + pdfId + '&' + (new Date()).getTime();
}

WebViewer(
    {
        licenseKey: '::Add The License Here::',
        type: "html5",
        path: '/assets/plugins/@pdftron/webviewer/public',
        css: '/xod/custom.css',
        initialDoc: openXodUrl,
        documentType: docType,
        documentId: pdfId,
        config: 'config.js?' + (new Date()).getTime(),
        enableAnnotations: true,
        streaming: true,
        showLocalFilePicker: true,
        annotationAdmin: true,
        fullAPI: true,
        disableLogs: true,
        disabledElements: [
            'menuButton', 'moreButton', 'toggleNotesButton', 'loadingModal', 'fullscreenButton', 'themeChangeButton', 'textSelectButton', 'leftPanelButton', 'searchButton', 'searchPanel', 'searchOverlay', 'selectToolButton', 'viewControlsButton', 'toolbarGroup-Edit', 'toolbarGroup-Forms', 'layoutButtons', 'annotationPopup', 'panToolButton', 'linkButton', 'toolsOverlayCloseButton',
            'toolbarGroup-Shapes', 'toolbarGroup-Insert',
            'underlineToolGroupButton', 'strikeoutToolGroupButton', 'squigglyToolGroupButton', 'stickyToolGroupButton', 'freeHandHighlightToolGroupButton',
            'crossStampToolButton', 'checkStampToolButton', 'dotStampToolButton', 'dateFreeTextToolButton',
        ],
        custom: JSON.stringify({
            saveXodUrl: saveXodUrl,
            printXodUrl: printXodUrl,
            openXfdfUrl: openXfdfUrl,
            helpAricleUrl: helpAricleUrl,
            pdfId: pdfId,
            booEnableAnnotations: booEnableAnnotations
        })
    },
    document.getElementById('viewer')
).then(instance => {
    const {disableFeatures, enableElements, Feature} = instance.UI;
    const {annotationManager, documentViewer} = instance.Core;

    var FitMode = instance.UI.FitMode;
    instance.UI.setFitMode(FitMode.FitWidth);
    instance.UI.setToolbarGroup('toolbarGroup-View');
    instance.UI.setPrintQuality(5);

    // Disable too: Feature.Print
    disableFeatures([Feature.FilePicker, Feature.Search, Feature.Measurement, Feature.Download]);
    enableElements(['bookmarksPanel', 'bookmarksPanelButton', 'richTextPopup']);
    if (!booEnableAnnotations) {
        disableFeatures([Feature.Ribbons]);
        annotationManager.enableReadOnlyMode();
    } else {
        annotationManager.disableReadOnlyMode();
    }
    
    const tool = documentViewer.getTool('AnnotationCreateRubberStamp');

    tool.setStandardStamps([
        'SHSignHere',
        'Approved',
        'SHWitness',
        'SHInitialHere',
        'SHAccepted',
        'SBRejected',
    ]);
});
