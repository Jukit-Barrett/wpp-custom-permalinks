<?php

namespace Mrzkit\SimpleDatabase\Connectors;

interface ConnectorInterface
{
    /**
     * @desc 建立数据库连接
     * @param  array  $config
     * @return \PDO
     */
    public function connect(array $config);
}
