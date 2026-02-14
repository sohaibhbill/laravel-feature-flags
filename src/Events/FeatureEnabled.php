<?php

namespace MugiWara\FeatureFlags\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeatureEnabled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        /** The feature flag name that was enabled. */
        public readonly string $feature,
        /** The tenant context at the time of the change, or null for global flags. */
        public readonly ?string $tenantId = null,
    ) {}
}
