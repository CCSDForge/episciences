<?php

require_once "InboxNotifications.php";

class InitSubmissionsFromHalInbox extends InboxNotifications
{
}

// https://notify.coar-repositories.org/patterns/request-review/

$script = new InitSubmissionsFromHalInbox();

$script
    ->setCoarNotifyId(NOTIFY_TARGET_HAL_URL) // repository URL
    ->setCoarNotifyType([   // expected type
        'Offer',
        'coar-notify:ReviewAction'
    ])
    ->setCoarNotifyOrigin([ // origin of request
        'id' => NOTIFY_TARGET_HAL_URL, // defined in pwd.json
        'inbox' => NOTIFY_TARGET_HAL_INBOX, // defined in pwd.json
        'type' => InboxNotifications::INBOX_SERVICE_TYPE
    ]);




$script->run();







