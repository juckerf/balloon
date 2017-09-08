<?php
declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPLv3 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Sharelink;

use \Balloon\Exception;
use \Micro\Config;
use \Balloon\App;
use \Balloon\Filesystem;
use \Balloon\Filesystem\Node\Collection;
use \Micro\Http\Response;
use \Micro\Http\Router\Route;
use \Balloon\App\AbstractApp;
use \Balloon\Hook\AbstractHook;
use \Micro\Auth;
use \Micro\Auth\Adapter\None as AuthNone;

class Http extends AbstractApp
{
    /**
     * Init
     *
     * @return bool
     */
    public function init(): bool
    {
        $this->router->appendRoute((new Route('/share', $this, 'start')));

        $this->server->getHook()->injectHook(new class($this->logger) extends AbstractHook {
            public function preAuthentication(Auth $auth): void
            {
                if (preg_match('#^/index.php/share#', $_SERVER["ORIG_SCRIPT_NAME"])) {
                    $auth->injectAdapter('none' ,(new AuthNone($this->logger)) );
                }
            }
        });
 
        return true;
    }



    /**
     * Start
     *
     * @return bool
     */
    public function start(): bool
    {
        if (isset($_GET['t']) && !empty($_GET['t'])) {
            $token    = $_GET['t'];
            if (isset($_GET['download'])) {
                $download = (bool)$_GET['download'];
            } else {
                $download = false;
            }

            try {
                $node = $this->fs->findNodeWithShareToken($token);
                $share = $node->getShareLink();
                
                if (array_key_exists('password', $share)) {
                    $valid = false;
                    if (isset($_POST['password'])) {
                        $valid = hash('sha256', $_POST['password']) === $share['password'];
                    }

                    if ($valid === false) {
                        echo "<form method=\"post\">\n";
                        echo    "Password: <input type=\"password\" name=\"password\"/>\n";
                        echo    "<input type=\"submit\" value=\"Submit\"/>\n";
                        echo "</form>\n";
                        exit();
                    }
                }

                if ($node instanceof Collection) {
                    $mime   = 'application/zip';
                    $stream = $node->getZip();
                    $name   = $node->getName().'.zip';
                } else {
                    $mime   = $node->getMime();
                    $stream = $node->get();
                    $name   = $node->getName();
                }

                if ($download === true || preg_match('#html#', $mime)) {
                    header('Content-Disposition: attachment; filename*=UTF-8\'\'' .rawurlencode($name));
                    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                    header('Content-Type: application/octet-stream');
                    header('Content-Length: '.$node->getSize());
                    header('Content-Transfer-Encoding: binary');
                } else {
                    header('Content-Disposition: inline; filename="'.$name.'"');
                    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                    header('Content-Type: '.$mime);
                }

                if ($stream === null) {
                    exit();
                }

                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }
            } catch (\Exception $e) {
                $this->logger->error("failed load node with access token [$token]", [
                    'category' => get_class($this),
                    'exception' => $e,
                ]);

                (new Response)
                    ->setOutputFormat('text')
                    ->setCode(404)
                    ->setBody('Token is invalid or share link is expired')
                    ->send();
            }
        } else {
            (new Response)
                ->setOutputFormat('text')
                ->setCode(401)
                ->setBody('No token submited')
                ->send();
        }

        return true;
    }
}
