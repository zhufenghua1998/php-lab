<?php


/**
 * Class DB 数据库封装类
 * @author zfh
 */
class DB
{

    /**
     * 私有化构造方法
     */
    private function __construct()
    {
    }

    /**
     * 私有克隆方法
     */
    private function __clone()
    {
    }

    // PDO唯一实例
    private $pdo;

    // DB唯一实例
    private static $db;

    // 执行过的所有sql（执行后的sql集合记录分析）
    private static $sqlList;

    //当前操作sql
    private static $sql;

    //临时缓存sql（一些操作由多个sql组合而成，此时tempSql保留原始sql)
    private static $tempSql;

    //当前操作sql表
    private static $sqlTables;

    //当前操作sql参数
    private static $args;

    //连接配置
    private $dbConfig = array(
        'db' => 'mysql',
        'host' => '5252.online',
        'port' => 3306,
        'dbname' => "bishe_tiku",
        'user' => "bishe_tiku",
        'pwd' => "XxtE6Z47AX3C2WPx",
        'charset' => "utf8"
    );

    /**
     * 获取单例对象实例 （获取实例方法一）
     */
    public static function getInstance()
    {
        if (self::$db) {
            return self::$db;
        } else {
            $db = new DB();
            self::$db = $db;
            return $db;
        }
    }

    /**
     * 设置表名，并返回实例化对象 （获取实例方法二）
     * @param string|array $table
     * @param string $sql
     * @param string|array $args
     * @return DB
     */
    public static function table($table,$sql="",$args=null){
        //实例化对象
        $db = DB::getInstance();
        //记录sql
        $db::$sql = $sql;
        //fixSql
        return $db->fixSqlAll($args,$table);
    }


    /**
     * 设置一个sql，并返回实例化对象（获取实例方法三）
     * @param $sql
     * @param array $args
     * @param null $table
     * @return DB
     */
    public static function sql($sql,$args=null,$table=null){

        return self::table($table,$sql,$args);
    }

    /**
     * 模拟生成sql，返回一个sql
     * @param string $sql
     * @param array $args
     * @param null $tables
     * @return string
     */
    public static function mockSql(string $sql,$args=null,$tables=null){

        return DB::sql($sql,$args,$tables)->getSql();
    }



    /** 获取连接对象
     * @return PDO|void
     */
    public function connect()
    {
        if ($this->pdo) {
            return $this->pdo;
        } else {
            $db = $this->dbConfig['db'];
            $host = $this->dbConfig['host'];
            $port = $this->dbConfig['port'];
            $dbname = $this->dbConfig['dbname'];
            $user = $this->dbConfig['user'];
            $pwd = $this->dbConfig['pwd'];
            $charset = $this->dbConfig['charset'];

            $dsn = "$db:host=$host;port=$port;dbname=$dbname;charset=$charset;";
            if (!class_exists("PDO")) {
                die("<p style='color: red'>错误：PDO扩展未开启</p>");
            } else {
                $this->pdo = new PDO($dsn, $user, $pwd, array(
                    PDO::MYSQL_ATTR_FOUND_ROWS => true,  // 获取匹配的数据条数，而不是影响条数
                    PDO::ATTR_PERSISTENT => true,         // 保持长连接
                    PDO::ATTR_EMULATE_PREPARES => false,  // 禁止模拟预处理
                    PDO::ATTR_STRINGIFY_FETCHES => false   // 不要自动转String
                ));
                return $this->pdo;
            }
        }
    }


    /**
     * 获取临时sql（仅通过sql()方法生成）
     */
    public function getSql()
    {
        return self::$sql;
    }

    /**
     * 设置sql
     * @param $sql
     * @param string|array $args
     * @param string|array $tables
     * @return DB
     */
    public function setSql($sql,$args=null,$tables=null)
    {
        return self::sql($sql,$args,$tables);
    }

    /**
     * 设置table（另一种设置sql的方法）
     * @param $table
     * @param string $sql
     * @param null $args
     * @return mixed
     */
    public function setTable($table,$sql="",$args=null){
        return self::sql($sql,$args,$table);
    }


    /**
     * 一键填充到sql
     * @param null $args
     * @param null $table
     * @return DB
     */
    private function fixSqlAll($args=null,$table=null){
        //记录table
        $table = is_string($table) ? array($table) : $table;
        $table = $table ?: array();
        self::$sqlTables = $table;
        $this->fixSqlTable();

        //处理args
        $args = is_string($args) ? array($args) : $args;
        $args = $args ?: array();
        self::$args = $args;
        $this->fixSqlArgs();

        //返回当前对象
        return $this;
    }


    /**
     * 把表名填充到sql语句中
     * @return DB
     */
    private function fixSqlTable(){
        $sql = self::$sql;
        $tables = self::$sqlTables;
        //如果没有表名，直接返回sql
        if(empty($tables)){
            return $this;
        }
        $sql = str_replace(";", "", $sql);
        $sql .= " ";
        //判断表名是 索引 还是 关联 数组
        $args_value = array_values($tables);
        $arg_is_key = array_diff_key($tables, $args_value);
        //表名是关联数组
        if($arg_is_key){
            foreach ($tables as $k => $v) {
                $sql = str_replace("@$k", $v, $sql);
            }
        }
        //索引数组
        else{
            //提取表名（通常为 @key ，也就是 @ 开头， 空格结尾，则使用 \w 单词字符开头，\s 空格结尾）
            preg_match_all('/@\w+\s/', $sql, $matches);
            $keys = $matches[0];
            //遍历赋值
            for ($i = 0; $i < count($keys); $i++) {
                $key = str_replace(" ", '', $keys[$i]);
                $sql = str_replace("$key", $tables[$i], $sql);
            }
        }
        self::$sql = substr($sql, 0, -1);
        return $this;
    }

    /**
     * 把参数填充到sql中
     * @return $this
     */
    private function fixSqlArgs(){
        $sql = self::$sql;
        $args = self::$args;
        //如果没有参数，直接返回
        if(empty($args)){
            return $this;
        }
        //参数处理（有参数，拼接为正确的sql）
        $sql = str_replace(";", "", $sql);
        //判断是关联数组 OR 索引数组
        $args_value = array_values($args);
        $arg_is_key = array_diff_key($args, $args_value);
        //判断sql是?还是:key
        $sql_is_key = strpos($sql, "?") === false;
        //如果是 ？ ,则遍历替换即可
        if (!$sql_is_key) {
            foreach ($args as $k => $v) {
                if (is_string($v)) $v = "'$v'";
                $sql = preg_replace('/\?/', $v, $sql, 1);
            }
        } //如果是 :key ，且 args 是关联数组，则遍历替换
        elseif ($arg_is_key) {
            foreach ($args as $k => $v) {
                if (is_string($v)) $v = "'$v'";
                $sql = str_replace(":$k", $v, $sql);
            }
        } //如果是 :key ，且 args 是索引数组
        else {
            //提取键名（通常为 :key ，也就是 : 开头， 空格结尾，则使用 \w 单词字符开头，\s 空格结尾）
            $sql .= " ";
            preg_match_all('/:\w+\s/', $sql, $matches);
            $keys = $matches[0];
            //遍历赋值
            for ($i = 0; $i < count($keys); $i++) {
                $key = str_replace(" ", '', $keys[$i]);
                if (is_string($args[$i])) $args[$i] = "'$args[$i]'";
                $sql = str_replace("$key", $args[$i], $sql);
            }
            $sql = substr($sql, 0, -1);
        }
        //记录sql
        self::$sql = $sql;
        //返回当前对象
        return $this;
    }


    /**
     * 获取一个值（单行单列）
     * @return mixed
     */
    public function selectOne()
    {
        return $this->open(array("type"=>"one"));
    }

    /**
     * 查询多行单列、或单行多列，封装为一个数组
     * @return mixed
     */
    public function selectArr()
    {
        return $this->open(array("type"=>"arr"));
    }

    /**
     * 查询多条数据，并且封装为二维数组
     * @return mixed
     */
    public function selectArrList()
    {
        return $this->open(array("type"=>"arrList"));
    }

    /** 计算sql总数
     * @return mixed
     */
    public function count()
    {
        //缓存sql
        self::$tempSql = self::$sql;
        //拼接统计sql
        self::$sql = "select count(*) total from ( " . self::$sql . " ) tmp_count";
        //执行sql
        $res = $this->open(array("type"=>"count"));
        //还原sql，并返回数据
        self::$sql = self::$tempSql;
        return $res;
    }


    /**
     * 自动分页查询，封装为纯数组格式
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function selectPage(int $page, int $pageSize)
    {
        // 封装并返回结果
        $data = array();
        $data['pageInfo'] = self::selectPageInfo($page, $pageSize);
        $data['list'] = self::selectPageList($page, $pageSize);
        return $data;
    }

    /**
     * 获取分页数据统一接口
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function selectPageList(int $page, int $pageSize)
    {
        $db = $this->dbConfig['db'];
        $db = strtolower($db);  // 规范化命名，转小写
        $method = "selectPageList_" . $db;
        if (!method_exists($this, $method)) {
            die("DB类方法：" . $method . " 不存在");
        } else {
            // 灵活扩展，使用动态反射，获取 getPageList_数据库名() 方法返回的数据
            return $this->$method($page, $pageSize);
        }
    }

    /** mysql 分页数据获取
     * @param int $page
     * @param int $pageSize
     * @return mixed
     */
    private function selectPageList_mysql(int $page, int $pageSize)
    {
        //缓存原始sql
        self::$tempSql = self::$sql;
        // 查询数据
        $r_start = ($page - 1) * $pageSize;
        self::$sql = self::$sql . " limit $r_start,$pageSize";
        //取得数据
        $res = $this->selectArrList();
        //恢复sql并返回数据
        self::$sql = self::$tempSql;
        return $res;
    }

    /**
     * 获取分页提示
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function selectPageInfo(int $page, int $pageSize)
    {
        // 取得总数
        $total = $this->count();
        // 封装info
        $pageInfo = array();
        $pageInfo['page'] = $page;      // 当前页数
        $pageInfo['pageSize'] = $pageSize;  // 页面大小
        $pageInfo['totalCount'] = $total;  // 总条数
        $pageInfo['totalPage'] = ceil($total / $pageSize);  // 总页数
        return $pageInfo;
    }


    /**
     * 新增、更新、删除语句（从语义上，专指更新）
     * @return mixed
     */
    public function update()
    {
        return $this->open(array("type"=>"update"));
    }

    /**
     * 新增、更新、删除语句（从语义上，专指新增）
     * @return array|int|mixed|string
     */
    public function insert()
    {
        return $this->update();
    }

    /**
     * 新增、更新、删除语句（从语义上，专指删除）
     */
    public function delete(){
        return $this->update();
    }


    /**
     * 获取数据库版本
     */
    public function dbVersion(){
        return $this->connect()->getAttribute(PDO::ATTR_SERVER_VERSION);
    }


    /**
     * 获取最后一个插入的自增主键
     * @return int|string
     */
    public function lastId()
    {
        return $this->open(array("type"=>"lastId"));
    }

    /**
     * 开启事务
     */
    public static function begin()
    {
        $db = DB::getInstance()->connect();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
        $db->beginTransaction();
    }

    /**
     * 当前是否开启事务
     */
    public static function isBegin()
    {
        return !DB::getInstance()->connect()->getAttribute(PDO::ATTR_AUTOCOMMIT);  // 事务的自动提交{1.是，0.否}，默认为1也就是每条sql都自动提交
    }


    /**
     * 提交事务
     */
    public static function commit()
    {
        $DB = DB::getInstance()->connect();
        $DB->commit();
        $DB->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
    }

    /**
     * 事务回滚
     */
    public static function rollBack()
    {
        $DB = DB::getInstance()->connect();
        $DB->rollBack();
        $DB->setAttribute(PDO::ATTR_AUTOCOMMIT, true);
    }

    /**
     * 打开连接
     * @param array $param
     * @return array|int|mixed|string
     */
    private function open($param=array()){
        $type = $param['type'];
        //取出sql
        $sql = self::$sql;
        //获取连接sql对象，并进行预编译（执行开始时间）
        $st = microtime(true)*1000;
        $stmt = $this->connect()->prepare($sql);
        //执行预编译
        $stmt->execute();
        $code=$stmt->errorCode();
        if($code=="00000"){
            //执行正常，记录sql执行时间
            $exeTime = microtime(true)*1000-$st;
            self::$sqlList[] = array(
                'sql'=>$sql,
                'exeTime'=>$exeTime,
            );
            /*   根据不同类型执行并获取不同的结果   */
            //返回二维数组
            if($type=="arrList"){
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            //返回一维数组
            elseif($type=="arr"){
                // 先假设为多行多列
                $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $res2 = array();
                if (count($res) > 1) {
                    foreach ($res as $k => $v) { // 多行单列，取第一列
                        $res2[] = current($v);
                    }
                } else {
                    if (empty($res)) {  // 空数组，直接返回
                        return $res;
                    } else {
                        $res2 = $res[0]; // 单行多列，取第一行
                    }
                }
                return $res2;
            }
            elseif($type=="one"){
                $res = $stmt->fetch(PDO::FETCH_NUM);
                if (empty($res)) {
                    return $res;   // 空数组，直接返回
                } else {
                    return $res[0];
                }
            }
            elseif($type=="count"){
                // 不要使用 rowCount ，其浪费内存且性能低，此外已经设定改为found_rows，所以rowCount可能在select得不到正确数据
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int)$res['total'];
            }
            elseif($type=="update"){
                return $stmt->rowCount();
            }
            elseif($type=="lastId"){
                if ($stmt->rowCount()) {
                    return $this->connect()->lastInsertId();
                } else {
                    return 0;
                }
            }
        }else{
            //执行失败，记录错误sql
            var_dump($sql);
            var_dump($stmt->errorInfo());
        }
    }

}



//返回原生sql五种方式
//$res = DB::getInstance()->setSql("select * from @user where utype=:utype and uname=:uname" , [1, '张伟'],'user')->getSql(); // 实例生成
//$res = DB::sql("select * from @user where utype=:utype and uname=:uname" , [1, '张伟'],'user')->getSql(); //静态生成
//$res = DB::mockSql("select * from @user where utype=:utype and uname=:uname" , [1, '张伟'],'user');  // 只返回sql
//$res = DB::table('user',"select * from @user where utype=:utype and uname=:uname",[1, '张伟'])->getSql(); //习惯先写表名
//$res = DB::getInstance()->setTable('user',"select * from @user where utype=:utype and uname=:uname",[1, '张伟'])->getSql(); //实例生成，另一种写法
//echo($res);


//几种常见的查询
//$res = DB::getInstance()->setSql("select * from user")->selectOne();
//$res = DB::getInstance()->setSql("select * from user")->selectArr();
//$res = DB::getInstance()->setSql("select * from user")->selectArrList();
//$res = DB::getInstance()->setSql("select * from user")->selectPage(2,3);
//$res = DB::getInstance()->setSql("select * from user")->selectPageList(1,3);
//$res = DB::getInstance()->setSql("select * from user")->selectPageInfo(1,3);
//$res = DB::getInstance()->setSql("select * from user")->count();
//var_dump($res);

//增加、更新和删除
//$res = DB::getInstance()->setSql("update user set upwd=1 where uno=3")->update();
//$res = DB::getInstance()->setSql("insert into user(uname,upwd) values('test','123456')")->lastId();
//$res = DB::getInstance()->setSql("select * from user")->insert();
//$res = DB::getInstance()->setSql("select * from user")->delete();
//var_dump($res);

//事务管理
//DB::begin();  //开启事务
//var_dump(DB::isBegin()); ;  //当前是否开启事务
//DB::commit();  //提交事务
//DB::rollback();  //事务回滚

//表相关
//$res = DB::table()->exist();//表是否存在
//$res = DB::table()->drop();//删除表
//$res = DB::table()->tableSize();//表大小
//$res = DB::table()->selectAll();//查询表所有数据
//$res = DB::table()->selectById();//根据ID查询一条数据
//$res = DB::table()->deleteById();//根据ID删除一条数据
//$res = DB::table()->showCreate();//查询表的结构
//$res = DB::table()->showColumns();//查询表的字段

//数据库相关
//$res = DB::database()->dbVersion();//获取数据库版本
//$res = DB::database()->dbSize();//获取数据库大小
//$res = DB::database()->showTables();//获取数据库的所有表




