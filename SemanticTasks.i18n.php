<?php
/**Internationalization messages file for SemanticTasks extension
  *
  * @addtogroup Extensions
*/

$messages = array();

/** English (English)
 * @author Steren Giannini
 */
$messages['en'] = array(
    'semantictasks-desc' => 'Email notifications for assigned or updated tasks',
    'semantictasks-newtask' => 'New task:',
    'semantictasks-taskupdated' => 'Task updated:',
    'semantictasks-assignedtoyou-msg' => "Hello $1,

The task \"$2\" has just been assigned to you",
    'semantictasks-updatedtoyou-msg' => "Hello $1,

The task \"$2\" has just been updated.",
    'semantictasks-reminder' => 'Reminder:',
    'semantictasks-reminder-message' => "Hello $1,

Just to remind you that the task \"$2\" ends in $3 {{PLURAL:$3|day|days}}.

$4",
    'semantictasks-text-message' => "Here is the task description:",
    'semantictasks-diff-message' => "Here are the differences:",
);

/** French (Français)
 */
$messages['fr'] = array(
    'semantictasks-newtask' => 'Nouvelle tâche :',
    'semantictasks-taskupdated' => 'Tâche mise à jour :',
    'semantictasks-assignedtoyou-msg' => "Bonjour $1,

La tâche \"$2\" vous a été assignée.",
    'semantictasks-updatedtoyou-msg' => "Bonjour $1,

La tâche \"$2\" a été mise à jour.",
    'semantictasks-reminder' => 'Rappel :',
    'reminder-message' => "Bonjour $1,
    
N'oubliez pas que la tâche \"$2\" se termine dans $3 jours.

$4",
    'semantictasks-text-message' => "Voici la description de la tâche :",
    'semantictasks-diff-message' => "Les différences sont listées ci-dessous :",
);

