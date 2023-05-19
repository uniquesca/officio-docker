OFFICIO
============

![Canada](https://github.com/uniquesca/officio/workflows/CA/badge.svg?branch=develop)
![Australia](https://github.com/uniquesca/officio/workflows/AU/badge.svg?branch=develop)
![Dominica](https://github.com/uniquesca/officio/workflows/DM/badge.svg?branch=develop)
![BC PNP](https://github.com/uniquesca/officio/workflows/BCPNP/badge.svg?branch=develop)
![NTNP](https://github.com/uniquesca/officio/workflows/NTNP/badge.svg?branch=develop)

Officio is a leading CRM in immigration industry in Canada and Australia.

REQUIREMENTS
------------
After all requirements listed below are met, please proceed to the INSTALLATION section of this documentation.

OS: Linux-based (can work with Windows, but it's highly unrecommended)  
Web-server: Apache 2.4+ (see Apache requirements below)  
Database: MySQL 5.7 or 8.0.+
PHP: 8.1+ MTA (Mail Transport Agent): any, supporting sendmail

#### Additional software (installation depends on customer needs)

- Java (working with PDF forms, PDF to HTML conversion)
- Libreoffice (MS Word to PDF conversion)
- PhantomJS (HTML forms to PDF conversion)

#### SELinux support

SELinux is supported, but might require additional configuration to ensure:
- Write access to various directories listed in this documentation
- Execution rights for various scripts
- Non-interrupted connections with various data sources such as database server, mail server, etc.

#### Possible issues with sending emails via SMTP
- `SMTP -> ERROR: Failed to connect to server: Permission denied (13)`  
  Might be caused by running into SELinux preventing PHP or the web server from sending email.   
  `getsebool` command can be used to check if web-server is allowed to make a connection over the network and send an email:  
  `getsebool httpd_can_sendmail`
  `getsebool httpd_can_network_connect`
  In case these commands return `off`, it should be turned on by running the following commands:  
  `sudo setsebool -P httpd_can_sendmail 1`
  `sudo setsebool -P httpd_can_network_connect 1`

#### Apache requirements

##### A recommended version: 2.4.4

Required Apache modules:
- `mod_filter` (required for `mod_deflate`)
- `mod_deflate`
- `mod_expires`
- `mod_rewrite`
- `mod_headers`
- `mod_ssl` (optional for non-production installations)

Required Apache settings:
- `AllowOverride` should be set to `All` in order to enable .htaccess files support
- `DocumentRoot` must be set to `public/` directory
- `Options` directive should enable `Indexes`
- `TimeOut` should be set to 300 seconds in order to allow some "time-heavy" operations such as documents generation or conversion

To avoid error in the FF for `.po` files - a correct mime type must be set. For instance, in the `mime.types` file (for RedHat located in the `/etc/` directory) add this line: `text/x-gettext-translation po`

Sample Apache virtual host file is located here: `officio/docs/config/server/apache/`


#### Reverse Proxy requirements

If a reverse proxy is used, it has to be properly configured.

1. It should send headers containing original client IP address and original schema (https/http), names of those headers should be properly configured in Officio configuration file's "proxy" section.
2. Proxy timeout has to be set to 300 seconds to match web-server's timeout.

#### MySQL requirements

##### Recommended version: 8.0

Required config settings (placement depends on OS and MySQL installation):

- `init-connect="SET NAMES utf8mb4"`
- `collation_server=utf8mb4_general_ci`
- `character_set_server=utf8mb4`
- `max_allowed_packet=512M`
- `tmp_table_size=64M`
- `max_heap_table_size=64M`
- `innodb_buffer_pool_size=2048M`
- `innodb_file_per_table=1`
- `innodb_strict_mode=0`
- `sql_mode=NO_ENGINE_SUBSTITUTION`

Officio requires up to 3 databases:
1. Main database
2. Statistics database
3. (optional) Webmail database

Sample MySQL config file is located here: `officio/application/config/server/mysql/`

#### PHP requirements

##### Recommended version: 8.1

Required config settings (placement depends on OS and PHP installation):

- `register_globals off`
- `short_open_tag on`
- `magic_quotes_gpc off`
- `magic_quotes_sybase off`

Important config settings:

- `session.cookie_samesite` - "SameSite" attribute can be set for the session ID cookie. This attribute is a way to mitigate CSRF (Cross Site Request Forgery) attacks. Must be set to "Lax" or "Strict".
- `max_execution_time` - the maximum time in seconds a script is allowed to run before it is terminated by the parser. We recommend 300 seconds or more.
- `max_input_time` - the maximum time in seconds a script is allowed to parse input data. Recommendede value is 90 seconds.
- `max_input_vars` - maximum variables count that php allows receiving and parsing. Officio can have thousands of variables on the "Edit Role" page - depends on the fields and client types count. 3000 is the minimum recommended value, but might be increased on the systems with big amount of roles and fields.
- `upload_max_filesize` - maximum allowed size for uploads, affects all uploads in the system
- `max_file_uploads` - particularly important in case of using a frontend application - this option defines how many files can be uploaded into officio at once. If request contains more files, several files will be chopped off randomly.
- `post_max_size` - maximum allowed size for a request made to the server, minimum value is:
  `upload_max_filesize` * `max_file_uploads` + at least 2Mb
- `memory_limit` - no less than 256Mb

In some shell scripts emails are sent. Make sure that `mail` command works.  
E.g. execute the following in the command line:
`mail -s "New website works" "to@email.com" < /dev/null`

If PHP is used/installed as `fpm` - please make sure that the timeout is set there too.  
E.g. add these lines to the `php.conf`:  
`<Proxy "unix:/run/php-fpm/www.sock|fcgi://localhost">`  
    `ProxySet connectiontimeout=300 TimeOut=300`  
`</Proxy>`

##### PHP extensions

Full list of required extensions contains in `composer.json` file. Presence of all the required extensions can be verified 3 ways:
1. Using phpinfo() output
2. Using Composer command: `./composer.phar check-platform-reqs`
3. Using Officio check.php script: `INSTALLATION_URL/check.php?show_info`

###### List of PHP extensions currently required:

1. Mcrypt | deprecated
    - Migrating Officio to OpenSSL
2. Json
    - Encoding and decoding data to/from JSON format which is extensively used when browser is communicating to the server
3. OpenSSL
    - Encryption and decryption of data, hashing passwords
4. Mbstring
    - Processing templates
5. PDO
    - Working with the database
6. PDO_MySQL
    - Driver for MySQL database
7. Zip
    - Downloading files as Zip archive
    - Processing templates
8. Zlib
    - Migrating Officio to OpenSSL
    - Storing cache
9. Gd
    - Processing images - used in HTML conversion and letterheads
10. Apache
    - Used by checker tool to verify Apache configuration
11. Gettext
    - Used for internationalization

###### List of PHP extensions to be made optional (currently required):

1. Calendar
    - Company workdays
2. DOM
    - Working with XFDF & XDP files
    - TranPage payment gateway
    - HTML to XFDF conversion
3. SimpleXML
    - Working with XFDF and XDP data (opening forms, printing, etc)
    - TranPage payment gateway
    - Logging data from XFDF file (optional and seems not being used)
4. XML
    - Working with XFDF data
    - Working with data from HTML forms // TODO Can this be removed?
5. Iconv
    - Encoding of PDF files
6. Curl
    - Downloading external files RemoteController::getFileAction() TODO
    - PayWay payment gateway
    - TranPage payment gateway
    - Payment service
    - Immi functionality
    - Vevo functionality
    - Url checker

###### List of PHP extensions required by Officio Mail suite:

1. Iconv
    - Processing emails
2. Mbstring
    - Processing emails
3. Mailparse
    - Parsing emails, including sent ones
4. Imap
    - Working with email attachments (some attachments require special imap base64 decoding)
5. Bcmath
    - Comparing float numbers (used in Accounting and Invoice template generation)

#### List of optional PHP/Composer modules:

1. GeoIP - responsible for determining client location, used by Officio\Common\Tools::detectLocation() method.
    - Installation: `./composer.phar require "geoip2/geoip2@^2.12"`
    - Configuration:  set up geoip->browscap_path string pointing to browscap ini file in local.php config. By default, it's set to point to lite version of the file which is included into this distro.
2. Twilio - responsible for sending SMS messages, used by Officio\Comms\Service\Sms service.
    - Installation: `./composer.phar require "twilio/sdk@~6.31.0"`
3. Stripe - to be made optional.

#### Java requirements

##### Recommended version: 1.6

Java is used in:
- `library/Pdf2Html`
    - Superadmin: Convert to HTML (when upload pdf)
    - Superadmin: Manage HTML forms -> Convert PDF to HTML

Potential issues:
- `Could not reserve enough space for code cache` / `There is insufficient memory for the Java Runtime`
  Known to occur in RedHat 7 (Amazon EC2). The fix is described [here](https://stackoverflow.com/questions/39285304/php-exec-java-cmd-failed-with-permission-denied/52201705#52201705).


#### Libreoffice requirements

##### A recommended version: 4.0.4.2 or higher (tested on 5.3.6 and 6.4.6, failed on 7.0)

Libreoffice is used in the headless mode for DOCX to PDF file conversion.

The following parts of Libreoffice have to be installed:
1. Libreoffice (main package)
2. Libreoffice Headless
3. Libreoffice Impress
4. Libreoffice Calc

**Requirements:**
- (Linux) Set execution rights on `scripts/convert_to_pdf.sh` file for web-server user

**Steps to install (Example for RedHat 8):**

- Remove previously installed versions: `yum remove openoffice* libreoffice*`
- Got to the official website and select Linux 64bit RPM, copy download link: https://www.libreoffice.org/download/download/
- `cd /tmp`
- `wget https://download.documentfoundation.org/libreoffice/stable/6.4.6/rpm/x86_64/LibreOffice_6.4.6_Linux_x86-64_rpm.tar.gz`
- `tar -xvf LibreOffice_6.4.6_Linux_x86-64_rpm.tar.gz`
- `cd /tmp/LibreOffice_6.4.6_Linux_x86-64_rpm/RPMS`
- `yum localinstall *.rpm`
- also, please create a symlink or edit the `scripts/convert_to_pdf.sh` script:
    - `cd /bin/`
    - `ln -s /opt/libreoffice6.4/program/soffice libreoffice`

**Check if libreoffice is correctly setup:**

- `which libreoffice`
- `libreoffice --version`

**Test of the conversion script:**

- `cd /var/www/officio/scripts`
- `./convert_to_pdf.sh /var/www/officio/var/log/test.docx /var/www/officio/var/log`
- As a result - you'll see a `test.pdf` file in the `/var/www/officio/var/log` dir
- Note that test.docx should be very big and complicated (images, tables). Libreoffice 7.0 failed with one test file.

**Note:**
When there is "Unable to create XXX directory. Permission denied. dconf will not work properly." error in the `check.php` script - please create that XXX directory and switch the owner to the web-server (apache) user.

#### PhantomJS requirements

##### A recommended version: 2.1.1

HTML forms which load data via AJAX should be converted to PDF using PhantomJS which simulates browser behavior.

Requirements:
- (Linux) Execution rights on `officio/library/PhantomJS/phantomjs` for web-server user
- (Linux) Write rights on `var/pdf_tmp` directory for web-server user

#### PDFTron (PDFNet) requirements

##### Recommended version:

PDFTron is used to:

- convert `pdf` files to the `xod` format,
- unite `pdf` and `xfdf` files,
- create flatten `pdf` files,
- extract field names from the `pdf` files.

`pdf` to `xod` conversion can be done either via web-service or locally - regulated by `pdf2xod.use_local` setting in the application config file.

Requirements:

- Install Python 2.7
- Make sure that python exists (`which python`), if not - create a symlink in the `/usr/bin` directory (`cd /usr/bin` and after that `sudo ln -s python2 python`)
- (Linux) Set execution rights on the files with extensions .py and .sh located at `vendor/uniquesca/officio-pdftron/library/pdftron-lib-python/` for web-server user.

Potential issues:

- `... undefined symbol: PyUnicodeUCS2*` - versions of installed Python and the one used by PDFTron seem to differ. More information [here](https://docs.python.org/2.7/faq/extending.html#when-importing-module-x-why-do-i-get-undefined-symbol-pyunicodeucs2)
  Solution: recompile PDFTrong library, instructions [here](https://github.com/PDFTron/PDFNetWrappers#example)
- (recompilation, see potential issue above) `Could NOT find PythonLibs (missing:  PYTHON_LIBRARIES)`
  Solution: run the following command:
  `cmake -D BUILD_PDFNetPython=ON .. -DPYTHON_LIBRARY=$(python -c "import distutils.sysconfig as sysconfig; print(sysconfig.get_c config_var('LIBDIR'))")`
- `Could NOT find PythonLibs (missing:  PYTHON_INCLUDE_DIRS)`
  Solution: install `python-dev` package

#### Integrations requirements

##### Calendly
- We use the calendly API to retrieve scheduling links for the user to insert into emails.
- Register an application with Calendly here: https://developer.calendly.com/how-to-authenticate-with-oauth. They will provide the client ID/secret after approval.

##### Dropbox
- We use the Chooser / Saver pre-built components from Dropbox.
- Create an app at Dropbox here: https://www.dropbox.com/developers and save the app key into the config. Ensure the 'Chooser / Saver / Embedder domains' are set in the app settings.

##### Google Drive
- The 'Google Picker API' (https://developers.google.com/picker/docs) is used to retrieve an OAuth token and select a file/folder from Drive. The 'Files: get' API (https://developers.google.com/drive/api/v3/reference/files/get) is used to download the file, and 'Files: create' is used to upload files.
- Create a project and relevant credentials by following the instructions in the Picker docs.

INSTALLATION
--------------
Officio uses 3rd-party libraries of two types: PHP libraries and JS libraries. PHP libraries are installed into `vendor/` folder by Composer, JS ones - into `public/assets/plugins/` by Yarn.

1. Deploy project source code into a specified directory
2. Install the 3rd-party libraries
    1. Composer:
        1. (optional) Configure PhpStorm to work with Composer as per instructions [here](https://www.jetbrains.com/help/phpstorm/using-the-composer-dependency-manager.html)
        2. Set up authentication for letting Composer access private packages on GitHub.
            1. Create Personal Access Token at GitHub which has Read Repositories permission enabled;
            2. Run the following command ((replace ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx with your personal access token):
               > ./composer.phar config --global --auth github-oauth.github.com ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
        3. Either run composer installation via PhpStorm, or using the following command:  
           `./composer.phar install`  
           IMPORTANT: Do not run update command unless permitted to do so by Project Technical Lead. IMPORTANT: Run composer install command every time new changes are pulled from the repository.
    2. Yarn:
        1. Install NodeJS in your system as per instructions [here](https://nodejs.org/en/download)
        2. Install Yarn in your system as per instructions [here](https://yarnpkg.com/getting-started/install)
        3. Set up authentication for letting Yarn access private packages:
            1. Create Personal Access Token at GitHub
            2. Create .npmrc file in Officio root directory with the following content (replace ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx with your personal access token):
               @uniquesca:registry=https://npm.pkg.github.com
               //npm.pkg.github.com/:_authToken="ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
               always_auth=true
        4. Install dependencies by running the following command:
           `yarn install`
           IMPORTANT: Do not run upgrade command unless permitted to do so by Project Technical Lead. IMPORTANT: Run yarn install command every time new changes are pulled from the repository.
3. Import MySQL database schema dumps:
    1. Main database schema located at `application/install/<PROJECT>/schema-mysql.sql`
    2. Statistics database schema located at `application/install/schema-mysql-statistics.sql`  
       Note: Webmail installation including database is covered in a separate section.
4. Configure application. Configuration is stored in `config` folder and is split to several files in PHP format. Structure is following:
    - application.config.php - technical settings of the application
        - development.config.php - overrides settings of the application.config.php for developers. Sample is stored in development.config.php.dist file.
    - modules.config.php - list of modules to be used by the application
    - minify.config.php - config for the minification software for JS/CSS files
    - phinx.config.php - config to be used by Phinx when executing migrations
    - AUTOLOAD (this is folder for configs which are to be changed for every installation):
        - global.php - main set of Officio parameters
            - local.php - overrides settings of the global.php. Should contain database credentials and project-specific settings. Sample is stored in local.php.dist, also there are project-specific samples stored in `stubs`
              folder.
            - development.local.php - overrides settings of the global.php. Should contain development-specific params. Sample is stored in development.local.php.dist.
5. Ensure the following directories are writable by the web-server:
    - `backup/`
    - `data/`
    - `data/pdf`
    - `data/xod`
    - `var/cache/`
    - `var/log/`
    - `var/log/payment/`
    - `var/log/xfdf/`
    - `var/pdf_tmp/`
    - `var/tmp/`
    - `var/tmp/uploads/`
    - `var/tmp/lock/`
    - `public/captcha/images/`
    - `public/website/`
    - `public/email/data/`
    - `public/help_files/`
6. Ensure the following scripts are executable by the web-server:
    - TODO Do the list
    - `vendor/bin/phinx`
    - `vendor/robmorgan/phinx/bin/phinx`
7. Run database migrations using Phinx
    - Phinx documentation is [here](https://book.cakephp.org/phinx/0/en/index.html)
    - Phinx binary is located at `vendor/bin/phinx`
    - Phinx configuration is located at `config/phinx.config.php`

   Phinx execution command should look like the following:  
   `vendor/bin/phinx <COMMAND> -c config/phinx.config.php`
8. Configure crontab to execute periodical actions. Crontab has to be configured for the web-server user and should contain the following records:
    * Each 5 minutes (5, 10, 15, .., 55):     
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=3`
    * Hourly:                               
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=0`
    * Daily:                                
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=1`
    * (optional - non-gov Officio installation only) Daily (03:01):                        
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/api/index/run-recurring-payments`
    * Daily (00:01):                        
      `sh /home/officio/secure/scripts/cron/cron-empty-tmp.sh`
    * Weekly (Monday, 00:01):               
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=2`
    * Monthly:                              
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=4`
    * (optional - non-gov Officio installation only) Monthly (1 day at 02:00):             
      `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=8`
9. (optional) Install Webmail - refer to `WEBMAIL INSTALLATION` section of this document
10. After installation is complete, open `officio/public/check.php?show_info` URL in the browser
    in order to verify installation. Complete any missing steps (if any).

### (optional) Webmail installation
Webmail has to be installed in case `mail.enabled` config setting is set to 1. Otherwise, it won't be used.
Webmail is an email client embedded into Officio.

Installation steps are:
1. Put Webmail source code into `public/email` directory.
2. Ensure the following directoy is writable: `public/email/data`
3. Configure database connection in `public/email/data/settings/settings.xml` by setting values to
   `DBName`, `DBLogin` and `DBPassword` parameters.
4. Configure Webmail in `officio/public/email/configUrls.php`   
   Make sure that `mail.salt` setting in the main Officio config file is the same as it is in the `configUrls.php` file.

### (optional) RabbitMQ configuration

1. Install RabbitMQ server including all dependencies.
2. Start RabbitMQ server by command:  
   `service rabbitmq-server start`  
   RabbitMQ server status can be checked using this command:    
   `rabbitmqctl status`
3. Install Python 2.7+
4. Change url and --user agent for cURL due to the current project for subprocesses in rabbit.py script and also change url of file that will be detected if python script is processing, e.g. /tmp/rabbit.pid
5. Place into `/etc/rc.d/rc.local` the string:  
   `rm URL_TO_YOUR_FILE_PYTHON_DETECT/rabbit.pid` (e.g. /tmp/rabbit.pid ) to delete file on system
6. Add the following cron record to execute once every 24 hours:
   `/usr/bin/curl --silent --compressed --location --user-agent Officio_Cron OFFICIO_URL/default/index/cron?p=5`
7. Add the following cron record to execute once every 10 minutes:  
   `python rabbit.py 5`  
   Make sure `python` refers to 2.x version, not 3.x. Otherwise, command should be (replace 2.7 with the actual version):  
   `python2.7 rabbit.py 5`
8. Configure RabbitMQ in application/config/config.ini file.

APPLICATION ADVANCED CONFIGURATION
----------------------------------

### Connection to Immigration Square
-------------------------------------------
Officio has integration with Immigration Square project (used only for Canadian Officio installation).  
This can be enabled/disabled in the config file by changing `marketplace` settings. This integration enables
automatically as soon as correct URLs and OpenSSL settings are provided and are correct.

The following settings are required for successful integration with Immigration Square:

- `marketplace.toggle_status_url`:  should be `https://immigrationsquare.com/api/update-profile`
- `marketplace.create_profile_url`: should be `https://immigrationsquare.com/sp-profile/add-profile`
- `marketplace.edit_profile_url`:  should be `https://immigrationsquare.com/sp-profile/edit-profile`
- `marketplace.private_pem`: full path to private key, e.g. `APPLICATION_TOP_PATH "/application/config/keys/openssl/private.pem"`
- `marketplace.public_pem`: full path to public key, e.g. `APPLICATION_TOP_PATH "/application/config/keys/openssl/public.pem"`
- `marketplace.key`, passphraze

ADDITIONAL INFORMATION
----------------------

### General folders/files structure for `data` folder

**[Local]** means that folder and all its content will be saved on local server.  
**[S3]** means that folder and all its content will be saved on remote server (Amazon S3).  
**[S3 and Local]** means that folder and all its content exists on both local and remote servers.  
**[S3 or Local]** means that folder and all its content will be saved in relation to the company's settings.  
[Local] data/  
[Local]         pdf/  
[Local]         xod/  
[S3 and Local]  blank/  
[S3 or Local]   reconciliation_reports/

# (company id)/

[S3 or Local]   .agents/  
[S3 or Local]   help_article_images/
[Local]         .client_files_other/  
[Local]                            BarcodedPDF/  
[Local]                            DB/  
[Local]                            XDP/  
[Local]                            XFDF/  
[Local]                            Json/  
[Local]                            default_files/  
[S3 or Local]                     logo  
[S3 or Local]  .clients/  
[S3 or Local]  .dependants/  
[S3 or Local]  .dependants/#(case id)/#(dependent id)/original  
[S3 or Local]  .dependants/#(case id)/#(dependent id)/thumbnail  
[S3 or Local]  .dependants/#(case id)/#(dependent id)/checklist/  
[S3 or Local]  .emails/  
[S3 or Local]  .import_clients/  
[S3 or Local]  .import_bcpnp/  
[S3 or Local]  .letter_templates/  
[S3 or Local]  .letterheads/  
[S3 or Local]  .invoices/  
[S3 or Local]  .prospects/

# (prospect id)/

[S3 or Local]  .prospects_files/

# (prospect id)/ # (prospect job id)

[S3 or Local]  .users/  
[S3 or Local]  .users_pua/  
