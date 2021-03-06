<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview\Constructor;

use Balloon\App\Preview\Api\v1;
use Balloon\App\Preview\Api\v2;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http
{
    /**
     * Constructor.
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v1/file/preview(/|\z)', v1\Preview::class))
            ->prependRoute(new Route('/api/v1/file/{id:#([0-9a-z]{24})#}/preview(/|\z)', v1\Preview::class))
            ->prependRoute(new Route('/api/v2/files/preview(/|\z)', v2\Preview::class))
            ->prependRoute(new Route('/api/v2/files/{id:#([0-9a-z]{24})#}/preview(/|\z)', v2\Preview::class));
    }
}
