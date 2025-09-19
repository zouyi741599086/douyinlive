<?php
namespace app;

use app\protobuf\douyin\Response;
use app\protobuf\douyin\ChatMessage;
use app\protobuf\douyin\GiftMessage;
use app\protobuf\douyin\LikeMessage;
use app\protobuf\douyin\MemberMessage;
use app\protobuf\douyin\SocialMessage;
use app\protobuf\douyin\RoomUserSeqMessage;
use app\protobuf\douyin\FansclubMessage;
use app\protobuf\douyin\EmojiChatMessage;
use app\protobuf\douyin\RoomMessage;
use app\protobuf\douyin\RoomStatsMessage;
use app\protobuf\douyin\RoomRankMessage;
use app\protobuf\douyin\ControlMessage;
use app\protobuf\douyin\RoomStreamAdaptationMessage;
use app\protobuf\douyin\PushFrame;

class ParseMessage
{
    /**
     * 解析各种消息
     * @param mixed $connection
     * @param mixed $message
     * @return void
     */
    public static function init($connection, $message)
    {
        try {
            // 解析 PushFrame
            $pushFrame = new PushFrame();
            $pushFrame->mergeFromString($message);

            // 解压 payload
            $payload = $pushFrame->getPayload();
            if ($pushFrame->getPayloadType() == 'gzip') {
                $payload = gzdecode($payload);
            }

            // 解析 Response
            $response = new Response();
            $response->mergeFromString($payload);

            // 如果需要 ack，发送确认
            if ($response->getNeedAck()) {
                $ack = new PushFrame();
                $ack->setLogId($pushFrame->getLogId());
                $ack->setPayloadType('ack');
                $ack->setPayload($response->getInternalExt());
                $connection->send($ack->serializeToString());
            }

            // 处理消息
            foreach ($response->getMessagesList() as $msg) {
                $method  = $msg->getMethod();
                $payload = $msg->getPayload();

                try {
                    $handlers = [
                        'WebcastChatMessage'                 => '_parseChatMsg',
                        'WebcastGiftMessage'                 => '_parseGiftMsg',
                        'WebcastLikeMessage'                 => '_parseLikeMsg',
                        'WebcastMemberMessage'               => '_parseMemberMsg',
                        'WebcastSocialMessage'               => '_parseSocialMsg',
                        'WebcastRoomUserSeqMessage'          => '_parseRoomUserSeqMsg',
                        'WebcastFansclubMessage'             => '_parseFansclubMsg',
                        'WebcastControlMessage'              => '_parseControlMsg',
                        'WebcastEmojiChatMessage'            => '_parseEmojiChatMsg',
                        'WebcastRoomStatsMessage'            => '_parseRoomStatsMsg',
                        'WebcastRoomMessage'                 => '_parseRoomMsg',
                        'WebcastRoomRankMessage'             => '_parseRankMsg',
                        'WebcastRoomStreamAdaptationMessage' => '_parseRoomStreamAdaptationMsg',
                    ];

                    if (isset($handlers[$method])) {
                        $handler = $handlers[$method];
                        self::$handler($payload);
                    }
                } catch (\Exception $e) {
                    error_log("处理消息错误: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log("解析消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析聊天消息
     * @param $payload
     */
    private static function _parseChatMsg($payload)
    {
        try {
            $message = new ChatMessage();
            $message->mergeFromString($payload);

            $user      = $message->getUser();
            $user_name = $user->getNickName();
            $user_id   = $user->getId();
            $content   = $message->getContent();

            echo "【聊天msg】[{$user_id}]{$user_name}: {$content}\n";
        } catch (\Exception $e) {
            error_log("解析聊天消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析礼物消息
     * @param $payload
     */
    private static function _parseGiftMsg($payload)
    {
        try {
            $message = new GiftMessage();
            $message->mergeFromString($payload);

            $user      = $message->getUser();
            $user_name = $user->getNickName();
            $gift      = $message->getGift();
            $gift_name = $gift->getName();
            $gift_cnt  = $message->getComboCount();

            echo "【礼物msg】{$user_name} 送出了 {$gift_name}x{$gift_cnt}\n";
        } catch (\Exception $e) {
            error_log("解析礼物消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析点赞消息
     * @param $payload
     */
    private static function _parseLikeMsg($payload)
    {
        try {
            $message = new LikeMessage();
            $message->mergeFromString($payload);

            $user      = $message->getUser();
            $user_name = $user->getNickName();
            $count     = $message->getCount();

            echo "【点赞msg】{$user_name} 点了{$count}个赞\n";
        } catch (\Exception $e) {
            error_log("解析点赞消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析进场消息
     * @param $payload
     */
    private static function _parseMemberMsg($payload)
    {
        try {
            $message = new MemberMessage();
            $message->mergeFromString($payload);

            $user      = $message->getUser();
            $user_name = $user->getNickName();
            $user_id   = $user->getId();
            $gender    = $user->getGender() == 1 ? "男" : "女";

            echo "【进场msg】[{$user_id}][{$gender}]{$user_name} 进入了直播间\n";
        } catch (\Exception $e) {
            error_log("解析进场消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析关注消息
     * @param $payload
     */
    private static function _parseSocialMsg($payload)
    {
        try {
            $message = new SocialMessage();
            $message->mergeFromString($payload);

            $user      = $message->getUser();
            $user_name = $user->getNickName();
            $user_id   = $user->getId();

            echo "【关注msg】[{$user_id}]{$user_name} 关注了主播\n";
        } catch (\Exception $e) {
            error_log("解析关注消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析统计消息
     * @param $payload
     */
    private static function _parseRoomUserSeqMsg($payload)
    {
        try {
            $message = new RoomUserSeqMessage();
            $message->mergeFromString($payload);

            $current = $message->getTotal();
            $total   = $message->getTotalPvForAnchor();

            echo "【统计msg】当前观看人数: {$current}, 累计观看人数: {$total}\n";
        } catch (\Exception $e) {
            error_log("解析统计消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析粉丝团消息
     * @param $payload
     */
    private static function _parseFansclubMsg($payload)
    {
        try {
            $message = new FansclubMessage();
            $message->mergeFromString($payload);

            $content = $message->getContent();

            echo "【粉丝团msg】 {$content}\n";
        } catch (\Exception $e) {
            error_log("解析粉丝团消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析聊天表情包消息
     * @param $payload
     */
    private static function _parseEmojiChatMsg($payload)
    {
        try {
            $message = new EmojiChatMessage();
            $message->mergeFromString($payload);

            $emoji_id        = $message->getEmojiId();
            $user            = $message->getUser();
            $common          = $message->getCommon();
            $default_content = $message->getDefaultContent();

            $user_name   = $user->getNickName();
            $common_desc = $common->getDescribe();

            echo "【聊天表情包msg】表情ID: {$emoji_id}, 用户: {$user_name}, 描述: {$common_desc}, 默认内容: {$default_content}\n";
        } catch (\Exception $e) {
            error_log("解析表情包消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析直播间消息
     * @param $payload
     */
    private static function _parseRoomMsg($payload)
    {
        try {
            $message = new RoomMessage();
            $message->mergeFromString($payload);

            $common  = $message->getCommon();
            $room_id = $common->getRoomId();
            $content = $message->getContent();

            echo "【直播间msg】直播间ID: {$room_id}, 内容: {$content}\n";
        } catch (\Exception $e) {
            error_log("解析直播间消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析直播间统计消息
     * @param $payload
     */
    private static function _parseRoomStatsMsg($payload)
    {
        try {
            $message = new RoomStatsMessage();
            $message->mergeFromString($payload);

            $display_short  = $message->getDisplayShort();
            $display_middle = $message->getDisplayMiddle();
            $display_long   = $message->getDisplayLong();

            echo "【直播间统计msg】短显示: {$display_short}, 中显示: {$display_middle}, 长显示: {$display_long}\n";
        } catch (\Exception $e) {
            error_log("解析直播间统计消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析排行榜消息
     * @param $payload
     */
    private static function _parseRankMsg($payload)
    {
        try {
            $message = new RoomRankMessage();
            $message->mergeFromString($payload);

            $ranks_list = $message->getRanksList();
            $rank_info  = [];

            foreach ($ranks_list as $rank) {
                $user        = $rank->getUser();
                $user_name   = $user->getNickName();
                $score_str   = $rank->getScoreStr();
                $rank_info[] = "{$user_name}: {$score_str}";
            }

            echo "【直播间排行榜msg】" . implode(", ", $rank_info) . "\n";
        } catch (\Exception $e) {
            error_log("解析排行榜消息错误: " . $e->getMessage());
        }
    }
    /**
     * 解析控制消息
     * @param $payload
     */
    private static function _parseControlMsg($payload)
    {
        try {
            $message = new ControlMessage();
            $message->mergeFromString($payload);

            $status = $message->getStatus();

            if ($status == 3) {
                echo "【直播间状态msg】直播间已结束\n";
            } else {
                echo "【直播间状态msg】直播间状态: {$status}\n";
            }
        } catch (\Exception $e) {
            error_log("解析控制消息错误: " . $e->getMessage());
        }
    }

    /**
     * 解析流配置消息
     * @param $payload
     */
    private static function _parseRoomStreamAdaptationMsg($payload)
    {
        try {
            $message = new RoomStreamAdaptationMessage();
            $message->mergeFromString($payload);

            $adaptation_type              = $message->getAdaptationType();
            $adaptation_height_ratio      = $message->getAdaptationHeightRatio();
            $adaptation_body_center_ratio = $message->getAdaptationBodyCenterRatio();

            echo "【直播间流配置msg】适配类型: {$adaptation_type}, 高度比例: {$adaptation_height_ratio}, 身体中心比例: {$adaptation_body_center_ratio}\n";
        } catch (\Exception $e) {
            error_log("解析流配置消息错误: " . $e->getMessage());
        }
    }

}