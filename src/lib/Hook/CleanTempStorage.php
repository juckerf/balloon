<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Hook;

use Balloon\Async\CleanTempStorage as Job;
use TaskScheduler\Async;

class CleanTempStorage extends AbstractHook
{
    /**
     * Execution interval.
     *
     * @var int
     */
    protected $interval = 172800;

    /**
     * max age.
     *
     * @var int
     */
    protected $max_age = 172800;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Constructor.
     *
     * @param iterable $config
     */
    public function __construct(Async $async, ?Iterable $config = null)
    {
        $this->async = $async;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     */
    public function setOptions(?Iterable $config = null): HookInterface
    {
        if (null === $config) {
            return $this;
        }

        foreach ($config as $option => $value) {
            switch ($option) {
                case 'max_age':
                case 'interval':
                    $this->{$option} = (int) $value;

                break;
                default:
                    throw new Exception('invalid option '.$option.' given');
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function preExecuteAsyncJobs(): void
    {
        $this->async->addJobOnce(Job::class, [
            'max_age' => $this->max_age,
        ], [
            'interval' => $this->interval,
        ]);
    }
}
