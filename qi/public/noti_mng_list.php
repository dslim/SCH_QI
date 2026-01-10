<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

try {
    $sql = "
SELECT 
    A.NOTICE_ID as id, 
    A.TITLE as title, 
    IF(CHAR_LENGTH(A.CONTENT) > 100, CONCAT(LEFT(A.CONTENT, 100), '...'), A.CONTENT) as content,
    A.REG_USER_ID as author, 
    DATE_FORMAT(A.REG_DATE, '%Y-%m-%d') as date, 
    A.STATUS,
    B.VALUE as status,
    (SELECT COUNT(USER_ID) FROM NOTICE_RECEIVERS WHERE NOTICE_ID = A.NOTICE_ID) as totalCount,
    (SELECT COUNT(USER_ID) FROM NOTICE_RECEIVERS WHERE NOTICE_ID = A.NOTICE_ID AND READ_STATUS = '1') as readCount,
    (SELECT COUNT(USER_ID) FROM NOTICE_RECEIVERS WHERE NOTICE_ID = A.NOTICE_ID AND READ_STATUS = '2') as joinCount,
    (SELECT COUNT(USER_ID) FROM NOTICE_RECEIVERS WHERE NOTICE_ID = A.NOTICE_ID AND READ_STATUS = '3') as solveCount
FROM NOTICES A, CODE_MASTER B
WHERE A.STATUS = B.CODE 
  AND B.CODE_TYPE = 'NOTICE_STATUS'
ORDER BY A.STATUS, A.REG_DATE DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $notices = [];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 공지 관리</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        body { font-family: -apple-system, sans-serif; background-color: #f0f2f5; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; position: sticky; top: 0; z-index: 100; }
        h2 { text-align: center; margin: 0 0 15px 0; font-size: 1.2rem; }
        
        .date-filter { display: flex; align-items: center; justify-content: center; gap: 5px; margin-bottom: 12px; background: #f9f9f9; padding: 8px; border-radius: 8px; }
        .date-filter input { border: 1px solid #ccc; border-radius: 4px; padding: 5px; font-size: 13px; outline: none; }
        
        /* --- [변경] 세그먼트 바 스타일 필터 --- */
        .header-stats { 
            display: flex; 
            background-color: #efefef; /* 바 배경색 */
            padding: 3px; 
            border-radius: 10px; 
            margin: 0 5px;
        }
        .stat-item { 
            flex: 1; /* 너비 균등 배분 */
            font-size: 12px; 
            padding: 8px 0; 
            text-align: center;
            border-radius: 8px; 
            color: #777; 
            cursor: pointer; 
            transition: all 0.2s ease;
            font-weight: 500;
        }
        /* 선택된 아이템 공통 스타일 */
        .stat-item.active { 
            background-color: #fff; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.1); 
            font-weight: bold; 
        }
        /* 상태별 텍스트 강조색 */
        .stat-item.total.active { color: #333; }
        .stat-item.ing.active { color: #d32f2f; }   /* 공지: Red */
        .stat-item.done.active { color: #388e3c; }  /* 완료: Green */
        .stat-item.drop.active { color: #757575; }  /* 폐기: Gray */
        /* ---------------------------------- */

        .container { padding: 15px; }
        
        .card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); cursor: pointer; }
        .card:active { background-color: #f9f9f9; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        
        /* 카드 내 상태 태그 색상 */
        .status { font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: bold; }
        .status.공지 { background: #ffebee; color: #d32f2f; } 
        .status.완료 { background: #e8f5e9; color: #388e3c; }
        .status.폐기 { background: #eeeeee; color: #757575; }
        
        .title { font-size: 16px; font-weight: bold; margin-bottom: 5px; color: #333; }
        .content { font-size: 14px; color: #666; margin-bottom: 12px; line-height: 1.4; }

        .receiver-stats { display: flex; flex-wrap: wrap; gap: 8px; background-color: #f8f9fa; padding: 6px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #f1f3f5; }
        .stat-badge { font-size: 15px; display: flex; align-items: center; gap: 4px; }
        .stat-label { color: #888; font-size: 15px; }
        .stat-value { font-weight: 700; color: #212529; }
        .total-box { border-right: 1px solid #dee2e6; padding-right: 10px; margin-right: 2px; }

        .footer-info { font-size: 15px; color: #999; display: flex; justify-content: space-between; border-top: 1px solid #f8f9fa; padding-top: 8px; }
        .btn-register { position: fixed; bottom: 25px; right: 20px; background-color: #007bff; color: white; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; box-shadow: 0 4px 12px rgba(0,123,255,0.4); border: none; z-index: 999; cursor: pointer; }
    </style>
</head>
<body>

<div id="app">
    <div class="header">
        <h2>공지사항 관리</h2>
        <div class="date-filter">
            <span style="font-size:12px; color:#666;">조회기간</span>
            <input type="date" v-model="startDate">
            <span>~</span>
            <input type="date" v-model="endDate">
        </div>
        <div class="header-stats">
            <div class="stat-item total" :class="{active: currentFilter === 'all'}" @click="currentFilter = 'all'">전체 {{ filteredByDate.length }}</div>
            <div class="stat-item ing" :class="{active: currentFilter === '공지'}" @click="currentFilter = '공지'">공지 {{ counts.ing }}</div>
            <div class="stat-item done" :class="{active: currentFilter === '완료'}" @click="currentFilter = '완료'">완료 {{ counts.done }}</div>
            <div class="stat-item drop" :class="{active: currentFilter === '폐기'}" @click="currentFilter = '폐기'">폐기 {{ counts.drop }}</div>
        </div>
    </div>

    <div class="container">
        <div v-for="item in finalFilteredList" :key="item.id" class="card" @click="goToDetail(item.id)">
            <div class="card-header">
                <span style="font-size:15px; color:#ccc;">No. {{ item.id }}</span>
                <span style="font-size:15px" :class="['status', item.status]">{{ item.status }}</span>
            </div>
            <div class="title">{{ item.title }}</div>
            <div class="content">{{ item.content }}</div>
            
            <div class="receiver-stats">
                <div class="stat-badge total-box">
                    <span class="stat-label">대상</span>
                    <span class="stat-value">{{ item.totalCount }}</span>
                </div>
                <div class="stat-badge">
                    <span class="stat-label">읽음</span>
                    <span class="stat-value">{{ item.readCount }}</span>
                </div>
                <div class="stat-badge">
                    <span class="stat-label">참여</span>
                    <span class="stat-value" style="color: #1976d2;">{{ item.joinCount }}</span>
                </div>
                <div class="stat-badge">
                    <span class="stat-label">해결</span>
                    <span class="stat-value" style="color: #388e3c;">{{ item.solveCount }}</span>
                </div>
            </div>

            <div class="footer-info">
                <span>등록자: {{ item.author }}</span>
                <span>{{ item.date }}</span>
            </div>
        </div>

        <div v-if="finalFilteredList.length === 0" style="text-align: center; color: #bbb; margin-top: 60px; font-size: 14px;">
            조건에 맞는 공지사항이 없습니다.
        </div>
    </div>

    <button class="btn-register" @click="goToDetail()">+</button>
</div>

<script>
    const { createApp, ref, computed, onMounted } = Vue;

    createApp({
        setup() {
            const noticeList = ref(<?php echo json_encode($notices, JSON_UNESCAPED_UNICODE); ?>);
            
            const today = new Date().toISOString().split('T')[0];
            const startDate = ref('');
            const endDate = ref(today);
            const currentFilter = ref('all');

            onMounted(() => {
                const d = new Date();
                d.setMonth(d.getMonth() - 1);
                startDate.value = d.toISOString().split('T')[0];
            });

            const filteredByDate = computed(() => {
                return noticeList.value.filter(item => {
                    return item.date >= startDate.value && item.date <= endDate.value;
                });
            });

            const counts = computed(() => ({
                ing: filteredByDate.value.filter(i => i.status === '공지').length,
                done: filteredByDate.value.filter(i => i.status === '완료').length,
                drop: filteredByDate.value.filter(i => i.status === '폐기').length
            }));

            const finalFilteredList = computed(() => {
                if (currentFilter.value === 'all') return filteredByDate.value;
                return filteredByDate.value.filter(item => item.status === currentFilter.value);
            });

            const goToDetail = (id) => {
                window.location.href = id ? `noti_mng_detail.php?id=${id}` : 'noti_mng_reg.php';
            };

            return { 
                startDate, endDate, currentFilter, 
                counts, filteredByDate, finalFilteredList, goToDetail 
            };
        }
    }).mount('#app');
</script>

</body>
</html>