<?php
// 프로젝트의 루트 경로를 상수로 정의
define('ROOT_PATH', dirname(__DIR__)); 

// 상수를 활용한 파일 포함
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/src/db_connect.php';