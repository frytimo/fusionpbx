<?php

defined('STDIN') or die;

require dirname(__DIR__, 4) . '/resources/auto_loader.php'; new auto_loader();

$fax_queue_service = fax_queue_service::create();
$fax_queue_service->run();
