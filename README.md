# Semantic Tasks

Semantic Tasks is an extension to MediaWiki that works in conjunction with another extension, Semantic MediaWiki, to provide email task notifications and reminders.

Notes on installing Semantic tasks can be found in the file INSTALL.


## Credits

Semantic Tasks was created by Steren Giannini for Creative Commons.

## Manual

* Notification emails are sent when a page is saved. The system looks for the [[Assigned to::*]] and the [[Carbon copy::*]] property.

* Reminder emails. The system looks for the [[Reminder at::*]] and the [[Target date::*]] property. It then send reminders to the assignees.
You must run a cron job once a day to execute the reminder script:
    * edit your crontab file: 
        $ crontab -e
    * add the following line to execute the script every day at 12: 
        0 12 * * * php extensions/SemanticTasks/ST_CheckForReminders.php

## Contact

For comments, questions, suggestions or bug reports please contact steren.giannini@gmail.com
