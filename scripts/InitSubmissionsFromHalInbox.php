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
        'inbox' => isset($params['app_env']) ? InboxNotifications::HAL_INBOX_URL[$script->getParam('app_env')] : NOTIFY_TARGET_HAL_INBOX,
        'type' => InboxNotifications::INBOX_SERVICE_TYPE
    ]);




$script->run();







