<?php

// Entry point for processing inbound COAR Notify submissions from the HAL inbox.
// @see https://notify.coar-repositories.org/patterns/request-review/
// @see https://notify.coar-repositories.org/patterns/request-endorsement/

require_once "InboxNotifications.php";

(new InboxNotifications())->run();
