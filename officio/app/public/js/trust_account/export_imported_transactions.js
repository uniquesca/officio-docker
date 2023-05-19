function exportImportedTransactions(exportTaId, exportFilter, exportClientName, exportClientCode, exportStartDate, exportEndDate, exportUnassignedEndDate) {
    var url = String.format(
        '{0}/trust-account/index/export?exportTaId={1}&exportFilter={2}',
        baseUrl,
        exportTaId,
        exportFilter
    );

    var extraUrl = '';
    if (!empty(exportFilter)) {
        switch (exportFilter) {
            case 'client_name':
                extraUrl = String.format(
                    '&firstParam={0}',
                    exportClientName
                );
                break;

            case 'client_code':
                extraUrl = String.format(
                    '&firstParam={0}',
                    exportClientCode
                );
                break;

            case 'period':
                extraUrl = String.format(
                    '&firstParam={0}&secondParam={1}',
                    encodeURIComponent(exportStartDate),
                    encodeURIComponent(exportEndDate)
                );
                break;

            case 'unassigned':
                extraUrl = String.format(
                    '&firstParam={0}',
                    encodeURIComponent(exportUnassignedEndDate)
                );
                break;

        }
    }

    url += extraUrl;

    window.open(url);
}
