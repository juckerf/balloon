<?php
/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\ClamAv;

use \Balloon\User;
use \Balloon\Filesystem;
use \Balloon\Http\Router\Route;
use \Balloon\App\AbstractApp;

class Init extends AbstractApp
{
    const FILE_INFECTED = 0;
    const FILE_OK = 1;

    /**
    * Socket
    *
    * @var string
    */
    protected $socket = 'unix:///var/run/clamav/clamd.ctl';

    /**
    * Maximum Stream Size
    *
    * @var int
    */
    protected $maxStreamSize = 26214400;

    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->pluginmgr->registerPlugin('\Balloon\App\ClamAv\Plugin', null);
        return true;
    }

    /**
     * Set options
     *
     * @var Iterable $config
     */
    public function setOptions(?Iterable $config=null): AppInterface
    {
        if($config === null) {
            return $this;
        }

        foreach($config as $option => $value) {
            switch($option) {
                case 'socket':
                    $this->socket = (string)$value;
                    break;
                case 'maxStreamSize':
                    $this->maxStreamSize = (int)$value;
                    break;
            }
        }

        return $this;
    }

    /**
     * Scan file
     *
     * @param  File $file
     * @return int
     */
    public function scan(File $file): int
    {
        if ($file->getSize() > $this->maxStreamSize) {
            throw new Exception('file size of ' . $file->getSize() . ' exceeds stream size (' . $this->maxStreamSize . ')');
        }
        // Create a new socket instance
        // TODO: read socket from config
        $socket = (new \Socket\Raw\Factory())->createClient($this->socket);

        // Create a new instance of the Client
        $quahog = new \Xenolope\Quahog\Client($socket);

        // Scan file
        $result = $quahog->scanResourceStream($file->get());

        $this->logger->debug('scan result for file ' . $file->getId() . ': ' . $result, [
            'category' => get_class($this)
        ]);

        $result = explode(' ', $result);

        if (end($result) === 'OK') {
            return self::FILE_OK;
        }
        if (end($result) === 'FOUND') {
            return self::FILE_INFECTED;
        }

        throw new Exception('scan of file ' . $file->getId() . ' failed');
    }
}