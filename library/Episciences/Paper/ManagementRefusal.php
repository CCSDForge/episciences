<?php

declare(strict_types=1);

namespace Episciences\Paper;

/**
 * Why the logged user may not change a paper. Each caller turns it into its own answer
 * (redirection with a message for a form, 403 for an Ajax action).
 */
enum ManagementRefusal
{
    /** The paper belongs to another journal */
    case OTHER_JOURNAL;

    /** The paper was deleted by its author or removed by the journal */
    case DELETED_OR_REMOVED;

    /** The user submitted the paper, or declared a conflict of interest with it */
    case OWN_SUBMISSION_OR_CONFLICT;

    /** The user's roles do not give access to the paper */
    case PERMISSIONS;
}
