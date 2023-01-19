<?php

namespace Mrzkit\SimpleDatabaseTest\Unit;

use Mrzkit\SimpleDatabase\Capsule\Manager as DB;
use Mrzkit\SimpleDatabaseTest\Config\DatabaseConfig;

trait ConnectTrait
{
    protected static $_db;

    /**
     * @desc 获取连接
     * @return \Mrzkit\SimpleDatabase\Connection
     */
    public static function db()
    {
        if (is_null(self::$_db)) {
            self::$_db = DB::resolverDatabaseConfig(DatabaseConfig::class)::connection('ali-rds');
        }

        return self::$_db;
    }

    /**
     * @before
     */
    public function setupSomeFixtures()
    {
        echo "\r\n\r\n";
        // 开启查询日志
        self::db()->enableQueryLog();
    }

    /**
     * @after
     */
    public function tearDownSomeFixtures()
    {
        // 获取查询日志
        $logs = self::db()->getQueryLog();
        echo "\r\n\r\n";
        print_r($logs);
    }

}
