<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '권한이 없습니다.']);
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

// 응답을 JSON으로 설정
header('Content-Type: application/json');

try {
    // 2. POST 데이터 수신
    $id      = $_POST['id'] ?? null;
    $title   = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $status  = $_POST['status'] ?? '1';
    $userId  = $_SESSION['user_id']; // 세션의 사용자 ID

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
        // --- [Case B] 신규 공지 등록 ---
        $sql = "INSERT INTO NOTICES (TITLE, CONTENT, STATUS, REG_USER_ID, REG_DATE) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $content, $status, $userId]);
        $noticeId = $pdo->lastInsertId(); // 생성된 ID 가져오기
    }
    
    $pdo->commit(); // 모든 작업 성공 시 커밋
    echo json_encode(['success' => true, 'message' => '저장되었습니다.', 'id' => $noticeId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // 오류 발생 시 롤백
    }
    echo json_encode(['success' => false, 'message' => '오류 발생: ' . $e->getMessage()]);
}