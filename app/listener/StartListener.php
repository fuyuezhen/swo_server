<?php
namespace app\listener;

use swostar\event\Listener;
use Swoole\Coroutine;

/**
 * 开始监听
 */
class StartListener extends Listener
{
    /**
     * 事件名称
     *
     * @var string
     */
    protected $name = "start";

    /**
     * 事件处理程序的方法
     *
     * @param [type] $swoStarServer
     * @return void
     */
    public function handler($swoStarServer = null)
    {
        $config = $this->app->make('config');

        info("服务注册：" . $swoStarServer->getHost() . ":" . $swoStarServer->getPort());
        info("路由地址：" . $config->get('server.route.server.host') . ":" . $config->get('server.route.server.port'));
        // 这里我们需要使用协程客户端来实现功能
        // 因为IM - Server 中启动swoole服务就会请求 Route 的服务，并进行注册。那么IM-Server相对于Route来说就是一个客户端，同时还要做间断性的发送信息，以保持连接。
        Coroutine::create(function() use($swoStarServer, $config){
            $client = new \Swoole\Coroutine\Http\Client($config->get('server.route.server.host'), $config->get('server.route.server.port'));
            $ret = $client->upgrade("/"); // 升级为 WebSocket 连接。
            if ($ret) {
                $data = [
                    'method'      => 'register',
                    'serviceName' => 'IM1',
                    'ip'          => $swoStarServer->getHost(),
                    'port'        => $swoStarServer->getPort(),
                ];
                $client->push(json_encode($data));
                // 心跳处理
                swoole_timer_tick(3000, function() use($client){
                    if($client->errCode == 0){
                        // 一旦建立与服务器的连接，客户端和服务端都可以发起一个ping请求，当接收到一个ping请求，那么接收端必须要尽快回复pong请求，通过这种方式，来确认对方是否存活
                        $client->push('', WEBSOCKET_OPCODE_PING);
                    }
                });
            }
            // $client->close();
        });
    }
}
