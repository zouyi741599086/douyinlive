<?php
namespace app;

class JsServer
{
    private static $url = 'http://localhost:3000';

    /**
     * 请求node运行的服务，获取js执行的结果
     * @param string $path
     * @param array $params
     * @throws \Exception
     */
    public static function get(string $path, array $params)
    {
        $url  = self::$url . $path;
        $data = json_encode($params);

        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $data,
            ],
        ];

        $context = stream_context_create($options);
        $result  = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            throw new \Exception("请求Node.js服务失败");
        }

        return json_decode($result, true);
    }
}
