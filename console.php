#!/usr/bin/env php
<?php

require_once './autoload.php';

use Symfony\Component\Console\Application;
use ImageRecoveryOrganizer\Commands\ImageRecoveryOrganizer as IRO;

$application = new Application();
$application->add(new IRO());
$application->run();





