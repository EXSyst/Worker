<?php

namespace EXSyst\Component\Worker\Internal;

use stdClass;
use EXSyst\Component\IO\Channel\ChannelInterface;
use EXSyst\Component\IO\Channel\SerializedChannel;
use EXSyst\Component\Worker\Status\WorkerStatus;

final class AdminEncoding
{
    private function __construct()
    {
    }

    /**
     * @param mixed       $message
     * @param string|null $adminCookie
     * @param bool        $privileged
     *
     * @return bool
     */
    public static function isStopMessage($message, $adminCookie, &$privileged)
    {
        if ($message instanceof StopMessage) {
            $privileged = $adminCookie !== null && $message->getCookie() === $adminCookie;

            return true;
        } elseif ($message instanceof stdClass && isset($message->_stop_)) {
            $privileged = $adminCookie !== null && $message->_stop_ === $adminCookie;

            return true;
        } elseif (is_array($message) && isset($message['_stop_'])) {
            $privileged = $adminCookie !== null && $message['_stop_'] === $adminCookie;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param mixed       $message
     * @param string|null $adminCookie
     * @param bool        $privileged
     *
     * @return bool
     */
    public static function isQueryMessage($message, $adminCookie, &$privileged)
    {
        if ($message instanceof QueryMessage) {
            $privileged = $adminCookie !== null && $message->getCookie() === $adminCookie;

            return true;
        } elseif ($message instanceof stdClass && isset($message->_query_)) {
            $privileged = $adminCookie !== null && $message->_query_ === $adminCookie;

            return true;
        } elseif (is_array($message) && isset($message['_query_'])) {
            $privileged = $adminCookie !== null && $message['_query_'] === $adminCookie;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param mixed $message
     *
     * @return WorkerStatus|null
     */
    public static function getStatusMessage($message)
    {
        if ($message instanceof WorkerStatus) {
            return $message;
        } elseif ($message instanceof stdClass && isset($message->_status_)) {
            return WorkerStatus::fromArrayOrObject($message->_status_);
        } elseif (is_array($message) && isset($message['_status_'])) {
            return WorkerStatus::fromArrayOrObject($message['_status_']);
        }
    }

    /**
     * @param ChannelInterface $channel
     * @param string           $adminCookie
     *
     * @throws Exception\RuntimeException
     */
    public static function sendStopMessage(ChannelInterface $channel, $adminCookie)
    {
        $channel->sendMessage(($channel instanceof SerializedChannel) ? new StopMessage($adminCookie) : ['_stop_' => $adminCookie]);
    }

    /**
     * @param ChannelInterface $channel
     * @param string           $adminCookie
     *
     * @throws Exception\RuntimeException
     */
    public static function sendQueryMessage(ChannelInterface $channel, $adminCookie)
    {
        $channel->sendMessage(($channel instanceof SerializedChannel) ? new QueryMessage($adminCookie) : ['_query_' => $adminCookie]);
    }

    /**
     * @param ChannelInterface $channel
     * @param WorkerStatus     $result
     *
     * @throws Exception\RuntimeException
     */
    public static function sendStatusMessage(ChannelInterface $channel, WorkerStatus $result)
    {
        $channel->sendMessage(($channel instanceof SerializedChannel) ? $result : ['_status_' => $result->toArray()]);
    }
}
