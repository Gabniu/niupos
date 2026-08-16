<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain;

enum ChannelSelection: string
{
    case Pos = 'pos';
    case Web = 'web';
    case Mobile = 'mobile';
    case WebMobile = 'web_mobile';

    public function includesWeb(): bool
    {
        return $this === self::Web || $this === self::WebMobile;
    }

    public function includesMobile(): bool
    {
        return $this === self::Mobile || $this === self::WebMobile;
    }
}
