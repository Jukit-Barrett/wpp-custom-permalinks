<?php
/**
 * Plugin Name: WPP Custom Permalinks
 * Plugin URI: https://www.tranhom.com/
 * Description: Set custom permalinks on a per-post basis.
 * Version: 1.0.0
 * Requires at least: 2.6
 * Requires PHP: 5.4
 * Author: Kitgor
 * Author URI: https://www.tranhom.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Text Domain: wpp-custom-permalinks
 * Domain Path: /languages/
 *
 * @package CustomPermalinks
 */

/**
 *  Custom Permalinks - Update Permalinks of Post/Pages and Categories
 *  Copyright 2008-2021 Sami Ahmed Siddiqui <sami.siddiqui@yasglobal.com>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.

 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.

 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use Mrzkit\SimpleDatabase\Capsule\DatabaseConfigContract;
use Mrzkit\SimpleDatabase\Capsule\Manager as DB;

if ( ! defined('ABSPATH' ) ) {
    exit;
}

require __DIR__ . '/vendor/autoload.php';

class DbConfig implements DatabaseConfigContract {
    /**
     * @desc 数据库连接配置
     * @return array
     */
    public function getConnectionConfig()
    {
        $config = [
            'default'     => 'ali-rds',
            'connections' => [
                'ali-rds'     => [
                    'name'      => 'ali-rds',
                    'read'      => [
                        'host' => [
                            'mysql.kitgor.com',
                            'mysql.kitgor.com',
                        ],
                    ],
                    // 读写
                    'write'     => [
                        'host' => [
                            'mysql.kitgor.com',
                        ],
                    ],
                    'driver'    => 'mysql',
                    'port'      => 3306,
                    'database'  => 'wordpress_test',
                    'username'  => 'root',
                    'password'  => '123456',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => 'th_',
                ],
                'tencent-rds' => [
                    'name'      => 'tencent-rds',
                    'read'      => [
                        'host' => [
                            'mysql.kitgor.com',
                            'mysql.kitgor.com',
                        ],
                    ],
                    // 读写
                    'write'     => [
                        'host' => [
                            'mysql.kitgor.com',
                        ],
                    ],
                    'driver'    => 'mysql',
                    'port'      => 3306,
                    'database'  => 'wordpress_test',
                    'username'  => 'root',
                    'password'  => '123456',
                    'charset'   => 'utf8',
                    'collation' => 'utf8_unicode_ci',
                    'prefix'    => 'th_',
                ],
            ],
        ];

        return $config;
    }
}

DB::resolverDatabaseConfig(DbConfig::class);

new \Mrzkit\WppCustomPermalinks\CustomPermalinks(__FILE__);
