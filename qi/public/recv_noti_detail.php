<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

// 1. 로그인 체크 및 DB 연결
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

$userId = $_SESSION['user_id'];
$noticeId = $_GET['id'] ?? '';

if (!$noticeId) {
    echo "<script>alert('잘못된 접근입니다.'); history.back();</script>";
    exit;
}

// 2. [POST 요청 처리] 상태 업데이트 로직
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatusText = $_POST['status_text'] ?? '';
    
    // 화면 텍스트를 DB 상태 코드(1, 2, 3)로 매핑
    $statusMap = ['미확인' => '1', '참여중' => '2', '해결됨' => '3'];
    $statusCode = $statusMap[$newStatusText] ?? null;

    if ($statusCode) {
        try {
            // 현재 상태보다 낮은 단계로 업데이트하는지 확인하는 로직을 쿼리에 추가 가능
            $updateSql = "UPDATE NOTICE_RECEIVERS 
                          SET READ_STATUS = ?, UPDATE_DATE = NOW() 
                          WHERE NOTICE_ID = ? AND USER_ID = ?";
            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute([$statusCode, $noticeId, $userId]);
            
            // 성공 시 리다이렉트 (새로고침 방지)
            header("Location: recv_noti_detail.php?id=" . $noticeId . "&success=1");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
    }
}

// 3. 공지 상세 데이터 조회
try {
    $sql = "SELECT A.NOTICE_ID, A.TITLE, A.CONTENT, A.REG_USER_ID, A.REG_DATE, B.READ_STATUS 
            FROM NOTICES A
            INNER JOIN NOTICE_RECEIVERS B ON A.NOTICE_ID = B.NOTICE_ID 
            WHERE A.NOTICE_ID = ? AND B.USER_ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$noticeId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "<script>alert('데이터를 찾을 수 없습니다.'); history.back();</script>";
        exit;
    }

    // DB 상태 코드를 화면용 텍스트로 변환
    $statusMapping = ['1' => '미확인', '2' => '참여중', '3' => '해결됨'];
    $currentStatus = $statusMapping[$row['READ_STATUS']] ?? '미확인';

    $notiData = [
        'id'       => $row['NOTICE_ID'],
        'title'    => $row['TITLE'],
        'content'  => $row['CONTENT'],
        'author'   => $row['REG_USER_ID'],
        'date'     => date('Y-m-d', strtotime($row['REG_DATE'])),
        'myStatus' => $currentStatus
    ];

} catch (PDOException $e) {
    die("DB 오류: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공지 상세 보기</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        /* CSS 스타일은 동일하게 유지 (생략 가능) */
        body { font-family: -apple-system, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; }
        .back-btn { font-size: 20px; background: none; border: none; cursor: pointer; margin-right: 10px; }
        h2 { margin: 0; font-size: 1.1rem; flex-grow: 1; text-align: center; margin-right: 30px; }
        .container { padding: 20px; padding-bottom: 120px; }
        .info-section { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .title { font-size: 1.3rem; font-weight: bold; color: #333; margin-bottom: 10px; line-height: 1.4; }
        .meta-data { font-size: 13px; color: #888; display: flex; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .content-body { font-size: 15px; color: #444; line-height: 1.6; min-height: 150px; white-space: pre-wrap; margin-bottom: 25px; }
        .status-selector { margin-top: 25px; padding-top: 20px; border-top: 2px solid #f0f2f5; }
        .label { font-size: 14px; font-weight: bold; color: #555; margin-bottom: 10px; display: block; }
        .status-options { display: flex; gap: 10px; margin-top: 10px; }
        .opt-btn { flex: 1; padding: 12px 5px; border-radius: 8px; border: 1px solid #ddd; background: #fff; color: #ccc; font-size: 13px; font-weight: bold; cursor: pointer; text-align: center; }
        .opt-btn.active-unread { border-color: #ff4d4d; color: #ff4d4d; background: #fff5f5; }
        .opt-btn.active-reading { border-color: #1976d2; color: #1976d2; background: #e3f2fd; }
        .opt-btn.active-solved { border-color: #388e3c; color: #388e3c; background: #e8f5e9; }
        .opt-btn.disabled { background: #f8f9fa; color: #eee; border-color: #eee; cursor: not-allowed; text-decoration: line-through; }
        .bottom-actions { position: fixed; bottom: 0; left: 0; right: 0; padding: 15px 20px; background: #fff; border-top: 1px solid #eee; }
        .btn-save { width: 100%; padding: 15px; border-radius: 8px; border: none; font-size: 16px; font-weight: bold; cursor: pointer; background-color: #007bff; color: white; }
        .btn-save:disabled { background-color: #ccc; cursor: not-allowed; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-bottom: 10px; }
        .status-unread { background: #ffe3e3; color: #ff4d4d; }
        .status-reading { background: #e3f2fd; color: #1976d2; }
        .status-solved { background: #e8f5e9; color: #388e3c; }
    </style>
</head>
<body>

<div id="app">
    <form id="saveForm" method="POST" style="display:none;">
        <input type="hidden" name="status_text" :value="tempStatus">
    </form>

    <div class="header">
        <button class="back-btn" @click="goBack">←</button>
        <h2>공지 상세 내용</h2>
    </div>

    <div class="container" v-if="noti">
        <div class="info-section">
            <div :class="['status-badge', getStatusClass(noti.myStatus)]">
                현재 확정 상태: {{ noti.myStatus }}
            </div>

            <div class="title">{{ noti.title }}</div>
            <div class="meta-data">
                <span>보낸이: {{ noti.author }}</span>
                <span>{{ noti.date }}</span>
            </div>

            <div class="content-body">{{ noti.content }}</div>

            <div class="status-selector">
                <span class="label">나의 처리 상태 변경 (이전 단계 변경 불가)</span>
                <div class="status-options">
                    <button v-for="step in ['미확인', '참여중', '해결됨']" 
                            :key="step"
                            class="opt-btn" 
                            :class="[getOptionActiveClass(step), {'disabled': isStepDisabled(step)}]"
                            :disabled="isStepDisabled(step)"
                            @click="tempStatus = step">
                        {{ step }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-actions" v-if="noti">
        <button class="btn-save" 
                :disabled="noti.myStatus === tempStatus || noti.myStatus === '해결됨'" 
                @click="confirmSave">
            {{ noti.myStatus === '해결됨' ? '최종 완료된 공지입니다' : '상태 저장하기' }}
        </button>
    </div>
</div>

<script>
    const { createApp, ref } = Vue;

    createApp({
        setup() {
            // PHP에서 초기 데이터 로드
            const noti = ref(<?php echo json_encode($notiData, JSON_UNESCAPED_UNICODE); ?>);
            const tempStatus = ref(noti.value.myStatus);
            const statusWeight = { '미확인': 1, '참여중': 2, '해결됨': 3 };

            // 이전 단계 비활성화 로직
            const isStepDisabled = (stepName) => {
                return statusWeight[stepName] < statusWeight[noti.value.myStatus];
            };

            const getStatusClass = (status) => {
                const map = { '미확인': 'status-unread', '참여중': 'status-reading', '해결됨': 'status-solved' };
                return map[status];
            };

            const getOptionActiveClass = (step) => {
                if (tempStatus.value !== step) return '';
                const map = { '미확인': 'active-unread', '참여중': 'active-reading', '해결됨': 'active-solved' };
                return map[step];
            };

            const confirmSave = () => {
                if (confirm(`상태를 [${tempStatus.value}]으로 저장하시겠습니까?\n저장 후에는 이전 단계로 되돌릴 수 없습니다.`)) {
                    // 폼을 이용해 서버에 전송
                    document.getElementById('saveForm').submit();
                }
            };

            const goBack = () => {
                // 목록 페이지(list.php)로 돌아가거나 뒤로 가기
                location.href = 'recv_noti_list.php'; 
            };

            return { 
                noti, tempStatus, getStatusClass, 
                getOptionActiveClass, confirmSave, goBack, isStepDisabled 
            };
        }
    }).mount('#app');
</script>

<?php if (isset($_GET['success'])): ?>
<script>alert('상태가 성공적으로 업데이트되었습니다.');</script>
<?php endif; ?>

</body>
</html>