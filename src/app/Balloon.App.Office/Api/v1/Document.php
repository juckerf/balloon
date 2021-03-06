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
use Balloon\App\Office\Document as OfficeDoc;
use Balloon\App\Office\Template;
use Balloon\Filesystem;
use Balloon\Filesystem\Node\Collection;
use Balloon\Filesystem\Node\File;
use Balloon\Server;
use Micro\Http\Response;

class Document extends Controller
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
     * @api {get} /api/v1/office/document?id=:id Get document
     * @apiName get
     * @apiVersion 1.0.0
     * @apiUse _getNode
     * @apiGroup App\Office
     * @apiPermission none
     * @apiDescription Retreive office document
     *
     * @apiExample (cURL) example:
     * curl -XGET "https://SERVER/api/v1/app/office/document/544627ed3c58891f058b4611"
     * curl -XGET "https://SERVER/api/v1/app/office/document?id=544627ed3c58891f058b4611"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *      "status": 200,
     *      "data": {
     *          "loleaflet": "https:\/\/officeserver:9980\/loleaflet\/dist\/loleaflet.html",
     *          "sessions": []
     *      }
     * }
     *
     * @param string $id
     * @param string $p
     */
    public function get(?string $id = null, ?string $p = null): Response
    {
        $node = $this->fs->getNode($id, $p, File::class);
        $document = new OfficeDoc($this->fs->getDatabase(), $node);
        $sessions = [];

        foreach ($document->getSessions() as $session) {
            $sessions[] = [
                'id' => (string) $session['_id'],
                'created' => $session['_id']->getTimestamp(),
                'user' => [
                    'id' => (string) $session['user'],
                    'name' => $this->server->getUserById($session['user'])->getUsername(),
                ],
            ];
        }

        $result = [
            'code' => 200,
            'data' => [
                'loleaflet' => $this->app->getLoleaflet(),
                'session' => $sessions,
            ],
        ];

        return (new Response())->setCode(200)->setBody($result);
    }

    /**
     * @api {put} /api/v1/office/document Create new empty document
     * @apiName put
     * @apiVersion 1.0.0
     * @apiGroup App\Office
     * @apiPermission none
     * @apuUse _conflictNode
     * @apiDescription Create new document from an existing office document template, option type has to be one of the follwing:
     *  - xlsx  => "Office Open XML Spreadsheet",
     *  - xls   => "Microsoft Excel 97-2003",
     *  - xlt   => "Microsoft Excel 97-2003 Template",
     *  - csv   => "Text CSV",
     *  - ods   => "ODF Spreadsheet",
     *  - ots   => "ODF Spreadsheet Template",
     *  - docx  => "Office Open XML Text",
     *  - doc   => "Microsoft Word 97-2003",
     *  - dot   => "Microsoft Word 97-2003 Template",
     *  - odt   => "ODF Textdocument",
     *  - ott   => "ODF Textdocument Template",
     *  - pptx  => "Office Open XML Presentation",
     *  - ppt   => "Microsoft Powerpoint 97-2003",
     *  - potm  => "Microsoft Powerpoint 97-2003 Template",
     *  - odp   => "ODF Presentation",
     *  - otp   => "ODF Presentation Template"
     *
     * @apiParam (GET Parameter) {string} name The name of the new document
     * @apiParam (GET Parameter) {string} [collection] Parent collection id (If none  given, the document will be placed under root)
     * @apiParam (GET Parameter) {string} type Office document file type
     * @apiParam (GET Parameter) {string[]} attributes Node attributes
     *
     * @apiExample (cURL) example:
     * curl -XPUT "https://SERVER/api/v1/app/office/document?type=xlsx"
     *
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 201 Created
     * {
     *      "status": 201,
     *      "data": "544627ed3c58891f058b4611"
     * }
     *
     * @param string $collection
     */
    public function put(string $name, string $type, ?string $collection = null, array $attributes = [], int $conflict = 0): Response
    {
        $parent = $this->fs->getNode($collection, null, Collection::class, false, true);
        $tpl = new Template($type);

        $stream = $tpl->get();
        $session = $parent->getStorage()->storeTemporaryFile($stream, $this->server->getIdentity());
        $result = $parent->addFile($name, $session, $attributes);
        fclose($stream);

        return (new Response())->setCode(201)->setBody([
            'code' => 201,
            'data' => (string) $result->getId(),
        ]);
    }
}
