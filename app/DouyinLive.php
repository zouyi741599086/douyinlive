<?php
namespace app;

use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;
use GuzzleHttp\Client as HttpClient;
use app\AcSignature;
use app\JsServer;
use app\ParseMessage;
use app\protobuf\douyin\PushFrame;

class DouyinLive
{
    private $ttwid;
    private $cookie;
    private $room_id;
    private $session;
    private $live_id;
    private $host;
    private $live_url;
    private $user_agent;
    private $headers;
    private $ws_connection;
    private $heartbeat_timer;
    private $reconnect_timer;
    private $is_connected    = false;
    private $room_status     = true;

    public function __construct($live_id = null, $room_id = null, $cookie = null)
    {
        $this->live_id    = $live_id;
        $this->room_id    = $room_id;
        $this->cookie     = $cookie;
        $this->host       = "https://www.douyin.com/";
        $this->live_url   = "https://live.douyin.com/";
        $this->user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0";
        $this->headers    = [
            'User-Agent' => $this->user_agent
        ];

        $this->session = new HttpClient([
            'cookies' => true,
            'verify'  => false,
            'headers' => $this->headers,
            'timeout' => 10
        ]);
    }

    public function start()
    {
        $worker = new Worker();

        $worker->onWorkerStart = function ($worker)
        {
            $this->_connectWebSocket();
        };

        // 运行 Workerman
        Worker::runAll();
        
    }

    public function stop()
    {
        try {
            $this->room_status = false;
            if ($this->heartbeat_timer) {
                Timer::del($this->heartbeat_timer);
                $this->heartbeat_timer = null;
            }

            if ($this->reconnect_timer) {
                Timer::del($this->reconnect_timer);
                $this->reconnect_timer = null;
            }

            if ($this->ws_connection) {
                $this->ws_connection->close();
                $this->ws_connection = null;
            }
        } catch (\Exception $err) {
            echo "【X】关闭连接错误: " . $err->getMessage() . "\n";
        }

        $this->is_connected = false;
        echo "【√】已停止连接\n";
    }

    private function getTtwid()
    {
        if ($this->ttwid) {
            return $this->ttwid;
        }

        try {
            $response = $this->session->get($this->live_url);
            $cookies  = $response->getHeader('Set-Cookie');
            foreach ($cookies as $cookie) {
                if (strpos($cookie, 'ttwid=') !== false) {
                    preg_match('/ttwid=([^;]+);/', $cookie, $matches);
                    $this->ttwid = $matches[1];
                    break;
                }
            }
        } catch (\Exception $err) {
            echo "【X】请求直播URL错误: " . $err->getMessage() . "\n";
        }

        return $this->ttwid;
    }

    private function getRoomId()
    {
        if ($this->room_id) {
            return $this->room_id;
        }

        $url = $this->live_url . $this->live_id;

        $headers = [
            "User-Agent" => $this->user_agent,
            "cookie"     => $this->cookie ?: "ttwid=" . $this->getTtwid() . "&msToken=" . $this->generateMsToken() . "; __ac_nonce=0123407cc00a9e438deb4",
        ];

        try {
            $response = $this->session->get($url, ['headers' => $headers]);
            $body     = (string) $response->getBody();

            // 匹配 "roomId\":\"数字\" 的模式
            if (preg_match("/roomId\\\\\":\\\\\"(\d+)\\\\\"/", $body, $matches)) {
                // 检查是否匹配成功并捕获了组 1
                if (isset($matches[1]) && is_numeric($matches[1])) {
                    $this->room_id = $matches[1];
                } else {
                    echo "【X】匹配成功但未捕获有效数字 roomId\n";
                }
            } else {
                echo "【X】未找到 roomId。";
            }


            if (empty($matches)) {
                echo "【X】未找到roomId\n";
            } else {
                $this->room_id = $matches[1];
            }
        } catch (\Exception $err) {
            echo "【X】请求直播间URL错误: " . $err->getMessage() . "\n";
        }

        return $this->room_id;
    }

    private function getAcNonce()
    {
        try {
            $response = $this->session->get($this->host);
            $cookies  = $response->getHeader('Set-Cookie');
            foreach ($cookies as $cookie) {
                if (strpos($cookie, '__ac_nonce=') !== false) {
                    preg_match('/__ac_nonce=([^;]+);/', $cookie, $matches);
                    return $matches[1];
                }
            }
        } catch (\Exception $e) {
            echo "获取ac_nonce错误: " . $e->getMessage() . "\n";
        }

        return null;
    }

    private function getAcSignature($ac_nonce = null)
    {
        try {
            // 这里需要实现 get__ac_signature 函数
            $ac_signature = (new AcSignature())->getAcSignature(substr($this->host, 8), $ac_nonce, $this->user_agent);
            return $ac_signature;
        } catch (\Exception $e) {
            echo "获取ac_signature错误: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function getABogus($url_params)
    {
        $url    = http_build_query($url_params);
        $result = JsServer::get('/get_ab', ['dpf' => $url, 'ua' => $this->user_agent]);
        return $result['a_bogus'] ?? null;
    }

    private function getRoomStatus()
    {
        $msToken   = $this->generateMsToken();
        $nonce     = $this->getAcNonce();
        $signature = $this->getAcSignature($nonce);

        $url = ('https://live.douyin.com/webcast/room/web/enter/?aid=6383'
            . '&app_name=douyin_web&live_id=1&device_platform=web&language=zh-CN&enter_from=page_refresh'
            . '&cookie_enabled=true&screen_width=5120&screen_height=1440&browser_language=zh-CN&browser_platform=Win32'
            . '&browser_name=Edge&browser_version=140.0.0.0'
            . '&web_rid=' . $this->live_id
            . '&room_id_str=' . $this->getRoomId()
            . '&enter_source=&is_need_double_stream=false&insert_task_id=&live_reason=&msToken=' . $msToken);

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);
        $a_bogus = $this->getABogus($params);
        $url .= "&a_bogus=" . $a_bogus;

        $headers            = $this->headers;
        $headers['Referer'] = 'https://live.douyin.com/' . $this->live_id;
        $headers['Cookie']  = 'ttwid=' . $this->getTtwid() . ';__ac_nonce=' . $nonce . '; __ac_signature=' . $signature;

        try {
            $response = $this->session->get($url, ['headers' => $headers]);
            $data     = json_decode($response->getBody(), true);

            if (isset($data['data'])) {
                $room_status = $data['data']['room_status'] ?? null;
                // $user        = $data['data']['user'];
                // $user_id     = $user['id_str'];
                // $nickname    = $user['nickname'];
                // $status_text = $room_status == 0 ? '正在直播' : '已结束';
                return $room_status == 0;
            }
        } catch (\Exception $e) {
            echo "获取直播间状态错误: " . $e->getMessage() . "\n";
        }
        return false;
    }

    private function _connectWebSocket()
    {
        $room_id = $this->getRoomId();
        if (! $room_id) {
            echo "【X】获取room_id失败，无法连接WebSocket\n";
            return;
        }

        $wss = "ws://webcast100-ws-web-lq.douyin.com/webcast/im/push/v2/?" . http_build_query([
            'app_name'               => 'douyin_web',
            'version_code'           => '180800',
            'webcast_sdk_version'    => '1.0.14-beta.0',
            'device_platform'        => 'web',
            'cookie_enabled'         => true,
            'screen_width'           => 1536,
            'screen_height'          => 864,
            'browser_language'       => 'zh-CN',
            'browser_platform'       => 'Win32',
            'browser_name'           => 'Mozilla',
            'browser_version'        => '5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'tz_name'                => 'Asia/Shanghai',
            'cursor'                 => 'd-1_u-1_fh-7392091211001140287_t-1721106114633_r-1',
            'wss_push_room_id'       => $room_id,
            'wss_push_did'           => '7319483754668557238',
            'first_req_ms'           => 1721106114541,
            'fetch_time'             => 1721106114633,
            'seq'                    => 1,
            'wrds_v'                 => '7392094459690748497',
            'host'                   => 'https://live.douyin.com',
            'aid'                    => 6383,
            'live_id'                => 1,
            'did_rule'               => 3,
            'endpoint'               => 'live_pc',
            'support_wrds'           => 1,
            'user_unique_id'         => '7319483754668557238',
            'im_path'                => '/webcast/im/fetch/',
            'identity'               => 'audience',
            'need_persist_msg_count' => 15,
            'room_id'                => $room_id,
            'heartbeatDuration'      => 0
        ]);

        try {
            $signature = $this->generateSignature($wss);
            $wss .= "&signature={$signature}";
        } catch (\Exception $e) {
            echo "生成签名失败: " . $e->getMessage() . "\n";
            return;
        }

        $headers = [
            'cookie'     => 'ttwid=' . $this->getTtwid(),
            'user-agent' => $this->user_agent,
        ];

        $this->ws_connection            = new AsyncTcpConnection($wss);
        $this->ws_connection->transport = 'ssl';
        $this->ws_connection->context   = (object) [
            'ssl' => (object) [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'SNI_enabled'      => true,
            ]
        ];
        $this->ws_connection->headers   = $headers;

        $this->ws_connection->onConnect = [$this, '_wsOnConnect'];
        $this->ws_connection->onMessage = [$this, '_wsOnMessage'];
        $this->ws_connection->onError   = [$this, '_wsOnError'];
        $this->ws_connection->onClose   = [$this, '_wsOnClose'];
        $this->ws_connection->connect();
        return $room_id;
    }

    /**
     * WebSocket连接成功
     */
    public function _wsOnConnect($connection)
    {
        $this->is_connected = true;
        echo "【√】WebSocket连接成功.\n";

        // 启动心跳定时器
        $this->heartbeat_timer = Timer::add(5, function () use ($connection)
        {
            $this->_sendHeartbeat($connection);
        });
    }

    public function _wsOnError($connection, $code, $msg)
    {
        echo "【X】WebSocket错误: {$code} - {$msg}\n";
        $this->stop();
    }

    /**
     * 接收到WebSocket消息
     *
     * @param $connection
     * @param $data
     */
    public function _wsOnMessage($connection, $data)
    {
        ParseMessage::init($connection, $data);
    }

    /**
     * WebSocket连接关闭
     *
     * @param $connection
     */
    public function _wsOnClose($connection)
    {
        echo "【X】WebSocket连接关闭.\n";
        $this->stop();

        // // 尝试重新连接
        // if ($this->room_status) {
        //     $connection->reConnect(5);
        // } else {
        //     $this->stop();
        // }
    }

    /**
     * 发送心跳包
     *
     * @param $connection
     */
    private function _sendHeartbeat($connection)
    {
        if (! $this->is_connected) {
            return;
        }
        try {
            $heartbeat = new PushFrame();
            $heartbeat->setPayloadType('hb');
            $connection->send($heartbeat->serializeToString());
            echo "【√】发送心跳包\n";
        } catch (\Exception $e) {
            echo "【X】心跳包发送错误: " . $e->getMessage() . "\n";
        }
    }

    /**
     * 生成MsToken
     *
     * @param int $length
     * @return string
     */
    public static function generateMsToken($length = 182)
    {
        $random_str = '';
        $base_str   = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';
        $base_len   = strlen($base_str) - 1;

        for ($i = 0; $i < $length; $i++) {
            $random_str .= $base_str[rand(0, $base_len)];
        }

        return $random_str;
    }

    /**
     * 生成签名
     *
     * @param $wss
     * @return string|null
     */
    public function generateSignature($wss)
    {
        $params     = explode(',', "live_id,aid,version_code,webcast_sdk_version,room_id,sub_room_id,sub_channel_id,did_rule,user_unique_id,device_platform,device_type,ac,identity");
        $wss_params = explode('&', parse_url($wss, PHP_URL_QUERY));
        $wss_maps   = [];

        foreach ($wss_params as $param) {
            $parts = explode('=', $param);
            if (count($parts) >= 2) {
                $wss_maps[$parts[0]] = $parts[1];
            }
        }

        $tpl_params = [];
        foreach ($params as $param) {
            $value        = $wss_maps[$param] ?? '';
            $tpl_params[] = "{$param}={$value}";
        }

        $param     = implode(',', $tpl_params);
        $md5_param = md5($param);
        $result    = JsServer::get('/get_sign', ['params' => $md5_param]);
        return $result['sign'] ?? null;
    }
}