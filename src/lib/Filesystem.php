<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Balloon\Filesystem\Acl;
use Balloon\Filesystem\Acl\Exception\Forbidden as ForbiddenException;
use Balloon\Filesystem\Delta;
use Balloon\Filesystem\Exception;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\Factory as NodeFactory;
use Balloon\Filesystem\Node\NodeInterface;
use Balloon\Server\User;
use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

class Filesystem
{
    /**
     * Database.
     *
     * @var Database
     */
    protected $db;

    /**
     * LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Root collection.
     *
     * @var Collection
     */
    protected $root;

    /**
     * User.
     *
     * @var Delta
     */
    protected $delta;

    /**
     * Get user.
     *
     * @var User
     */
    protected $user;

    /**
     * Node factory.
     *
     * @var NodeFactory
     */
    protected $node_factory;

    /**
     * Acl.
     *
     * @var Acl
     */
    protected $acl;

    /**
     * Cache.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * RAW Cache.
     *
     * @var array
     */
    protected $raw_cache = [];

    /**
     * Initialize.
     */
    public function __construct(Server $server, Database $db, Hook $hook, LoggerInterface $logger, NodeFactory $node_factory, Acl $acl, ?User $user = null)
    {
        $this->user = $user;
        $this->server = $server;
        $this->db = $db;
        $this->logger = $logger;
        $this->hook = $hook;
        $this->node_factory = $node_factory;
        $this->acl = $acl;
    }

    /**
     * Get user.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Get server.
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * Get database.
     */
    public function getDatabase(): Database
    {
        return $this->db;
    }

    /**
     * Get root.
     */
    public function getRoot(): Collection
    {
        if ($this->root instanceof Collection) {
            return $this->root;
        }

        return $this->root = $this->initNode([
            'directory' => true,
            '_id' => null,
            'owner' => $this->user ? $this->user->getId() : null,
        ]);
    }

    /**
     * Get delta.
     */
    public function getDelta(): Delta
    {
        if ($this->delta instanceof Delta) {
            return $this->delta;
        }

        return $this->delta = new Delta($this, $this->db, $this->acl);
    }

    /**
     * Find raw node.
     */
    public function findRawNode(ObjectId $id): array
    {
        if (isset($this->raw_cache[(string) $id])) {
            return $this->raw_cache[(string) $id];
        }

        $node = $this->db->storage->findOne(['_id' => $id]);
        if (null === $node) {
            throw new Exception\NotFound(
                'node '.$id.' not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $this->raw_cache[(string) $id] = $node;

        return $node;
    }

    /**
     * Factory loader.
     */
    public function findNodeById($id, ?string $class = null, int $deleted = NodeInterface::DELETED_INCLUDE): NodeInterface
    {
        if (isset($this->cache[(string) $id])) {
            return $this->cache[(string) $id];
        }

        if (!is_string($id) && !($id instanceof ObjectId)) {
            throw new Exception\InvalidArgument($id.' node id has to be a string or instance of \MongoDB\BSON\ObjectId');
        }

        try {
            if (is_string($id)) {
                $id = new ObjectId($id);
            }
        } catch (\Exception $e) {
            throw new Exception\InvalidArgument('invalid node id specified');
        }

        $filter = [
            '_id' => $id,
        ];

        switch ($deleted) {
            case NodeInterface::DELETED_INCLUDE:
                break;
            case NodeInterface::DELETED_EXCLUDE:
                $filter['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $filter['deleted'] = ['$type' => 9];

                break;
        }

        $node = $this->db->storage->findOne($filter);

        if (null === $node) {
            throw new Exception\NotFound(
                'node ['.$id.'] not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        $return = $this->initNode($node);

        if (null !== $class && !($return instanceof $class)) {
            throw new Exception('node '.get_class($return).' is not instance of '.$class);
        }

        return $return;
    }

    /**
     * Load node with path.
     */
    public function findNodeByPath(string $path = '', ?string $class = null): NodeInterface
    {
        if (empty($path) || '/' !== $path[0]) {
            $path = '/'.$path;
        }

        $last = strlen($path) - 1;
        if ('/' === $path[$last]) {
            $path = substr($path, 0, -1);
        }

        $parts = explode('/', $path);
        $parent = $this->getRoot();
        array_shift($parts);
        $count = count($parts);

        $i = 0;
        $filter = [];

        foreach ($parts as $node) {
            ++$i;

            if ($count === $i && $class !== null) {
                $filter = [
                    'directory' => ($class === Collection::class),
                ];
            }

            try {
                $parent = $parent->getChild($node, NodeInterface::DELETED_EXCLUDE, $filter);
            } catch (Exception\NotFound $e) {
                if ($count == $i) {
                    $parent = $parent->getChild($node, NodeInterface::DELETED_INCLUDE, $filter);
                } else {
                    throw $e;
                }
            }
        }

        if (null !== $class && !($parent instanceof $class)) {
            throw new Exception('node is not instance of '.$class);
        }

        return $parent;
    }

    /**
     * Load nodes by id.
     */
    public function findNodesById(array $id = [], ?string $class = null, int $deleted = NodeInterface::DELETED_INCLUDE): Generator
    {
        $find = [];
        foreach ($id as $i) {
            $find[] = new ObjectId($i);
        }

        $filter = [
            '_id' => ['$in' => $find],
        ];

        switch ($deleted) {
            case NodeInterface::DELETED_INCLUDE:
                break;
            case NodeInterface::DELETED_EXCLUDE:
                $filter['deleted'] = false;

                break;
            case NodeInterface::DELETED_ONLY:
                $filter['deleted'] = ['$type' => 9];

                break;
        }

        $result = $this->db->storage->find($filter);

        $nodes = [];
        foreach ($result as $node) {
            try {
                $return = $this->initNode($node);

                if (in_array($return->getId(), $nodes)) {
                    continue;
                }

                $nodes[] = $return->getId();
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                continue;
            }

            if (null !== $class && !($return instanceof $class)) {
                throw new Exception('node is not an instance of '.$class);
            }

            yield $return;
        }
    }

    /**
     * Load nodes by id.
     */
    public function findNodesByPath(array $path = [], ?string $class = null): Generator
    {
        $find = [];
        foreach ($path as $p) {
            if (empty($path) || '/' !== $path[0]) {
                $path = '/'.$path;
            }

            $last = strlen($path) - 1;
            if ('/' === $path[$last]) {
                $path = substr($path, 0, -1);
            }

            $parts = explode('/', $path);
            $parent = $this->getRoot();
            array_shift($parts);
            foreach ($parts as $node) {
                $parent = $parent->getChild($node, NodeInterface::DELETED_EXCLUDE);
            }

            if (null !== $class && !($parent instanceof $class)) {
                throw new Exception('node is not an instance of '.$class);
            }

            yield $parent;
        }
    }

    /**
     * Load nodes by id.
     *
     * @param null|mixed $class
     */
    public function getNodes(?array $id = null, ?array $path = null, $class = null, int $deleted = NodeInterface::DELETED_EXCLUDE): Generator
    {
        if (null === $id && null === $path) {
            throw new Exception\InvalidArgument('neither parameter id nor p (path) was given');
        }
        if (null !== $id && null !== $path) {
            throw new Exception\InvalidArgument('parameter id and p (path) can not be used at the same time');
        }
        if (null !== $id) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_INCLUDE;
            }

            return $this->findNodesById($id, $class, $deleted);
        }
        if (null !== $path) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_EXCLUDE;
            }

            return $this->findNodesByPath($path, $class);
        }
    }

    /**
     * Load node.
     *
     * @param null|mixed $id
     * @param null|mixed $path
     * @param null|mixed $class
     */
    public function getNode($id = null, $path = null, $class = null, bool $multiple = false, bool $allow_root = false, ?int $deleted = null): NodeInterface
    {
        if (empty($id) && empty($path)) {
            if (true === $allow_root) {
                return $this->getRoot();
            }

            throw new Exception\InvalidArgument('neither parameter id nor p (path) was given');
        }
        if (null !== $id && null !== $path) {
            throw new Exception\InvalidArgument('parameter id and p (path) can not be used at the same time');
        }
        if (null !== $id) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_INCLUDE;
            }

            if (true === $multiple && is_array($id)) {
                return $this->findNodesById($id, $class, $deleted);
            }

            return $this->findNodeById($id, $class, $deleted);
        }
        if (null !== $path) {
            if (null === $deleted) {
                $deleted = NodeInterface::DELETED_EXCLUDE;
            }

            return $this->findNodeByPath($path, $class);
        }
    }

    /**
     * Find node with custom filter.
     */
    public function findNodeByFilter(array $filter): NodeInterface
    {
        $result = $this->db->storage->findOne($filter);
        if (null === $result) {
            throw new Exception\NotFound(
                'node with custom filter was not found',
                Exception\NotFound::NODE_NOT_FOUND
            );
        }

        return $this->initNode($result);
    }

    /**
     * Find nodes with custom filters.
     */
    public function findNodesByFilter(array $filter, ?int $offset = null, ?int $limit = null): Generator
    {
        $result = $this->db->storage->find($filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);

        $count = $this->db->storage->count($filter);

        foreach ($result as $node) {
            try {
                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return $count;
    }

    /**
     * Get custom filtered children.
     */
    public function findNodesByFilterUser(int $deleted, array $filter, ?int $offset = null, ?int $limit = null): Generator
    {
        $shares = $this->user->getShares();
        $stored_filter = ['$and' => [
            [],
            ['$or' => [
                ['owner' => $this->user->getId()],
                ['shared' => ['$in' => $shares]],
            ]],
        ]];

        if (NodeInterface::DELETED_EXCLUDE === $deleted) {
            $stored_filter['$and'][0]['deleted'] = false;
        } elseif (NodeInterface::DELETED_ONLY === $deleted) {
            $stored_filter['$and'][0]['deleted'] = ['$type' => 9];
        }

        $stored_filter['$and'][0] = array_merge($filter, $stored_filter['$and'][0]);

        $result = $this->db->storage->find($stored_filter, [
            'skip' => $offset,
            'limit' => $limit,
        ]);

        $count = $this->db->storage->count($stored_filter);

        foreach ($result as $node) {
            try {
                yield $this->initNode($node);
            } catch (\Exception $e) {
                $this->logger->error('remove node from result list, failed load node', [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);
            }
        }

        return $count;
    }

    /**
     * Init node.
     */
    public function initNode(array $node): NodeInterface
    {
        $id = $node['_id'];

        if (isset($node['shared']) && true === $node['shared'] && null !== $this->user && $node['owner'] != $this->user->getId()) {
            $node = $this->findReferenceNode($node);
        }

        if (isset($node['parent'])) {
            $parent = $this->findNodeById($node['parent']);
        } elseif ($node['_id'] !== null) {
            $parent = $this->getRoot();
        } else {
            $parent = null;
        }

        if (!array_key_exists('directory', $node)) {
            throw new Exception('invalid node ['.$node['_id'].'] found, directory attribute does not exists');
        }

        $instance = $this->node_factory->build($this, $node, $parent);

        if (!$this->acl->isAllowed($instance, 'r')) {
            if ($instance->isReference()) {
                $instance->delete(true);
            }

            throw new ForbiddenException(
                'not allowed to access node',
                ForbiddenException::NOT_ALLOWED_TO_ACCESS
            );
        }

        $loaded = isset($this->cache[(string) $node['_id']]);

        if ($loaded === false) {
            $this->cache[(string) $node['_id']] = $instance;
        }

        if ($loaded === false && isset($node['destroy']) && $node['destroy'] instanceof UTCDateTime && $node['destroy']->toDateTime()->format('U') <= time()) {
            $this->logger->info('node ['.$node['_id'].'] is not accessible anmyore, destroy node cause of expired destroy flag', [
                'category' => get_class($this),
            ]);

            $instance->delete(true);

            throw new Exception\Conflict('node is not available anymore');
        }

        if (PHP_SAPI === 'cli') {
            unset($this->cache[(string) $node['_id']]);
        }

        return $instance;
    }

    /**
     * Resolve shared node to reference or share depending who requested.
     */
    protected function findReferenceNode(array $node): array
    {
        if (isset($node['reference']) && ($node['reference'] instanceof ObjectId)) {
            $this->logger->debug('reference node ['.$node['_id'].'] requested from share owner, trying to find the shared node', [
                'category' => get_class($this),
            ]);

            $result = $this->db->storage->findOne([
                'owner' => $this->user->getId(),
                'shared' => true,
                '_id' => $node['reference'],
            ]);

            if (null === $result) {
                throw new Exception\NotFound(
                    'no share node for reference node '.$node['reference'].' found',
                    Exception\NotFound::SHARE_NOT_FOUND
                );
            }
        } else {
            $this->logger->debug('share node ['.$node['_id'].'] requested from member, trying to find the reference node', [
                'category' => get_class($this),
            ]);

            $result = $this->db->storage->findOne([
                'owner' => $this->user->getId(),
                'shared' => true,
                'reference' => $node['_id'],
            ]);

            if (null === $result) {
                throw new Exception\NotFound(
                    'no share reference for node '.$node['_id'].' found',
                    Exception\NotFound::REFERENCE_NOT_FOUND
                );
            }
        }

        return $result;
    }
}
