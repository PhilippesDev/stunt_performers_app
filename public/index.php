<?php
/**
 * Front Controller - Entry point for all HTTP requests
 */

require_once __DIR__ . '/../libs/Router.php';
require_once __DIR__ . '/../libs/routes.php';

// Dispatch request to appropriate route handler
Router::dispatch();