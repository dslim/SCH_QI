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
    /**
     * 한 번의 쿼리로 모든 상태 카운트 조회
     * 1: 미확인, 2: 참여중, 3: 완료됨
     */
    $sql = "SELECT 
                COUNT(CASE WHEN STATUS = '1' THEN 1 END) as ongoing_cnt,
                COUNT(CASE WHEN STATUS = '3' THEN 1 END) as solved_cnt
            FROM NOTICES";

    $stmt = $pdo->prepare($sql);
//    $stmt->execute([$userId]);
    $stmt->execute();
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    $ongoingCount = $counts['ongoing_cnt'] ?? 0;
    $solvedCount  = $counts['solved_cnt'] ?? 0;
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $unreadCount = 0; $ongoingCount = 0; $solvedCount = 0;
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
            --ongoing-color: #1976d2;
            --solved-color: #52c41a;
        }

        body {
            font-family: 'Pretendard', -apple-system, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center; 
            min-height: 100vh;
            justify-content: center;
        }

        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { font-size: 1.4rem; margin: 0; color: #333; }
        .header h1 span { color: var(--primary-color); font-weight: 800; }
        .header p { color: #888; margin-top: 8px; font-size: 1rem; font-size: 1.4rem; font-weight: 500; /* 가독성을 위해 약간의 굵기 추가 가능 (선택사항)*/ }

        .card-container {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .summary-card {
            background: white;
            border-radius: 20px;
            padding: 25px 20px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s ease;
            -webkit-tap-highlight-color: transparent;
        }

        .summary-card:active { transform: scale(0.97); background-color: #fcfcfc; }

        .card-info { display: flex; flex-direction: column; }
        .card-title { font-size: 1.8rem; font-weight: 600; color: #666; }
        
        .card-count { font-size: 2.2rem; font-weight: 800; }
        .card-count small { font-size: 1.8rem; margin-left: 2px; }

        /* 상태별 색상 */
        .unread .card-count { color: var(--unread-color); }
        .ongoing .card-count { color: var(--ongoing-color); }
        .solved .card-count { color: var(--solved-color); }

        .view-all {
            margin-top: 30px;
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
            padding: 10px 20px;
            border: 1.5px solid var(--primary-color);
            border-radius: 30px;
            font-size: 0.9rem;
        }

        .logout-btn { margin-top: 20px; color: #bbb; text-decoration: none; font-size: 0.85rem; }
    </style>
</head>
<body>

    <div class="header">
        <h1><span><?= htmlspecialchars($userName) ?></span> 님, 안녕하세요!</h1>
        <p>오늘 확인하실 공지는 다음과 같습니다.</p>
    </div>

    <div class="card-container">
        <a href="noti_mng_list.php?tab=공지" class="summary-card ongoing">
            <div class="card-info">
                <div class="card-title">공지</div>
            </div>
            <div class="card-count"><?= sprintf('%01d', $ongoingCount) ?><small>건</small></div>
        </a>

        <a href="noti_mng_list.php?tab=완료" class="summary-card solved">
            <div class="card-info">
                <div class="card-title">완료</div>
            </div>
            <div class="card-count"><?= sprintf('%01d', $solvedCount) ?><small>건</small></div>
        </a>
    </div>
    
    <a href="logout.php" class="logout-btn">로그아웃</a>

</body>
</html>