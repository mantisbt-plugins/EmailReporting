Installation:
Extract the complete package to the /mantis/plugins/ directory.
After that you should be able to see it on the "Manage plugins" page


The current version of bug_report_mail support plain text and MIME
encoded e-mails via POP3 Mailaccounts with PEAR's Net_POP3 package.

bug_report_mail is able to recognize if mail is a reply to an already
opened bug and adds the content as a bugnote.

After installing this plugin, you can add a POP3 server's hostname
and authentication data for each of your projects with the Mailbox settings
form.

There are two ways to receive mail with bug_report_mail:
The secure (and default) way is to use a standard reporting user (see 
plugin config page in mantis)

The other way is to signup new user accounts automatically. (see plugin 
config page in mantis)
Now, bug_report_mail will look for an user named like the mail's sender
or an user which mail adress is identical.
If no user is found, then a new account will be created.
The new user's name will be the mail address.

This could be used for attacks, but there is no other way at this moment.

If you like to parse MIME encoded mails, you have to install the PEAR
Mail_Mime package and enable the setting (see plugin config page in mantis)

For parsing HTML mails enable the setting (see plugin config page in mantis)
The mail tmp directory has to be writable

For debugging controls there is a switch to add the complete email to the 
bug as an attachment (see plugin config page in mantis)

If you like to see what bug_report_mail.php is doing, enable debug 
mode (see plugin config page in mantis)

If debug mail directory is a valid directory and also writeable,
the complete mails will be saved to this directory.

Its advisable to keep the mail fetch max at 1 since the parsing of mime 
content can use up a significant amount of memory. But this means that only
one email will be retrieved per mailbox every time bug_report_mail.php is
executed

If you'd like to use the Mail Reporter but don't save the whole message for
making the sender's address available, disable the sender of the email in 
the issue report

If you don't want bug_report_mail.php to delete the mails from your POP3
server disable the setting (see plugin config page in mantis)

With the auth method you may set the AUTH method for your POP3 server.
Default is 'USER', but 'DIGEST-MD5','CRAM-MD5','LOGIN','PLAIN','APOP' 
are also possible

For using the priority of the mails for the bug priority, enable the 
setting (see plugin config page in mantis)

After this, bug_report_mail can be used via cron / scheduled job like this:

Linux or similar OS:
Via webserver (see settings because this is disabled by default, see plugin
config page in mantis)
*/5 *   *   *   * lynx --dump http://mantis.homepage.com/plugins/EmailReporting/scripts/bug_report_mail.php
or via command line interface
*/5 *   *   *   * /usr/local/bin/php /path/to/mantis/plugins/EmailReporting/scripts/bug_report_mail.php

This line fetch bug reports from via POP3 every 5 minutes. 

Windows or similar OS:
Via webserver (see settings because this is disabled by default, see plugin
config page in mantis)
No known method for scheduling this via webserver
or via command line interface (the space between the .php file and the
parameters is important)
c:\php\php.exe c:\path\to\mantis\plugins\EmailReporting\scripts\bug_report_mail.php

As can be seen in the "Sep 2009" changelog, this plugin now has a maximum
size for attachments received by
email. This however still requires significant time for processing because
of the mime decoding

This addon is distributed under the same conditions as Mantis itself.

IMAP addition based on work from Rolf Kleef
IMAP support has been added. Its still a bit experimental but should work
fine.
Here are some explanation about specific IMAP settings

The IMAP base folder is the folder under which Mantis expects to find
subfolders for each project or a single folder for specific project. This
could for instance be "INBOX/to_mantis" (meaning the mail folder
"to_mantis" under the INBOX folder of the account).

If you enable "Create project subfolder structure" for a mailbox, folders
for projects are created under the IMAP base folder if they don't exist
yet. Emails in those subfolder will be imported to their corresponding
projects. If you disable the setting, only emails in the basefolder will
be imported to the project which is defined for the mailbox

Inbox can most likely not be your basefolder, but that might differ between
different imap servers. (Applies only to mailboxes where you enable
"Create project subfolder structure")

The very free format of project names needed to be mapped to a bit more 
restricted format for IMAP folder names. We took these steps, and it might
lead to name collisions or folder names that are a tiny bit different from
their project name counterparts (but we haven't had problems in practice).

	- translate all accented characters to plain ASCII equivalents
	- replace all but alphanum chars and space and colon to dashes
	- replace multiple dots by a single one
	- strip spaces, dots and dashes at the beginning and end

Gerrit Beine, August 2004

Changelog:
Dec 2009
	- Added a fix for the adding of bugnotes when the issue is readonly (~21199)
	- Sometimes the charset names in emails were lowercase while mb_list_encodings returned names using a mix of upper and lower case names. This caused problems for detecting valid encodings used in emails
	- Support for IMAP has been added based on Rolf Kleef's work
	- Added Net_IMAP 1.0.3 to this package. 1.1.0 seems to be broken in some situations so EmailReporting will force the use of 1.0.3
	- Added sanity check to the bug priority array config. If this fails the old value will be retained
	- mail_auth_method (now called mailbox_auth_method) has been moved from the configuration menu to the mailbox settings. This way you can modify this per mailbox
	- Script is now declared compatible with 1.2.0rc2
	- Restored lost functionality to mail_fetch_max. This functionality was lost from version mantis-1.0.3.patch.gz and onwards. You might want to increase the mail_fetch_max value since it wil only retrieve one email per mailbox everytime bug_report_mail.php is executed by default
	- Deleting mailboxes works again. Seems to have been broken since the 0.5.0 release
Okt 2009
	- mb_convert_encoding converted strings even if they were using the same charsets. This caused the necessary problems since somehow certain chars seemed to be replaced with empty spaces.
	- EmailReporting will now try to retrieve the charset from the email content type if it is present, as this works better then the auto detect of mbstring functions for conversion. As a fallback it will still try the auto detect if the charset is missing.
Sep 2009
	- Incase the email address is not encapsulated with the proper chars EmailReporting will consider the whole string the email address (Note ~22839)
	- Problems with locating pear.php have been corrected and pear5.php is now also present in the package
	- Added a limit on the maximum allowed file size so that the emails containing those attachments don't block further processing (uses the max_file_size setting of mantis)
Aug 2009 v2
	- bug_report_mail.sql has been removed to avoid confusions about needing to use it
	- Decrypting the mailbox password did not occur before editing. Fixed (Note ~22655)
	- A new variable is added which resets the plugin schema and fixes the Mail Reporter user when it has the email address "nomail" (Automatically done during the first initialization of the plugin)
	- Removed functions which performed the exclusion of emails to Mail Reporter with "nomail" mail address
Aug 2009
	- The script now also recognizes a possible name in the from part of emails. It uses this as the username during the creation of the user
	- Cleaned up some more useless files that were not used
	- User creation during the installation of the plugin is now handled by mantis api's, schema functions no longer needed.
Jul 2009
	- Modified script to the new plugin architecture of mantis 1.2.0rc1
	- This script no longer needs new fields created in the database. This allows installation on mantis configurations where the database user only has select, insert, update and delete rights
	- custom_file_api.php has been updated to reflect the changes in its original Mantis counterpart
	- Cleaned up functions and files that were no longer usefull and / or necessary
	- Script did not use the default severity and default reproducibility for new bugs, it does now
	- New setting called secured_script to protect it from running on a webserver. Uses the same method as the send_emails.php job
	- File types that are not allowed will now be properly skipped during processing of attachments in emails so that it doesn't cause further errors down the line
	- mail_additional config setting is now called mail_add_complete_email and saves the complete email as an attachment .txt document instead of in the "Additional information" field
	- replaced html2text 1.2 (phphtmlparser) with simplehtmldom 1.11 from http://sourceforge.net/projects/simplehtmldom/
	- Names of attachments will now also be processed by mbstring to convert the character encoding. The content of the attachment does not need to be processed since its stored as binary
	- Parser will try the content-disposition field and the content-type field for a name if the name seems to be missing. As a fallback a alternative name will be provided incase of attachments of type email
	- Added support to use a different port for the pop3 server.
	- A lot of rewrites for the processing in parser.php and mail_api.php. It should now process all attachments properly
	- Reversible encryption will now be applied to the mailbox password
	- Updated the user information inside bug_report_mail.sql. This file is useless now though since the adding of the user is handled by the plugin
	- php.ini var upload-tmp-dir no longer used, the script will use the config mail_tmp_directory for temporarily saving attachments
	- html parsing no longer requires the file to be physically saved. mail_tmp_directory no longer needed here
	- The Mail Reporter user will now be filtered out of the email system as long as he has "nomail" as a email address
	- Identifying bugnotes works again
	- New function which removes automatic mantis emails from bugnotes
Dec 2008
	- Scipt made compatible with Mantis 1.1.6
Sep 2008
	- Check whether php.ini var upload-tmp-dir is empty to avoid attachment problems
Aug 2008
	- Applied a character encoding conversion on incoming emails.
	- New setting in config_defaults_inc.php called $g_mail_encoding (values can contain supported values for this function: http://www.php.net/mb_convert_encoding)
Jul 2008
	- Fixed FILE_MOVE_FAILED to ERROR_FILE_MOVE_FAILED
	- Fixed problem with attachments not being processed properly in disk mode
Jun 2008
	- update to mantis 1.1.2
	- Changes applied based on cas's code' NOTE 0016246
	- Using html2text 1.2 created by Jose Solorzano of Starnetsys, LLC. for html email parsing
	- html parsing on by default now
	- All pear packages updated to latest available versions
	- Necessary query adjustments in category_api.php
Jun 2007
	- update to Mantis 1.1.0a3
	- Support for PHP 4 ( ~13805 )
	- Fixed a bug with processing priority's of emails (priority class variable didn't exist in Mail/Parser.php)
	- Updates to the latest PEAR packages
	- print_r changed to var_dump's (Works better if you have xdebug extension installed)
	- New config setting called:
		# Default bug category for mail reported bugs (Defaults to "default_bug_category")
		# Because in Mantis 1.1.0a3 a category is required, we need a default category for issues reported by email.
		# Make sure the category exists and it has been configured correctly
		$g_mail_default_bug_category = '%default_bug_category%';
	- Fixed a missing variable in the function: "mail_process_all_mails"
		$t_mail_debug was not set and would cause notice level errors and debug mode in this function wouldn't work ( ~13854 )
	- Emails are now not allways saved to disk ( ~13854 )
	- Made sure $t_mail['X-Mantis-Complete'] would allways be populated ( with null value if the config "mail_additional" was disabled )
	- Adding attachments to bug items now also works on Windows systems (Removed hardcoded directory part "/tmp/").
	- The subject of an email is now also trimmed before storing it in $t_bug_data->summary. Like it is in bug_report.php
	- Fixed problem with duplicate attachments ( ~14255 and ~14256 )
Aug 2006
	- update to Mantis 1.0.5
	- mail parsing completely rewritten
	- include additional patches submitted by
		- cas (handling of attachments and empty fields)
		  mail_add_file in core/mail.api
		  mail_add_bug in core/mail.api
May 2006
	- update to Mantis 1.0.3
	- added support for HTML mail
	- added support for encoded mail bodies and subjects
	- changed handling of mail
	- the global mail_debug configuration option is now set OFF by default
	- include the additional patches submitted by
		- EBY (support for priorities and file uploads)
		  file_add in core/file_api.php
		  mail_parse_content in core/mail_api.php
		  config_defaults_inc.php
Dec 2005
	- update to Mantis 1.0.0rc4
	- update to Mantis 0.19.4
Oct 2005
	- update to Mantis 1.0.0rc3
	- update to Mantis 0.19.3
Sep 2005:
	- update to Mantis 1.0.0rc2
	- fixed a bug in getting all categories for a project
		category_get_all_rows in core/category_api.php
Aug 2005:
	- update to Mantis 1.0.0rc1
	- include the additional patches submitted by
		- gernot (Fixed MIME handling and save the mail's sender)
		  mail_get_all_mails in core/mail_api.php
		  mail_parse_content in core/mail_api.php
		  mail_add_bug in core/mail_api.php
		- stevenc (Fixed MIME handling)
		  mail_parse_content in core/mail_api.php
		- rainman (Fixed empty files bug and regex for finding a bug id)
		  mail_add_file in core/mail_api.php
		  mail_get_bug_id_from_subject in core/mail_api.php
Dec 2004:
	- update to Mantis 0.19.2
	- add config: g_mail_parse_mime
	- add config: g_mail_additional
	- add config: g_mail_fetch_max
	- make it working via CLI
Nov 2004:
	- update to Mantis 0.19.1
	- add support for MIME decoding
Sep 2004:
	- update to Mantis 0.19.0
Aug 2004:
	- create patch for Mantis 0.18.3

