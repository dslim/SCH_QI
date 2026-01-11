<?php

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

try {
    // DB 쿼리 예시: 
    $sql = "SELECT 
  A.NOTICE_ID, A.TITLE, IF(CHAR_LENGTH(A.CONTENT) > 100, CONCAT(LEFT(A.CONTENT, 100), '...'), A.CONTENT) AS CONTENT, A.REG_USER_ID, B.READ_STATUS AS STATUS, A.REG_DATE, A.UPDATE_DATE, B.READ_STATUS, B.OPEN_DATE, B.UPDATE_DATE, B.COMPLT_DATE 
  FROM NOTICES A, NOTICE_RECEIVERS B 
  WHERE A.NOTICE_ID = B.NOTICE_ID AND B.USER_ID = ?
  ORDER BY B.READ_STATUS, A.REG_DATE DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $notiList = [];
    foreach ($rows as $row) {
        $statusValue = (string)$row['READ_STATUS'];
        switch ($statusValue) {
            case '1': $statusText = '미확인'; break;
            case '2': $statusText = '확인'; break;
            default:  $statusText = '미확인'; // 예외 케이스 처리
        }

        $notiList[] = [
            'id'       => $row['NOTICE_ID'],
            'title'    => $row['TITLE'],
            'content'  => $row['CONTENT'],
            'author'   => $row['REG_USER_ID'], // 필요 시 JOIN을 통해 작성자 이름을 가져올 수 있음
            'date'     => date('Y-m-d', strtotime($row['REG_DATE'])),
            'myStatus' => $statusText
        ];
    }
    
    $notifications_json = json_encode($notiList, JSON_UNESCAPED_UNICODE);
    
    
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
    <title>나의 공지 현황</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        body { font-family: -apple-system, sans-serif; background-color: #f0f2f5; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; position: sticky; top: 0; z-index: 100; }
        .user-profile { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .user-name { font-weight: bold; color: #777; font-size: 1.3rem; margin-top:20px;}
        h2 { margin: 0; font-size: 1.2rem; text-align: center; }

        .filter-bar { display: flex; justify-content: space-around; background: #fff; padding: 10px 0; border-bottom: 1px solid #eee; }
        .filter-tab { font-size: 17px; color: #888; cursor: pointer; padding: 5px 10px; position: relative; }
        .filter-tab.active { color: #333; font-weight: bold; }
        .filter-tab.active::after { content: ''; position: absolute; bottom: -10px; left: 0; width: 100%; height: 2px; background: #007bff; }

        .container { padding: 15px; }
        .card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        
        .card-top { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .badge { font-size: 15px; padding: 2px 6px; border-radius: 4px; font-weight: bold; }
        .badge.unread { background: #ffe3e3; color: #ff4d4d; }
        .badge.read { background: #e3f2fd; color: #1976d2; }
        .badge.solved { background: #e8f5e9; color: #388e3c; }

        .title-area { cursor: pointer; } 
        .title { font-size: 16px; font-weight: bold; margin-bottom: 5px; color: #333; }
        .content { font-size: 14px; color: #666; margin-bottom: 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .action-area { display: flex; gap: 5px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f8f9fa; }
        .status-box { flex: 1; padding: 8px; border-radius: 6px; border: 1px solid #eee; font-size: 12px; font-weight: bold; text-align: center; color: #ddd; background: #fafafa; }
        
        .status-box.active-unread { background: #ff4d4d; color: white; border-color: #ff4d4d; }
        .status-box.active-read { background: #007bff; color: white; border-color: #007bff; }
        .status-box.active-solved { background: #388e3c; color: white; border-color: #388e3c; }

        .footer-info { font-size: 16px; color: #555; display: flex; justify-content: space-between; margin-top: 10px; }
    </style>
</head>
<body>

<div id="app">
    <div class="header">
        <div class="user-profile">
            <span class="user-name">{{ loginUser }} 님의 공지현황입니다.</span>
        </div>
    </div>

    <div class="filter-bar">
        <div v-for="tab in ['all', '미확인', '확인']" :key="tab" 
             class="filter-tab" :class="{active: currentTab === tab}" @click="currentTab = tab">
            {{ tab === 'all' ? '전체' : tab }}
        </div>
    </div>

    <div class="container">
        <div v-for="item in myFilteredNoti" :key="item.id" class="card">
            <div class="card-top">
                <span style="font-size: 15px; color: #ccc;">No. {{ item.id }}</span>
                <span :class="['badge', getStatusClass(item.myStatus)]">{{ item.myStatus }}</span>
            </div>
            
            <div class="title-area" @click="goToDetail(item.id)">
                <div class="title">{{ item.title }}</div>
                <div class="content">{{ item.content }}</div>
            </div>
            <!--
            <div class="action-area">
                <div class="status-box" :class="{'active-unread': item.myStatus === '미확인'}">미확인</div>
                <div class="status-box" :class="{'active-read': item.myStatus === '확인'}">확인</div>
            </div>
            -->

            <div class="footer-info">
                <span>작성자: {{ item.author }}</span>
                <span>{{ item.date }}</span>
            </div>
        </div>

        <div v-if="myFilteredNoti.length === 0" style="text-align: center; color: #999; margin-top: 50px;">
            수신된 공지사항이 없습니다.
        </div>
    </div>
</div>

<script>
    const { createApp, ref, computed, onMounted } = Vue;

    createApp({
        setup() {
            // PHP 데이터 파싱
            const loginUser = ref('<?php echo $userName; ?>');
            const initialData = <?php echo $notifications_json; ?>;
            const myNotifications = ref(initialData);

            // 1. URL 파라미터(type)에 따른 초기 탭 설정 로직
            const getInitialTab = () => {
                const urlParams = new URLSearchParams(window.location.search);
                const typeParam = urlParams.get('type'); // 'unread' 또는 'read'

                if (typeParam === 'unread') return '미확인';
                if (typeParam === 'read') return '확인';
                return 'all'; // 파라미터가 없거나 다를 경우 '전체'
            };

            // 2. 초기값 설정
            const currentTab = ref(getInitialTab());

            // 탭 변경 시 필터링 로직
            const myFilteredNoti = computed(() => {
                if (currentTab.value === 'all') return myNotifications.value;
                return myNotifications.value.filter(n => n.myStatus === currentTab.value);
            });

            const getStatusClass = (status) => {
                const map = { '미확인': 'unread', '확인': 'read' };
                return map[status] || '';
            };

            const goToDetail = (id) => {
                window.location.href = `recv_noti_detail.php?id=${id}`;
            };

            return {
                loginUser, currentTab,
                myFilteredNoti, getStatusClass, goToDetail
            };
        }
    }).mount('#app');
</script>

</body>
</html>