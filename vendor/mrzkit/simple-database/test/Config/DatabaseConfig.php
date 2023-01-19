<?php

namespace Mrzkit\SimpleDatabaseTest\Config;

use Mrzkit\SimpleDatabase\Capsule\DatabaseConfigContract;

class DatabaseConfig implements DatabaseConfigContract
{
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
                    'database'  => 'blog',
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
                            '172.20.0.4',
                            '172.20.0.4',
                        ],
                    ],
                    // 读写
                    'write'     => [
                        'host' => [
                            '172.20.0.4',
                        ],
                    ],
                    'driver'    => 'mysql',
                    'port'      => 3306,
                    'database'  => 'blog',
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
