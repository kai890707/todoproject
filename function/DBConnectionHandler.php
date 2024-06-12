<?php 
class DBConnectionHandler
{
    private static $serverName = 'localhost';

    private static $userName = 'root';

    private static $password = '';

    private static $db = 'todolist';  // 資料庫名稱

    private static $connection = null;

    public static function setConnection()
    {
        $connectionStr = sprintf(
          "mysql:host=%s;dbname=%s",
          static::$serverName,
          static::$db
        );

        try {
            $options = [
              PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
              PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];
            static::$connection = new PDO($connectionStr , static::$userName, static::$password, $options);
            // set the PDO error mode to exception
            static::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          } catch(PDOException $e) {
            throw $e;
          }
    }

    public static function getConnection()
    {
      if (is_null(static::$connection)) {
          static::setConnection();
      }
      return static::$connection;
    }
}

?>