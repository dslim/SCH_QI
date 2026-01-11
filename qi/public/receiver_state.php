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
$id = isset($_GET['id']) ? $_GET['id'] : null;

try {
// 2. 파라미터 받기 (URL의 ?notice_id=123 값을 가져옴)
$notice_id = $_GET['id'] ?? 0;

// 3. 데이터 가져오기
// Vue에서 사용하는 이름(name, dept, status, updateTime)으로 별칭(AS)을 설정했습니다.
$sql = "SELECT 
            B.USER_NAME as name, 
            B.DEPARTMENT as dept, 
            A.READ_STATUS as status, 
            IFNULL(A.UPDATE_DATE, '-') as updateTime 
        FROM NOTICE_RECEIVERS A
        JOIN USERS B ON A.USER_ID = B.USER_ID 
        WHERE A.NOTICE_ID = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$notice_id]);
$receivers_data = $stmt->fetchAll();

// DB의 상태값(숫자 등)을 UI에 표시될 한글 텍스트로 변환 (필요한 경우)
foreach ($receivers_data as &$row) {
    // 예: DB에 1, 2로 저장되어 있다면 변환
    if ($row['status'] == '1') $row['status'] = '미확인';
    else if ($row['status'] == '2') $row['status'] = '확인';
}

$json_data = json_encode($receivers_data, JSON_UNESCAPED_UNICODE);


} catch (\PDOException $e) {
    die("DB 연결 실패: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>부서별 수신 현황</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        body { font-family: -apple-system, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; }
        .back-btn { font-size: 20px; background: none; border: none; cursor: pointer; margin-right: 10px; }
        h2 { margin: 0; font-size: 1.1rem; flex-grow: 1; text-align: center; margin-right: 30px; }
        .container { padding: 15px; }
        .summary-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .summary-box { background: #fff; padding: 15px 10px; border-radius: 12px; text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .summary-box .val { display: block; font-size: 1.2rem; font-weight: bold; margin-bottom: 5px; }
        .summary-box .lab { font-size: 11px; color: #888; }
        .dept-group { margin-bottom: 25px; }
        .dept-title-bar { display: flex; justify-content: space-between; align-items: flex-end; padding: 5px; border-bottom: 2px solid #333; margin-bottom: 10px; }
        .dept-name { font-size: 16px; font-weight: bold; color: #333; }
        .dept-stat { font-size: 12px; color: #666; }
        .receiver-table { background: #fff; border-radius: 10px; overflow: hidden; width: 100%; border-collapse: collapse; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .receiver-table th, .receiver-table td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #f0f2f5; font-size: 14px; }
        .receiver-table th { background: #fafafa; color: #888; font-weight: normal; font-size: 12px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-align: center; min-width: 45px; }
        .badge-unread { background: #ffe3e3; color: #ff4d4d; }
        .badge-reading { background: #e3f2fd; color: #1976d2; }
        .badge-solved { background: #e8f5e9; color: #388e3c; }
        .time-text { font-size: 11px; color: #bbb; }
    </style>
</head>
<body>

<div id="app">
    <div class="header">
        <button class="back-btn" @click="goBack">←</button>
        <h2>공지 수신 현황</h2>
    </div>

    <div class="container">
        <div class="summary-container">
            <div class="summary-box">
                <span class="val">{{ totalCount }}</span>
                <span class="lab">전체 대상</span>
            </div>
            <div class="summary-box" style="color: #1976d2;">
                <span class="val">{{ noreadCount }}</span>
                <span class="lab">미확인</span>
            </div>
            <div class="summary-box" style="color: #388e3c;">
                <span class="val">{{ solvedCount }}</span>
                <span class="lab">확인</span>
            </div>
        </div>

        <div v-if="receivers.length === 0" style="text-align:center; padding: 50px; color: #999;">
            데이터가 없습니다.
        </div>

        <div v-for="(members, dept) in groupedData" :key="dept" class="dept-group">
            <div class="dept-title-bar">
                <span class="dept-name">{{ dept }}</span>
                <span class="dept-stat">해결 {{ getDeptSolved(members) }} / 전체 {{ members.length }}</span>
            </div>
            
            <table class="receiver-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">성명</th>
                        <th style="width: 35%;">상태</th>
                        <th style="width: 35%;">확인일시</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in members" :key="user.name">
                        <td><strong>{{ user.name }}</strong></td>
                        <td>
                            <span :class="['badge', getBadgeClass(user.status)]">
                                {{ user.status }}
                            </span>
                        </td>
                        <td class="time-text">{{ user.updateTime }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const { createApp, ref, computed } = Vue;

    createApp({
        setup() {
            const receivers = ref(<?php echo $json_data; ?>);

            const groupedData = computed(() => {
                return receivers.value.reduce((acc, cur) => {
                    if (!acc[cur.dept]) acc[cur.dept] = [];
                    acc[cur.dept].push(cur);
                    return acc;
                }, {});
            });

            const totalCount = computed(() => receivers.value.length);
            const noreadCount = computed(() => receivers.value.filter(r => r.status === '미확인').length);
            const solvedCount = computed(() => receivers.value.filter(r => r.status === '확인').length);

            const getDeptSolved = (members) => members.filter(m => m.status === '확인').length;

            const getBadgeClass = (status) => {
                if (status === '미확인') return 'badge-reading';
                if (status === '확인') return 'badge-solved';
                return '';
            };

            const goBack = () => window.history.back();

            return {
                receivers, groupedData, 
                totalCount, noreadCount, solvedCount,
                getDeptSolved, getBadgeClass, goBack
            };
        }
    }).mount('#app');
</script>

</body>
</html>
