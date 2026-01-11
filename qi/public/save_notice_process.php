<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

// 응답을 JSON으로 설정
header('Content-Type: application/json');

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

try {
    // 2. POST 데이터 수신
    $id        = $_POST['id'] ?? null;
    $title     = $_POST['title'] ?? '';
    $content   = $_POST['content'] ?? '';
    $status    = $_POST['status'] ?? '1';
    $userId    = $_SESSION['user_id']; 

    // 수신인 데이터 수신
    $receiversRaw = $_POST['receivers'] ?? '[]';
    $receivers    = json_decode($receiversRaw, true);

    if (empty($title) || empty($content)) {
        throw new Exception('제목과 내용을 입력해주세요.');
    }

    $pdo->beginTransaction(); // 트랜잭션 시작

    if (!empty($id)) {
        // --- [Case A] 기존 공지 수정 ---
        $sql = "UPDATE NOTICES 
                SET TITLE = ?, CONTENT = ?, STATUS = ?, UPDATE_DATE = NOW(), UPDATE_USER_ID = ? 
                WHERE NOTICE_ID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $status, $userId, $id]);
        $noticeId = $id;
    } else {
        // --- [Case B] 신규 공지 등록 (N + 타임스탬프 ID 생성) ---
        // SQL에서 직접 ID 생성
        $sqlGenId = "SELECT DATE_FORMAT(NOW(), 'N%Y%m%d%H%i%s') AS new_id";
        $stmtGen = $pdo->query($sqlGenId);
        $rowId = $stmtGen->fetch(PDO::FETCH_ASSOC);
        $noticeId = $rowId['new_id'];

        $sql = "INSERT INTO NOTICES (NOTICE_ID, TITLE, CONTENT, STATUS, REG_USER_ID, REG_DATE) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$noticeId, $title, $content, $status, $userId]);
    }

    // --- [STEP 2] 수신인 목록 저장 (NOTICE_RECEIVERS 테이블) ---
    // 기존 수신인 정보 삭제
    $deleteSql = "DELETE FROM NOTICE_RECEIVERS WHERE NOTICE_ID = ?";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute([$noticeId]);

    // 새로운 수신인 목록 인서트
    if (!empty($receivers) && is_array($receivers)) {
        // USER_ID 컬럼에 수신인 이름(또는 ID)을 저장
        $insertReceiverSql = "INSERT INTO NOTICE_RECEIVERS (NOTICE_ID, USER_ID, READ_STATUS) 
                              VALUES (?, ?, '1')";
        $receiverStmt = $pdo->prepare($insertReceiverSql);

        foreach ($receivers as $targetUser) {
            if (!empty(trim($targetUser))) {
                // $targetUser는 프론트에서 넘어온 수신인 이름 혹은 ID
                $receiverStmt->execute([$noticeId, $targetUser]);
            }
        }
    }
    
    $pdo->commit(); 
    echo json_encode(['success' => true, 'message' => '성공적으로 저장되었습니다.', 'id' => $noticeId]);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => '오류 발생: ' . $e->getMessage()]);
}