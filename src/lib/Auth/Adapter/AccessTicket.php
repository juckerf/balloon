<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Auth\Adapter;

use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server\User;
use Micro\Auth\Adapter\AdapterInterface;
use Micro\Auth\Adapter\AbstractAdapter;
use Psr\Log\LoggerInterface;

class AccessTicket extends AbstractAdapter
{
    /**
     * Algorithm.
     *
     * @var string
     */
    protected $algorithm = 'sha256';

    /**
     * Key.
     *
     * @var string
     */
    protected $key = 'secret';

    /**
     * Route matching pattern.
     *
     * @var string
     */
    protected $route_pattern = '/^\/api\/v2\/(?:nodes|files)(?:\/([a-z0-9]+))?\/content(?:\?.*(?:id=([a-z0-9]+)))?/';

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Idenitfier.
     *
     * @var string
     */
    protected $identifier;

    /**
     * Init adapter.
     *
     * @param LoggerInterface $logger
     * @param iterable        $config
     */
    public function __construct(LoggerInterface $logger, ?Iterable $config = null)
    {
        $this->logger = $logger;
        $this->setOptions($config);
    }

    /**
     * Set options.
     *
     * @param iterable $config
     *
     * @return AdapterInterface
     */
    public function setOptions(? Iterable $config = null): AdapterInterface
    {
        if (null === $config) {
            return $this;
        }
        foreach ($config as $option => $value) {
            switch ($option) {
                case 'algorithm':
                case 'key':
                case 'route_pattern':
                    $this->{$option} = (string) $value;
                    unset($config[$option]);
                break;
            }
        }
        return  parent::setOptions($config);
    }

    /**
     * Authenticate.
     *
     * @return bool
     */
    public function authenticate(): bool
    {
        $matches = [];
        if (
            \preg_match($this->route_pattern, $_SERVER['REQUEST_URI'], $matches) &&
            \count($matches) > 1
        ) {
            $nodeId = \array_values(\array_filter($matches, function ($match) {
                return '' !== $match;
            }))[1];

            if (!\array_key_exists('ticket', $_GET)) {
                return false;
            }
            // ticket has the format: ['username', 'nodeid', 'expiration_timestamp', 'hmac_signature']
            $ticket = \explode(';', \base64_decode($_GET['ticket']));

            if ( \count($ticket) < 4 ||
                !\hash_equals(\hash_hmac($this->algorithm, \implode(';', \array_slice($ticket, 0, 3)), $this->key), $ticket[3]) ||
                (new \DateTime())->getTimestamp() > $ticket[2] ||
                $nodeId !== $ticket[1]
            ) {
                $this->logger->debug('access ticket authentication failed with ticket [' . \base64_decode($_GET['ticket']) . ']', [
                    'category' => get_class($this),
                ]);
                return false;
            }
            $this->logger->debug('access ticket authentication succeeded for ticket [' . \base64_decode($_GET['ticket']) . ']', [
                'category' => get_class($this),
            ]);
            $this->identifier = $ticket[0];
            return true;
        }
        return false;
    }

    public function createTicket(User $user, NodeInterface $node): string
    {
        $ticket = [
            $user->getUsername(),
            $node->getId(),
            (new \DateTime())->getTimestamp() + 120,
        ];
        $ticket[] = hash_hmac($this->algorithm, \implode(';',$ticket), $this->key);
        return \base64_encode(\implode(';', $ticket));
    }

    /**
     * Get identifier.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get attributes.
     *
     * @return array
     */
    public function getAttributes(): array
    {
        return [];
    }
}
