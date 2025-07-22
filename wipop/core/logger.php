<?php

namespace Wipop\Core;

defined('ABSPATH') || exit;

class Logger {
    public static function log($message, $level = 'info') {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, [ 'source' => 'wipop' ]);
        }
    }
}
