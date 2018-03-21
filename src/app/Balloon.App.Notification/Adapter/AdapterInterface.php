<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Adapter;

use Balloon\App\Notification\MessageInterface;
use Balloon\Server\User;

interface AdapterInterface
{
    /**
     * Send notification.
     *
     * @param User             $receiver
     * @param User             $sender
     * @param MessageInterface $message
     * @param array            $context
     *
     * @return bool
     */
    public function notify(User $receiver, ?User $sender, MessageInterface $message, array $context = []): bool;
}