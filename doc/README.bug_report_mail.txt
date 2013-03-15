Installation:
1. Extract the complete package to the /mantis/plugins/ directory.
2. Rename the new folder to EmailReporting if necessary. If everything
has been done correctly you should be able to find a file called
EmailReporting.php plus some folders in the /mantis/plugins/EmailReporting/
folder
3. After that you should be able to see it on the "Manage Plugins" page
with its proper version number (check this in case of an upgrade)

Upgrade:
If you are performing an upgrade of your existing installation of
EmailReporting, all you need todo (as long you have not been editing
files yourself) is
1. Delete the EmailReporting folder from the plugins directory
2. Follow the instructions above for the installation of EmailReporting


Support:
The current version of bug_report_mail support plain text, html and
MIME encoded e-mails via POP3 and IMAP Mailaccounts with PEAR's
Net_POP3 and Net_IMAP package. Support for Outlook RTF formatted
(winmail.dat, ATT0000?.dat , application/ms-tnef, tnef) emails is
not fully supported


Notes:
bug_report_mail is able to recognize if an email is a reply to an
already opened issue and adds the content as a note.


Mailboxes:
After installing this plugin, you can add a mail server's hostname
and authentication data for each of your projects with the Manage
mailboxes form.


Mail Reporters:
There are two ways to receive mail with bug_report_mail:
The secure (and default) way is to use a standard reporting user (see 
plugin config page in mantis)

The other way is to signup new user accounts automatically. (see plugin 
config page in mantis)
Now, bug_report_mail will look for a user with a mail address identical
to the from email address. If no user is found, then a new account will
be created. The new user's name will be the mail address.

This could be used for attacks, but there is no other way at this moment.

It's possible to select the preferred username layouts for new user
creations. The "Get from LDAP" uses the email address to find a user if
you have enabled LDAP as the login method (login_method).


MIME encoded mails:
If you like to parse MIME encoded mails, you have to install the PEAR
Mail_mimeDecode package and enable the setting (see plugin config page
in mantis)

As can be seen in the "Sep 2009" changelog, this plugin now has a maximum
size for attachments received by email. This however still requires 
significant time for processing because of the mime decoding


HTML mails:
For parsing HTML mails enable the setting (see plugin config page in mantis)


Debug mode:
For debugging controls there is a switch to add the complete email to the 
bug as an attachment (see plugin config page in mantis)

If you like to see what bug_report_mail.php is doing, enable debug 
mode (see plugin config page in mantis)

If debug mail directory is a valid directory and also writeable,
the complete mails will be saved to this directory.


Fetching of emails:
Its advisable to keep the mail fetch max at 1 since the parsing of mime 
content can use up a significant amount of memory. But this means that only
one email will be retrieved every time bug_report_mail.php is executed

Also make sure you set the 'max_file_size' setting for MantisBT to the maximum
attachment size allowed. This includes not only what you want to allow but also
what your file system or database system is able to accept. MySQL for example
has a size limit which could break the processing of emails. I believe the
default for MySQL is 1MB
Reference: http://dev.mysql.com/doc/refman/5.5/en/packet-too-large.html


Deleting emails:
If you don't want bug_report_mail.php to delete the mails from your POP3/IMAP
server disable the setting (see plugin config page in mantis). POP3 only
processes unread emails. IMAP on the other hand processes read and unread
emails. Because of this IMAP will always mark the emails as deleted after
they have been processed but it will neglect to perform the expunge command
which would delete them permanently.


Authenthication methods:
With the auth method you may set the AUTH method for your POP3/IMAP server.
Default is 'USER', but 'DIGEST-MD5','CRAM-MD5','LOGIN','PLAIN','APOP' 
are also possible


Priority of emails:
For using the priority of the mails for the bug priority, enable the 
setting (see plugin config page in mantis)


Scheduling a job for bug_report_mail:
bug_report_mail can be used via scheduled job like this:

Linux or similar OS using Cron jobs:
Via webserver (see settings because this is disabled by default, see plugin
config page in mantis)
*/5 *   *   *   * lynx --dump http://mantis.homepage.com/plugins/EmailReporting/scripts/bug_report_mail.php
or via command line interface
*/5 *   *   *   * /usr/local/bin/php /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail.php

This line fetches bug reports via POP3 or IMAP every 5 minutes. 

Windows or similar OS:
Via webserver (see settings because this is disabled by default, see plugin
config page in mantis)
No known method for scheduling this via webserver
or via command line interface
c:\php\php.exe c:\path\to\mantis\plugins\EmailReporting\scripts\bug_report_mail.php

For correct operation of the scheduled job its advised that the job and
the webserver are operating under the same OS user account. If this is not
possible, you should either not use the DISK storage method
(file_upload_method) for MantisBT or you should adjust the global variable
attachments_file_permissions with the proper rights. In general its possible
to specify within the scheduled/cron job line which user should run the job.


IMAP:
IMAP addition based on work from Rolf Kleef
IMAP support has been added. Its still a bit experimental but should work
fine.
Here are some explanation about specific IMAP settings


IMAP basefolders:
The IMAP base folder is the folder under which Mantis expects to find
subfolders for each project or a single folder for specific project. This
could for instance be "INBOX/to_mantis" (meaning the mail folder
"to_mantis" under the INBOX folder of the account).

If you enable "Create project subfolder structure" for a mailbox, folders
for projects are created under the IMAP base folder if they don't exist
yet. Emails in those subfolders will be imported to their corresponding
projects. If you disable this setting, only emails in the basefolder will
be imported to the project which is defined for the mailbox

Inbox can possibly not be your basefolder if you enable "Create project
subfolder structure", but that might differ between different imap
servers.

If you are having problems selecting the Inbox folder as your basefolder,
try leaving the mailbox setting for basefolder empty. It should select the
Inbox folder by default.


IMAP folder names
The very free format of project names needed to be mapped to a bit more 
restricted format for IMAP folder names. We took these steps, and it might
lead to name collisions or folder names that are a tiny bit different from
their project name counterparts (but we haven't had problems in practice).

	- translate all accented characters to plain ASCII equivalents
	- replace all but alphanum chars and space and colon to dashes
	- replace multiple dots by a single one
	- strip spaces, dots and dashes at the beginning and end


Email address validation:
All from email addresses will be validated. The validation is done by the
email_is_valid function which checks for several things based on certain
core mantis configuration options, namely:
validate_email
use_ldap_email
allow_blank_email
limit_email_domain
check_mx_record


Plugins that depend on EmailReporting:
It possible for plugins to depend on the EmailReporting plugin. There are
some functions available that improve the ability to integrate those
plugins. Please have a look at core/config_api.php for the functions
available.

EVENT_ERP_OUTPUT_MAILBOX_FIELDS allows plugins to add extra mailbox form
fields. Its highly advised you use ERP_output_config_option for outputting
the options. ERP_output_config_option is only meant to be used by the
EmailReporting plugin or within by other plugins while this event is
triggered. If you don't use that function you will need to structure the
names of the input form fields yourself to the following format (brackets
included): 'plugin_content[' . plugin_get_current() . '][variable name]'

Two other events are available which perform the same function as their
core mantis counterparts. But these are only triggered when a issue report
or note is added by EmailReporting.
EVENT_ERP_BUGNOTE_DATA
EVENT_ERP_REPORT_BUG_DATA

Please consult the function within EmailReporting/core/config_api.php for
functions which can help you to access mailbox information, including the
possible fields you added with the EVENT_ERP_OUTPUT_MAILBOX_FIELDS event


Included PEAR packages within this distribution are:
Auth_SASL
Mail_mimeDecode
Net_POP3
Net_IMAP
Net_Socket
core pear files (pear.php and pear5.php)

All of these packages (except for Net_IMAP, see below) are the latest
available versions at the moment of release of this plugin. If you don't
need these PEAR packages you can delete the whole core_pear directory.

Net_IMAP 1.0.3 is forced since 1.1.0 does not work with this plugin
because of a bug. Therefore it is not present in the core_pear directory.

PHP Simple HTML DOM Parser - http://sourceforge.net/projects/simplehtmldom/
This package is not a PEAR package but is included to convert html content to
text content.


PHP extensions:
The following PHP extensions will be used when they are available but are
not required for this plugin to work.
OpenSSL - provides connection encryption functionality
mbstring - Enables EmailReporting to convert charsets in emails


Copyright:
This addon is distributed under the same conditions as Mantis itself.

Gerrit Beine, August 2004
