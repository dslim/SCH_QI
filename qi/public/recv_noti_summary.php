<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

try {
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM NOTICES A, NOTICE_RECEIVERS B WHERE A.NOTICE_ID = B.NOTICE_ID AND B.USER_ID = ? AND B.READ_STATUS = '1'");
    $stmtUnread->execute([$userId]);
    $unreadCount = $stmtUnread->fetchColumn();

    $stmtOngoing = $pdo->prepare("SELECT COUNT(*) FROM NOTICES A, NOTICE_RECEIVERS B WHERE A.NOTICE_ID = B.NOTICE_ID AND B.USER_ID = ? AND B.READ_STATUS = '2'");
    $stmtOngoing->execute([$userId]);
    $ongoingCount = $stmtOngoing->fetchColumn();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $unreadCount = 0;
    $ongoingCount = 0;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>공지사항 요약</title>
    <style>
        :root {
            --primary-color: #4a90e2;
            --bg-color: #f8f9fa;
            --unread-color: #ff4d4f;
            --ongoing-color: #52c41a;
        }

        /* 여백 계산을 포함한 박스 모델 설정 */
        * { box-sizing: border-box; }

        body {
            font-family: 'Pretendard', -apple-system, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            /* 세로 스크롤 방지 핵심 설정 */
            height: 100dvh; /* 모바일 브라우저 주소창 고려 */
            width: 100%;
            overflow: hidden; 
            display: flex;
            flex-direction: column;
            align-items: center; 
            justify-content: center;
        }

        .header {
            text-align: center;
            margin-bottom: 4vh; /* 고정 px 대신 vh 사용 */
        }

        .header h1 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-top: 40px;
            color: #555;
        }

        .header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            margin: 8px 0 50px 0;
            color: #777;
        }
        
        .header span {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .card-container {
            width: 90%;
            max-width: 360px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 5vh 20px; /* 세로 여백을 화면 높이에 비례하게 조절 */
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
            font-size: 1.5rem;
            font-weight: 500;
            color: #888;
            margin-bottom: 5px;
        }

        .card-count {
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .card-count small {
            font-size: 1.5rem;
            margin-left: 4px;
        }

        .unread .card-count { color: var(--unread-color); }
        .ongoing .card-count { color: var(--ongoing-color); }

        .logout-btn {
            margin-top: 5vh;
            color: #bbb;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1><span><?= htmlspecialchars($userName) ?></span> 님, 안녕하세요!</h1>
        <h2>공지현황을 확인하세요.</h2>
    </div>

    <div class="card-container">
        <a href="/qi/public/recv_noti_list.php?type=unread" class="summary-card unread">
            <div class="card-title">미확인</div>
            <div class="card-count"><?= sprintf('%d', $unreadCount) ?><small>건</small></div>
        </a>

        <a href="/qi/public/recv_noti_list.php?type=read" class="summary-card ongoing">
            <div class="card-title">확인</div>
            <div class="card-count"><?= sprintf('%d', $ongoingCount) ?><small>건</small></div>
        </a>
    </div>

    <a href="logout.php" class="logout-btn">로그아웃</a>

</body>
</html>