<?php
//
// declare(strict_types=1);
//
// /**
//  * balloon
//  *
//  * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
//  * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
//  */
//
// namespace Balloon\Migration\Delta;
//
// use Balloon\Server;
// use MongoDB\Database;
//
// class AddTestUser implements DeltaInterface
// {
//     /**
//      * Database.
//      *
//      * @var Database
//      */
//     protected $db;
//
//     /**
//      * Server.
//      *
//      * @var Server
//      */
//     protected $server;
//
//     /**
//      * Construct.
//      */
//     public function __construct(Database $db, Server $server)
//     {
//         $this->db = $db;
//         $this->server = $server;
//     }
//
//     /**
//      * Initialize database.
//      */
//     public function start(): bool
//     {
//         $this->server->addUser('fabian.jucker', [
//             'password' => 'secret',
//             'mail' => 'root@localhost.local',
//             'admin' => false,
//         ]);
//
//         return true;
//     }
// }
