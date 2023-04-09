<?php

require_once "InboxNotifications.php";

class InitSubmissionsFromHalInbox extends InboxNotifications
{
}

// https://notify.coar-repositories.org/patterns/request-review/

$script = new InitSubmissionsFromHalInbox();
$script
    ->setCoarNotifyId(NOTIFY_TARGET_HAL_URL)
    ->setCoarNotifyType([
        'Offer',
        'coar-notify:ReviewAction'
    ])
    ->setCoarNotifyOrigin([
        'id' => NOTIFY_TARGET_HAL_URL, // defined in pwd.json
        'inbox' => InboxNotifications::HAL_INBOX_URL[$script->getParam('app_env')],
        'type' => InboxNotifications::INBOX_SERVICE_TYPE
    ]);

$script->run();







