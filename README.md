# Semantic Tasks

Semantic Tasks is an extension to MediaWiki that works in conjunction with Semantic MediaWiki 
to provide task notifications and reminders by email. 

In contrast to the built in notification systems in MediaWiki (watching pages), this extension 
can be used to trigger notifications withour user interaction with the MediaWiki system, e. g. 
by filling in a form (using the PageForms extension) where a user name is entered in a form 
and in the resulting template the respective properties are set to trigger a email 
notification (see below). 

## Requirements

 - PHP 5.3 or later
 - MediaWiki 1.23  or later
 - Semantic MediaWiki 1.8 or later

## Installation

See the detailed [installation guide](docs/INSTALL.md).
    
## Contribution and support

If you have remarks, questions, or suggestions, please ask them on semediawiki-users@lists.sourceforge.net.
You can subscribe to this list [here](https://lists.sourceforge.net/lists/listinfo/semediawiki-user).

If you want to contribute work to the project please subscribe to the developers mailing list.

* [File an issue](https://github.com/SemanticMediaWiki/SemanticTasks/issues)
* [Submit a pull request](https://github.com/SemanticMediaWiki/SemanticTasks/pulls)
* Ask a question on [the mailing list](https://www.semantic-mediawiki.org/wiki/Mailing_list)


## Credits

Semantic Tasks was initially created by Steren Giannini for Creative Commons. 
Currenetly it is sponsored by KDZ - Centre for Public Administration Research.

## License

[GNU General Public License, version 2 or later][gpl-licence], see [COPYING](COPYING) file.

## Manual

* Notification emails are sent when a page is saved. The system looks for the [[Assigned to::*]] and the [[Carbon copy::*]] property.

* Reminder emails. The system looks for the [[Reminder at::*]] and the [[Target date::*]] property. It then send reminders to the assignees.
You must run a cron job once a day to execute the reminder script:
* edit your crontab file: 
```
$ crontab -e
```
* add the following line to execute the script every day at 12: 
```
0 12 * * * php extensions/SemanticTasks/maintenance/checkForReminders.php
```
## Parameters
```
$wgSemanticTasksNotifyIfUnassigned
```
Set to "true" to notify users when they are unassigned from a task (default = "false")
