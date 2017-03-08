<?php

require '../vendor/autoload.php';

$App = new \Slim\App();

require '../app/routes.php';

$App->run();