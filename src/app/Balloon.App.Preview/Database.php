<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Preview;

use \Balloon\Database\AbstractDatabase;
use \Balloon\App\Preview\Database\Delta\PreviewIntoApp;

class Database extends AbstractDatabase
{
    /**
     * Initialize database
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->db->selectCollection('thumbnail.files')->createIndex(['md5' => 1], ['unique' => true]);
        return true;
    }


    /**
     * Get deltas
     *
     * @return array
     */
    public function getDeltas(): array
    {
        return [
            PreviewIntoApp::class
        ];
    }
}
