<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use Balloon\Filesystem\Node\File;
use Balloon\Hook\AbstractHook;
use MongoDB\GridFS\Exception\FileNotFoundException;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;

class Hook extends AbstractHook
{
    /**
     * Preview.
     *
     * @var Preview
     */
    protected $preview;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(Preview $preview, Async $async, LoggerInterface $logger)
    {
        $this->preview = $preview;
        $this->async = $async;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function preDeleteFile(File $node, bool $force, ?string $recursion, bool $recursion_first): void
    {
        if (true === $force) {
            try {
                $this->preview->deletePreview($node);
            } catch (FileNotFoundException $e) {
                $this->logger->debug('could not remove preview from file ['.$node->getId().'], preview does not exists', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postPutFile(File $node): void
    {
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function postRestoreFile(File $node, int $version): void
    {
        $this->async->addJob(Job::class, [
            'id' => $node->getId(),
        ]);
    }
}
