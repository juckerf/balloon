<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Office\Api\v1;

use Balloon\App\Api\Controller;
use Balloon\App\Office\Constructor\Http as App;
use Balloon\App\Office\Document;
use Balloon\App\Office\Session as WopiSession;
use Balloon\App\Office\Session\Member;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;
use MongoDB\BSON\ObjectId;

class Session extends Controller
{
    /**
     * App.
     *
     * @var App
     */
    protected $app;

    /**
     * Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * Server.
     *
     * @var Server
     */
    protected $server;

    /**
     * Constructor.
     */
    public function __construct(App $app, Server $server)
    {
        $this->server = $server;
        $this->fs = $server->getFilesystem();
        $this->app = $app;
    }

    /**
     * @api {post} /api/v1/office/session Create session
     * @apiName post
     * @apiVersion 2.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apiUse _getNode
     * @apiDescription Create new session for a document
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/office/session?id=58a18a4ca271f962af6fdbc4"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "code": 201,
     *      "data": {
     *          "id": "544627ed3c58891f058bbbaa",
     *          "wopi_url": "https://localhost",
     *          "access_token": "544627ed3c58891f058b4622",
     *          "access_token_ttl": "1486989000"
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     */
    public function post(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p, File::class);
        $document = new Document($this->fs->getDatabase(), $node);
        $ttl = $this->app->getTokenTtl();

        $session = new WopiSession($this->fs, $document, $ttl);
        $member = new Member($this->fs->getUser(), $ttl);
        $session->join($member)
                ->store();

        return (new Response())->setCode(201)->setBody([
            'code' => 201,
            'data' => [
                'id' => (string) $session->getId(),
                'wopi_url' => $this->app->getWopiUrl(),
                'access_token' => $member->getAccessToken(),
                'access_token_ttl' => ($member->getTTL()->toDateTime()->format('U') * 1000),
            ],
        ]);
    }

    /**
     * @api {post} /api/v1/office/session/join?id=:id Join session
     * @apiName postJoin
     * @apiVersion 2.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Join running session
     * @apiParam (GET Parameter) {string} session_id The session id to join to
     *
     * @apiExample (cURL) example:
     * curl -XPOST "https://SERVER/api/v1/office/session/join?session_id=58a18a4ca271f962af6fdbc4"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "code": 200,
     *      "data": {
     *          "wopi_url": "https://localhost",
     *          "access_token": "544627ed3c58891f058b4622",
     *          "access_token_ttl": "1486989000"
     *      }
     * }
     */
    public function postJoin(ObjectId $id): Response
    {
        $session = WopiSession::getSessionById($this->fs, $id);
        $ttl = $this->app->getTokenTtl();
        $member = new Member($this->fs->getUser(), $ttl);
        $session->join($member)
                ->store();

        return (new Response())->setCode(200)->setBody([
            'code' => 200,
            'data' => [
                'wopi_url' => $this->app->getWopiUrl(),
                'access_token' => $member->getAccessToken(),
                'access_token_ttl' => ($member->getTTL()->toDateTime()->format('U') * 1000),
            ],
        ]);
    }

    /**
     * @api {delete} /api/v1/office/session?id=:id Delete session
     * @apiName delete
     * @apiVersion 2.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Delete a running session. If more members are active in the requested session than only the membership gets removed.
     * The session gets completely removed if only one member exists.
     * @apiParam (GET Parameter) {string} session_id The session id to delete
     * @apiParam (GET Parameter) {string} access_token Access token
     *
     * @apiExample (cURL) example:
     * curl -XDELETE "https://SERVER/api/v1/office/session?session_id=58a18a4ca271f962af6fdbc4&access_token=97223329239823bj223232323"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 204 OK
     */
    public function delete(ObjectId $id, string $access_token): Response
    {
        $session = WopiSession::getByAccessToken($this->server, $id, $access_token);
        $session->leave($this->fs->getUser())
                ->store();

        return (new Response())->setCode(204);
    }
}
