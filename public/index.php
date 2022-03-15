<?php
// https://github.com/Luracast/Restler
require_once '../library/restler/restler.php';
require_once '../application/mobile.php';
use Luracast\Restler\Restler;
use Luracast\Restler\Defaults;
Defaults::$throttle = 20;
$r = new Restler();
$r->setSupportedFormats('JsonFormat', 'XmlFormat');
$r->addAPIClass('mobile');
$r->addAPIClass('Explorer'); //from restler framework for API Explorer
$r->addFilterClass('RateLimit'); 
$r->handle();?>
