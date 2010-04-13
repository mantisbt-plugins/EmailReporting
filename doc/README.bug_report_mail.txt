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
size for attachments received by email. This however still requires 
significant time for processing because of the mime decoding

Scheduling bug_report_mail.php as a cron / scheduled job is no longer a
requirement, but is recommended for performance purposes and because a page
visit is required to trigger this. By default this plugin assumes you will
be creating a cron / scheduled job. You can change the setting in the
configuration page

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
yet. Emails in those subfolders will be imported to their corresponding
projects. If you disable this setting, only emails in the basefolder will
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

All from email addresses will be validated. The validation is done by the
email_is_valid function which checks for several things based on certain
core mantis configuration options, namely:
validate_email
use_ldap_email
allow_blank_email
limit_email_domain
check_mx_record


Gerrit Beine, August 2004
