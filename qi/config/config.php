<?php
/**
 * 프로젝트 전역 설정 파일
 */

// 1. 서버 환경 설정 (development 또는 production)
//define('APP_ENV', 'development'); 
define('APP_ENV', 'production'); 

// 2. 에러 리포팅 설정 (개발 환경에서만 출력)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 3. 데이터베이스 및 앱 설정 반환
return [
    // 데이터베이스 접속 정보
    'db' => [
        'host' => 'localhost',
        'name' => 'blab',     // 실제 DB명으로 변경
        'user' => 'blab',     // 실제 ID로 변경
        'pass' => 'tjdrhd91!', // 실제 PW로 변경
        'charset' => 'utf8mb4',
    ],

    // 앱 관련 설정
    'app' => [
        'title' => '진지(진료지원간호팀) ONE TEAM',
        'base_url' => 'http://blab.dothome.co.kr/', // 실제 URL로 변경
        'admin_email' => '',
    ],

    // 공지사항 상태 코드 정의
    'status' => [
        'unread' => 0,
        'read' => 1,
        'ongoing' => 'ongoing',
    ]
];