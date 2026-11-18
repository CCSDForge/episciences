<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

/**
 * Default review request types (#319): every journal has one of each. The value is stored in
 * review_request_type.default_type; a type created by a journal has none.
 *
 * The code names the default mail template of the type (paper_reviewer_invitation_<code>); the review report keeps
 * the three historical templates instead.
 */
enum DefaultRequestType: int
{
    case FullReport = 1;
    case QuickOpinion = 2;

    public function code(): string
    {
        return match ($this) {
            self::FullReport => 'full_report',
            self::QuickOpinion => 'quick_opinion',
        };
    }
}
