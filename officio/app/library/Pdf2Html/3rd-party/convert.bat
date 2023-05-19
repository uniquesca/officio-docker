@set CURRENT_DIR=%~dp0
@cd %CURRENT_DIR%
java -Xmx512M -Dorg.jpedal.pdf2html.viewMode=singlefile -Dorg.jpedal.pdf2html.disableComments=true -Dorg.jpedal.jai=true -cp jpdf2htmlForms.jar org.jpedal.examples.html.PDFtoHTML5Converter %1 %2