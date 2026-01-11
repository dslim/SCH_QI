<?php
// get_user_json.php
header('Content-Type: application/json; charset=utf-8');
session_start();

define('ROOT_PATH', dirname(__DIR__));

// 로그인 체크 (보안)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

try {
    $sql = "SELECT DEPARTMENT, USER_ID, USER_NAME 
            FROM USERS 
            WHERE ACTIVE_YN = 'Y'
            ORDER BY DEPARTMENT ASC, USER_NAME ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    // 데이터를 부서별로 그룹화
    foreach ($users as $user) {
        $dept = $user['DEPARTMENT'] ?? '소속없음';
        
        // 해당 부서 키가 없으면 배열 초기화
        if (!isset($result[$dept])) {
            $result[$dept] = [];
        }

        // Vue.js에서 기대하는 {id, name} 구조로 푸시
        $result[$dept][] = [
            'id'   => $user['USER_ID'],
            'name' => $user['USER_NAME']
        ];
    }

    // 결과 출력
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // 에러 발생 시 500 에러와 메시지 출력
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}