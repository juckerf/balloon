<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Api\v1;

use \Balloon\Exception;
use \Balloon\Api\Controller;
use \Balloon\Helper;
use \Micro\Http\Response;

class Api extends Controller
{
    /**
     * @api {get} / Server & API Status
     * @apiVersion 1.0.0
     * @apiName get
     * @apiGroup Api
     * @apiPermission none
     * @apiDescription Get server time and api status/version
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1?pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object} data API/Server information
     * @apiSuccess {string} data.name balloon identifier
     * @apiSuccess {number} data.api_version API Version
     * @apiSuccessExample {json} Success-Response:
     * HTTP/1.1 200 OK
     * {
     *     "status": 200,
     *     "data": {
     *         "name": "balloon",
     *         "api_version": 1
     *     }
     * }
     *
     * @return Response
     */
    public function get(): Response
    {
        $data = [
            'name' => 'balloon',
            'api_version' => 1,
        ];

        return (new Response())->setCode(200)->setBody($data);
    }

    /**
     * @api {get} /reference API Help Reference
     * @apiVersion 1.0.0
     * @apiName getReference
     * @apiGroup Api
     * @apiPermission none
     * @apiDescription API realtime reference (Automatically search all possible API methods)
     *
     * @apiExample Example usage:
     * curl -XGET "https://SERVER/api/v1/reference?pretty"
     *
     * @apiSuccess {number} status Status Code
     * @apiSuccess {object} data API Reference
     *
     * @return Response
     */
    public function getReference(): Response
    {
        $api = [];
        $controllers = ['Api', 'User', 'Node', 'File', 'Collection', 'Admin\\User'];
        $prefix = ['GET', 'POST', 'DELETE', 'PUT', 'HEAD'];

        foreach ($controllers as $controller) {
            $ref = new \ReflectionClass('Balloon\\Api\\v1\\'.$controller);
            $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $name  = Helper::camelCase2Dashes($method->name);
                $parts = explode('-', $name);
                $verb = strtoupper($parts[0]);
                $func = substr($name, strlen($verb) + 1);
                $url = '/rest/'.strtolower($controller).'/'.$func;
                $doc = $this->parsePhpDoc($method->getDocComment());

                if (!in_array($verb, $prefix, true)) {
                    continue;
                }

                $api[$controller][$name] = [
                    'url' => substr(str_replace('\\', '/', $url), 5),
                    'method' => $verb,
                    'return' => strtoupper($doc['return']),
                ];

                // add api parameters
                $params = $this->parseApiParams($doc);
                if (!empty($params)) {
                    $api[$controller][$name]['params'] = $params;
                }

                // add api aliases
                if (array_key_exists('apiAlias', $doc)) {
                    // ensure aliases is array
                    $aliases = $doc['apiAlias'];
                    if (!is_array($aliases)) {
                        $aliases = [$aliases];
                    }
                    $api[$controller][$name]['aliases'] = $aliases;
                }
            }
        }

        return (new Response())->setCode(200)->setBody($api);
    }

    /**
     * Parse apiParam comments from phpDoc
     *
     * @param  array $phpDoc
     * @return array
     */
    protected function parseApiParams(array $phpDoc): array
    {
        $params = [];
        if (array_key_exists('apiParam', $phpDoc)) {
            // ensure apiParams is array
            $apiParams = $phpDoc['apiParam'];
            if (!is_array($apiParams)) {
                $apiParams = [$apiParams];
            }

            // parse each apiParam
            foreach ($apiParams as $apiParam) {
                // split param string by regex (intentionally have bracket in catch group 3 to decide if parameter is optional)
                preg_match('/\(([A-Z]+) Parameter\) \{([a-zA-Z_]+(?:\[\])?)\} (\[?[a-zA-Z_\.]+)(?:=(.*))?\]? (.*)/s', $apiParam, $matches);

                // check if param is optional and "clean up" parameter name
                $optional = substr($matches[3], 0, 1) === '[';
                $name = $optional ? substr($matches[3], 1) : $matches[3];

                $param = [
                    'type' => $matches[1],
                    'datatype' => $matches[2],
                    'name' => $name,
                    'optional' => $optional
                ];

                // add default value only if set
                if (!empty($match[4])) {
                    $param['default'] = $match[4];
                }

                $params[] = $param;
            }
        }
        return $params;
    }

    /**
     * Parse php doc.
     *
     * @param string $data
     *
     * @return array
     */
    protected function parsePhpDoc($data)
    {
        $data = trim(preg_replace('/\r?\n *\* *(\/$)?|\/\*\*/', ' ', $data));
        preg_match_all('/@([a-zA-Z]+)\s+(.*?)\s*(?=$|@[a-zA-Z]+\s)/s', $data, $matches);
        $info = Helper::array_combine_recursive($matches[1], $matches[2]);
        if (isset($info['return'])) {
            $info['return'] = $info['return'];
        } else {
            $info['return'] = 'void';
        }

        return $info;
    }
}