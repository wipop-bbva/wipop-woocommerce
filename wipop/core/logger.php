<?php
namespace Wipop\Core;

defined( 'ABSPATH' ) || exit;

trait Logger {
    protected function log( $message, $level = 'info' ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            $logger = wc_get_logger();
            $logger->$level( $message, array( 'source' => 'wipop' ) );
        }
    }
}
