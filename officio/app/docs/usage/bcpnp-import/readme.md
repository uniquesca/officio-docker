# BCPNP IMPORT

This import allows to bulk update cases based one an identification field which can be configured.

### Supported formats
Currently .xls and .xlsx files are supported.
NOTE: .xls files saved by Office 365 may cause import issues, .xlsx files are recommended to use in that case.

### Configuration
Config setting for identification field name is `settings`->`bcpnp_import_identificator_field_name`. This field
has to be present in the import file. Also this field has to be added to case, not profile, can be plain text 
and should have unique value for every case.
NOTE: in order to use case reference (file) number, `file_number` field name should be used. 
NOTE: if identification field value is not unique and search returns several records having it, only first one will be
      used, therefore it's highly recommended to use only unique ones.

### Additional notes
Note that:
* For the best results, please use plain text format in the import file.
* Supported date formats are described here: https://www.php.net/manual/en/datetime.formats.date.php
* Formulas are not supported yet, therefore perform calculations before saving the document.
