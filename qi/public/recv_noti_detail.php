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

// 2. [POST 요청 처리] 상태 업데이트 및 전체 완료 체크 로직
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatusText = $_POST['status_text'] ?? '';
    
    // 화면 텍스트를 DB 상태 코드(1: 미확인, 2: 확인)로 매핑
    $statusMap = ['미확인' => '1', '확인' => '2'];
    $statusCode = $statusMap[$newStatusText] ?? null;

    if ($statusCode) {
        try {
            // 트랜잭션 시작
            $pdo->beginTransaction();

            // [A] 수신자 개인의 상태 업데이트
            $updateSql = "UPDATE NOTICE_RECEIVERS 
                          SET READ_STATUS = ?, UPDATE_DATE = NOW() 
                          WHERE NOTICE_ID = ? AND USER_ID = ?";
            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute([$statusCode, $noticeId, $userId]);

            // [B] 모든 수신자가 '확인(2)' 상태인지 체크
            // 아직 '미확인(1)'인 레코드가 있는지 확인
            $checkSql = "SELECT COUNT(*) FROM NOTICE_RECEIVERS WHERE NOTICE_ID = ? AND READ_STATUS != '2'";
            $stmtCheck = $pdo->prepare($checkSql);
            $stmtCheck->execute([$noticeId]);
            $remainingCount = $stmtCheck->fetchColumn();

            // [C] 미확인 인원이 0명이라면 공지사항 본문의 상태를 '완료(3)'로 변경
            if ($remainingCount == 0) {
                $finishSql = "UPDATE NOTICES SET STATUS = '3' WHERE NOTICE_ID = ?";
                $stmtFinish = $pdo->prepare($finishSql);
                $stmtFinish->execute([$noticeId]);
            }

            // 모든 쿼리가 성공하면 커밋
            $pdo->commit();
            
            header("Location: recv_noti_detail.php?id=" . $noticeId . "&success=1");
            exit;
        } catch (PDOException $e) {
            // 에러 발생 시 롤백
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            echo "<script>alert('처리 중 오류가 발생했습니다.'); history.back();</script>";
            exit;
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

    $statusMapping = ['1' => '미확인', '2' => '확인'];
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
        .opt-btn.disabled { background: #f8f9fa; color: #eee; border-color: #eee; cursor: not-allowed; text-decoration: line-through; }
        .bottom-actions { position: fixed; bottom: 0; left: 0; right: 0; padding: 15px 20px; background: #fff; border-top: 1px solid #eee; }
        .btn-save { width: 100%; padding: 15px; border-radius: 8px; border: none; font-size: 16px; font-weight: bold; cursor: pointer; background-color: #007bff; color: white; }
        .btn-save:disabled { background-color: #ccc; cursor: not-allowed; }
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-bottom: 10px; }
        .status-unread { background: #ffe3e3; color: #ff4d4d; }
        .status-reading { background: #e3f2fd; color: #1976d2; }
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
                    <button v-for="step in ['미확인', '확인']" 
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
                :disabled="noti.myStatus === tempStatus || noti.myStatus === '확인'" 
                @click="confirmSave">
            {{ noti.myStatus === '확인' ? '확인 완료된 공지입니다' : '상태 저장하기' }}
        </button>
    </div>
</div>

<script>
    const { createApp, ref } = Vue;

    createApp({
        setup() {
            const noti = ref(<?php echo json_encode($notiData, JSON_UNESCAPED_UNICODE); ?>);
            const tempStatus = ref(noti.value.myStatus);
            const statusWeight = { '미확인': 1, '확인': 2 };

            const isStepDisabled = (stepName) => {
                return statusWeight[stepName] < statusWeight[noti.value.myStatus];
            };

            const getStatusClass = (status) => {
                const map = { '미확인': 'status-unread', '확인': 'status-reading' };
                return map[status];
            };

            const getOptionActiveClass = (step) => {
                if (tempStatus.value !== step) return '';
                const map = { '미확인': 'active-unread', '확인': 'active-reading' };
                return map[step];
            };

            const confirmSave = () => {
                if (confirm(`상태를 [${tempStatus.value}]으로 저장하시겠습니까?\n저장 후에는 이전 단계로 되돌릴 수 없습니다.`)) {
                    document.getElementById('saveForm').submit();
                }
            };

            const goBack = () => {
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