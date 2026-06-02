<?php

declare(strict_types=1);

namespace Gracious\FeatureFlagBundle\Exception;

use Gracious\FeatureFlagBundle\Flag\Flag;

interface ExceptionFactoryInterface
{
    /**
     * @param bool $required the enabled state the guard expected but did not get
     */
    public function create(Flag $flag, bool $required): \Throwable;
}
