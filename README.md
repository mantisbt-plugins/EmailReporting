# MantisBT EmailReporting Plugin
Overview
========
The EmailReporting plugin allows you to report an issue in Mantis by sending an email to a particular mail account.

Features
========
* Create an issue
* Add notes to an existing issue
* Add attachments
* Filter email accounts

Requirements
============
EmailReporting v0.12.0 and later versions:
------------------------------------------

* MantisBT 2.0.0 or higher
* PHP 8.1 or higher

EmailReporting v0.10.0 and v0.11.0:
-----------------------------------

* MantisBT 1.3.0 until 2.28.4

EmailReporting v0.9.x:
----------------------

* MantisBT 1.2.6 until 1.3.99

Optional:
---------

* PHP 7.0 is supported from EmailReporting 0.9.2 and higher
* PHP 7.1 is supported from EmailReporting 0.10.0 and higher
* PHP 8.0-8.5 is supported from EmailReporting 0.11.0 and higher

EmailReporting v0.8.4 and earlier versions:
-------------------------------------------

* MantisBT 1.2.0 until 1.2.5

All versions:
-------------

* Ability to set scheduled / cron jobs on the webserver
* /api/soap/mc_file_api.php is required for EmailReporting to function properly

Includes thirdparty packages through Composer
=============================================
* [Composer](https://getcomposer.org/)

PEAR
----
* [core pear files (pear.php)](https://pear.php.net) -> Fork: [pear/pear-core-minimal](https://packagist.org/packages/pear/pear-core-minimal)
* [Auth_SASL](https://pear.php.net/package/Auth_SASL) -> Fork: [SL-Gundam/Auth_SASL](https://github.com/SL-Gundam/Auth_SASL)
* [Mail_mimeDecode](https://pear.php.net/package/Mail_mimeDecode) -> Fork: [SL-Gundam/Mail_mimeDecode](https://github.com/SL-Gundam/Mail_mimeDecode)
* [Net_IMAP](https://pear.php.net/package/Net_IMAP) -> Fork: [SL-Gundam/Net_IMAP](https://github.com/SL-Gundam/Net_IMAP)
* [Net_POP3](https://pear.php.net/package/Net_POP3) -> Fork: [SL-Gundam/Net_POP3](https://github.com/SL-Gundam/Net_POP3)
* [Net_Socket](https://pear.php.net/package/Net_Socket) -> Fork: [SL-Gundam/Net_Socket](https://github.com/SL-Gundam/Net_Socket)

Libraries
---------
* [PHP Simple HTML DOM Parser](http://sourceforge.net/projects/simplehtmldom/) -> Fork: [php-core/simplehtmldom](https://packagist.org/packages/php-core/simplehtmldom)
* [pixel418/markdownify](https://packagist.org/packages/pixel418/markdownify) -> Fork: [SL-Gundam/Markdownify](https://github.com/SL-Gundam/Markdownify)
* [willdurand/email-reply-parser](https://packagist.org/packages/willdurand/email-reply-parser)
* [qualityunit/tnef-decoder](https://packagist.org/packages/qualityunit/tnef-decoder)
* [firebase/php-jwt](https://packagist.org/packages/firebase/php-jwt)
* [google/auth](https://packagist.org/packages/google/auth)
* [guzzlehttp/guzzle](https://packagist.org/packages/guzzlehttp/guzzle)
* [phpseclib/phpseclib](https://packagist.org/packages/phpseclib/phpseclib)

Plus any dependencies these packages require.

Composer
========
EmailReporting 0.12.0 and later versions make use of Composer.
This is not important if you download from the [releases](https://github.com/mantisbt-plugins/EmailReporting/releases) page.
Otherwise you will need to install the Composer dependencies yourself using:
```
composer install
```

Download
========

The stable releases can be downloaded from the GitHub downloads page: https://github.com/mantisbt-plugins/EmailReporting/releases
The development versions are not meant for production environments. Use at your own risk

Source code
-----------
EmailReporting plugin is hosted in GitHub along with other MantisBT plugins. GitHub URL: https://github.com/mantisbt-plugins/EmailReporting

Support
========
Documentation
-------------
https://www.mantisbt.org/wiki/doku.php/mantisbt:plugins:emailreporting

Forum
-----
Please use forum to get help in installing and using EmailReporting plugin. Visit [EmailReporting Forum](https://www.mantisbt.org/forums/viewforum.php?f=13)

Bug Tracker
-----------
To report an issue or feature request for EmailReporting plugin, visit [Mantis BugTracker - EmailReporting project](http://www.mantisbt.org/bugs/set_project.php?project_id=10). (Make sure that you select the correct project from the drop-down)
