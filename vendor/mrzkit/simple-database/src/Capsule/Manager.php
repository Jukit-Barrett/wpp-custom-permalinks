<?php

namespace Mrzkit\SimpleDatabase\Capsule;

use Closure;
use InvalidArgumentException;
use Mrzkit\SimpleDatabase\Connection;
use Mrzkit\SimpleDatabase\Connectors\ConnectionFactory;
use Mrzkit\SimpleDatabase\DatabaseManager;
use Mrzkit\SimpleDatabase\Query\Builder;
use Mrzkit\SimpleDatabase\Query\Expression;

/**
 * @method static Expression raw($value)
 * @method static array getQueryLog()
 * @method static array prepareBindings(array $bindings)
 * @method static array pretend(Closure $callback)
 * @method static array select(string $query, array $bindings = [], bool $useReadPdo = true)
 * @method static bool insert(string $query, array $bindings = [])
 * @method static bool logging()
 * @method static bool statement(string $query, array $bindings = [])
 * @method static bool unprepared(string $query)
 * @method static int affectingStatement(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static mixed selectOne(string $query, array $bindings = [], bool $useReadPdo = true)
 * @method static int getDefaultConnection()
 * @method static int transactionLevel()
 * @method static mixed transaction(Closure $callback, int $attempts = 1)
 * @method static void afterCommit(Closure $callback)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack(int $toLevel = null)
 * @method static void enableQueryLog()
 * @method static void disableQueryLog()
 * @method static void flushQueryLog()
 *
 * @see DatabaseManager
 * @see Connection
 */
class Manager
{
    /**
     * @var static 当前全局实例
     */
    protected static $instance;

    /**
     * @var DatabaseManager 数据库管理实例
     */
    protected $manager;

    /**
     * @var DatabaseConfigContract
     */
    protected static $databaseConfigContract;

    final protected function __construct()
    {
        if (is_null(static::$databaseConfigContract)) {
            throw new InvalidArgumentException("Must instance of DatabaseConfigContract");
        }

        // 构建数据库连接工厂
        $factory = new ConnectionFactory();
        // 构建数据库管理实例
        $this->manager = new DatabaseManager(static::$databaseConfigContract, $factory);
    }

    protected function __clone()
    {

    }

    /**
     * @desc 解析配置
     * @param $databaseConfigContractClass
     * @return Manager|static
     */
    public static function resolverDatabaseConfig($databaseConfigContractClass)
    {
        if (is_string($databaseConfigContractClass) && class_exists($databaseConfigContractClass)) {
            $databaseConfigInstance = new $databaseConfigContractClass();
        } else {
            $databaseConfigInstance = $databaseConfigContractClass;
        }

        if ($databaseConfigInstance instanceof DatabaseConfigContract) {
            static::$databaseConfigContract = $databaseConfigInstance;
        } else {
            throw new InvalidArgumentException("Must instance of DatabaseConfigContract");
        }

        return static::getInstance();
    }

    /**
     * @desc 获取单例
     * @return static
     */
    protected static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @desc 从全局管理器获取连接实例
     * @param string|null $connection
     * @return Connection
     */
    public static function connection($connection = null)
    {
        return static::getInstance()->getConnection($connection);
    }

    /**
     * @desc 获取一个查询构建器实例
     * @param Closure|Builder|string $table
     * @param string|null $as
     * @param string|null $connection
     * @return Builder
     */
    public static function table($table, $as = null, $connection = null)
    {
        return static::getInstance()->connection($connection)->table($table, $as);
    }

    /**
     * @desc 获取已注册的连接实例
     * @param string|null $name
     * @return Connection
     */
    public function getConnection($name = null)
    {
        return $this->manager->connection($name);
    }

    /**
     * @desc 动态地将方法传递给默认连接
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return static::connection()->$method(...$parameters);
    }
}
