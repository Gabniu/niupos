<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain;

enum ChannelRegistrationStatus: string
{
    case Draft = 'draft';
    case ApprovalRequired = 'approval_required';
    case Approved = 'approved';
    case Published = 'published';
}
