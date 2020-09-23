<?php
/**
 * Created by PhpStorm.
 * User: xuce
 * Date: 2019-09-16
 * Time: 17:28
 */

namespace Cwk;


class Core
{
    public static $logFile = '';
    public static $count = 1;
    protected static $version = '1.0.0';
    protected static $_workerIdMap = [];
    protected static $_pidFile = '';
    protected static $_pid = null;
    protected static $_startTime = null;
    protected static $_daemon = false;
    protected static $_test = null;
    protected static $_protocol = '';
    protected static $_host = '0.0.0.0';
    protected static $_port = '2345';
    protected static $_listenSocket = '';
    protected static $_listenAddr = '';

    public function __construct()
    {
        $this->parseSocketAddress();
    }

    public static function run()
    {
        static::checkSapiType();
        static::init();
        static::checkExtension();
        static::initSignal();
        static::execCommand();
    }

    public static function init()
    {
        //pid文件
        if ( empty(static::$_pidFile) ) {
            static::$_pidFile = __DIR__ . '/../cwk.pid';
        }
        //日志文件
        if ( empty(static::$logFile)) {
            static::$logFile = __DIR__ . '/../cwk.log';
        }
    }

    public static function initSignal()
    {
        pcntl_signal(SIGINT, array(new Core(),'signalHandle'));
    }

    public static function checkExtension()
    {
        if( !extension_loaded('pcntl') ) {
            exit('请安装pcntl扩展'.PHP_EOL);
        }
        if( !extension_loaded('sockets') ){
            exit('请安装sockets扩展'.PHP_EOL);
        }
        if( !extension_loaded('event') ){
            exit('请安装event扩展'.PHP_EOL);
        }
    }

    public static function checkSapiType()
    {
        if(\php_sapi_name() != 'cli') {
            exit('only run in command line mode');
        }
    }

    public static function execCommand()
    {
        global $argv;
        $allowCommand = ['start','restart','reload','stop','status'];
        $command = isset($argv[1]) ? $argv[1]: '';
        if(!in_array($command,$allowCommand)) {
            exit('allow command list {start|restart|reload|stop|status}'.PHP_EOL);
        }
        try {
            switch ($argv[1]) {
                case 'start' :
                    $command2 = isset($argv[2]) ? $argv[2] : '';
                    if ($command2 == '-d') static::$_daemon = true;
                    static::start();
                    break;
                case 'restart':
                    break;
                case 'reload':
                    break;
                case 'stop':
                    static::stop();
                    break;
                case 'status':
                    break;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public static function start()
    {
        /**
         * 如果是守护进程方式
         */
        if (static::$_daemon) {
            self::daemonize();
        }
        static::initSignal();
        static::createListenSocket();
        cli_set_process_title('cwk master process');
        file_put_contents(static::$_pidFile,posix_getpid());
        static::$_pid = posix_getpid();
        while(count(static::$_workerIdMap) < static::$count) {
            static::forkOneWorker();
        }

        static::$_startTime = date('Y-m-d H:i:s');

        self::printStr();

        while (true) {
            pcntl_signal_dispatch();
            foreach (static::$_workerIdMap as $workerId => $status) {
                if (pcntl_waitpid($workerId, $status, WNOHANG ) > 0) {
                    unset(static::$_workerIdMap[$workerId]);
                }
            }

            if (count(static::$_workerIdMap) == 0) {
                break;
            }
            sleep(1);
        }
        echo "Stopped Success\n";
        exit;
    }

    public static function stop()
    {
        posix_kill((int)file_get_contents(static::$_pidFile),SIGINT);
    }

    public static function forkOneWorker()
    {
        $pid = pcntl_fork();
        if ($pid > 0) {
            static::$_workerIdMap[$pid] = 1;
        }

        if ($pid < 0) {
            throw new \Exception('worker process fork fail');
        }

        if ($pid == 0) {
            cli_set_process_title('cwk worker process');
            $workerId = posix_getpid();
            $socket = socket_accept(self::$_listenSocket);
            $rewData = socket_recv($socket, $content, 20, MSG_WAITALL);
            echo $content;
            socket_close($socket);
//            while (true) {
//                sleep(1);
//                //echo 'worker process '.$workerId.' is running..'.PHP_EOL;
//            }
            exit;
        }

    }

    public function signalHandle($signal)
    {
        switch ($signal) {
            case SIGINT :
                echo "Stopping...\n";
                if (posix_getpid() == static::$_pid) {
                    foreach (static::$_workerIdMap as $workerId => $status) {
                        posix_kill($workerId,SIGTERM);
                    }
                } else {
                    posix_kill(posix_getpid(),SIGTERM);
                }
                break;
            case SIGTERM:
            default:
        }
    }

    //打印到屏幕
    public static function printStr()
    {
        $display_str = '';
        $display_str .= "-------------------<white> cwk </white>-------------------" . PHP_EOL;
        $display_str .= '开始时间:' . static::$_startTime . PHP_EOL;
        $display_str .= "CWK version:<purple>" . static::$version . "</purple>" . PHP_EOL;
        $display_str .= "PHP version:<purple>" . PHP_VERSION . "</purple>" . PHP_EOL;
        $display_str .= "当前子进程数: <red>" . count(static::$_workerIdMap) . "个，PID:(" . implode(',', array_keys(static::$_workerIdMap)) . ")</red>" . PHP_EOL;
        $display_str .= "当前主进程PID: <red>" . posix_getpid() . "</red>" . PHP_EOL;
        $display_str .= "-------------------<green> By:XuCe </green>---------------" . PHP_EOL;
        if (!static::$_daemon) {
            $display_str .= "<yellow>Press Ctrl+C to quit.</yellow>" . PHP_EOL;
        }
        $display_str = self::replaceStr($display_str);
        echo $display_str;

    }

    //文字替换
    public static function replaceStr($str)
    {
        $line = "\033[1A\n\033[K";
        $white = "\033[47;30m";
        $green = "\033[32;40m";
        $yellow = "\033[33;40m";
        $red = "\033[31;40m";
        $purple = "\033[35;40m";
        $end = "\033[0m";
        $str = str_replace(array('<n>', '<white>', '<green>', '<yellow>', '<red>', '<purple>'), array($line, $white, $green, $yellow, $red, $purple), $str);
        $str = str_replace(array('</n>', '</white>', '</green>', '</yellow>', '</red>', '</purple>'), $end, $str);
        return $str;
    }

    public static function daemonize()
    {
        //设置umask掩码，umask与chmod权限相关
        \umask(0);

        $pid = pcntl_fork();

        if ($pid < 0) {
            throw new Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }

        //生成新的会话id
        if( -1 === \posix_setsid() ){
            throw new Exception('setsid fail');
        }

        // 避免SVR4某些情况下情况下进程会再次获得控制终端
        // Fork again avoid SVR4 system regain the control of terminal.
        $pid = pcntl_fork();
        if( $pid  < 0 ){
            throw new Exception('fork fail');
        } else if( $pid > 0 ) {
            // 主进程退出
            exit(0);
        }
    }

    protected function parseSocketAddress()
    {
        if (!self::$_listenAddr) {
            return;
        }

        list(self::$_protocol, self::$_host, self::$_port) = explode(":", self::$_listenAddr);
        self::$_host = str_replace("/", "", self::$_host);
    }

    /*
     * @desc : 创建监听socket
     */
    private static function createListenSocket(){
        $listenSocket = socket_create( AF_INET, SOCK_STREAM, SOL_TCP );
        // 要在bind之前执行，这句是为了解决刚停服后，再次启动时候出现 address already in use 的异常
        socket_set_option( $listenSocket, SOL_SOCKET, SO_REUSEADDR, 1 );
        socket_bind( $listenSocket, self::$_host, self::$_port );
        socket_listen( $listenSocket );
        //socket_set_nonblock( $listenSocket );
        self::$_listenSocket = $listenSocket;
    }
}