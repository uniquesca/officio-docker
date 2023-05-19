function showHomepageNotes()
{
    $('#notesForm').html('<img src="' + imagesUrl + '/loading.gif" alt="loading" />').load(baseUrl + "/notes/index/get-notes-list");
}