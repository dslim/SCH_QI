<?php
/**
 * 1. 초기 설정 및 보안 체크
 */
session_start();
define('ROOT_PATH', dirname(__DIR__));

// 로그인하지 않은 경우 로그인 페이지로 리다이렉트
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// DB 연결 로드
require_once ROOT_PATH . '/src/db_connect.php';

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name']; // 로그인 시 저장된 사용자 이름

/**
 * 2. 실시간 데이터 조회 (PDO 사용)
 */
try {
    // 1) 미확인 건수 (READ_STATUS-1:미확인, 2:참여중, 3:완료
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM NOTICES WHERE USER_ID = ? AND READ_STATUS = '1'");
    $stmtUnread->execute([$userId]);
    $unreadCount = $stmtUnread->fetchColumn();

    // 2) 참여중 건수 (status가 'ongoing'인 항목)
    $stmtOngoing = $pdo->prepare("SELECT COUNT(*) FROM NOTICES WHERE USER_ID=? AND READ_STATUS = '2'");
    $stmtOngoing->execute([$userId]);
    $ongoingCount = $stmtOngoing->fetchColumn();
    
} catch (PDOException $e) {
    // 에러 발생 시 로그에 기록하고 0으로 초기화
    error_log($e->getMessage());
    $unreadCount = 0;
    $ongoingCount = 0;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공지사항 요약</title>
    <style>
        :root {
            --primary-color: #4a90e2;
            --bg-color: #f8f9fa;
            --unread-color: #ff4d4f;
            --ongoing-color: #52c41a;
        }

        body {
            font-family: 'Pretendard', -apple-system, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center; 
            min-height: 100vh;
            justify-content: center;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .header span {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .card-container {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 8px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .summary-card:active {
            transform: scale(0.96);
            background-color: #f0f0f0;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 500;
            color: #888;
            margin-bottom: 10px;
        }

        .card-count {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .card-count small {
            font-size: 1.2rem;
            font-weight: 600;
            margin-left: 4px;
        }

        .unread .card-count { color: var(--unread-color); }
        .ongoing .card-count { color: var(--ongoing-color); }

        .logout-btn {
            margin-top: 30px;
            color: #999;
            text-decoration: none;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1><span><?= htmlspecialchars($userName) ?></span> 님, 안녕하세요!</h1>
    </div>

    <div class="card-container">
        <a href="recv_noti_list.php?type=unread" class="summary-card unread">
            <div class="card-title">미확인</div>
            <div class="card-count"><?= sprintf('%02d', $unreadCount) ?><small>건</small></div>
        </a>

        <a href="recv_noti_list.php?type=ongoing" class="summary-card ongoing">
            <div class="card-title">참여중</div>
            <div class="card-count"><?= sprintf('%02d', $ongoingCount) ?><small>건</small></div>
        </a>
    </div>

    <a href="logout.php" class="logout-btn">로그아웃</a>

</body>
</html>