<?php
// 데이터베이스 접속 정보
$host = 'localhost';
$db   = 'blab';
$user = 'blab';
$pass = 'tjdrhd91!';
$charset = 'utf8mb4';

// DSN (Data Source Name) 설정
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// 접속 옵션 설정
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // 에러 발생 시 예외를 던짐
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // 결과를 연관 배열로 반환
    PDO::ATTR_EMULATE_PREPARES   => false,                  // 실제 Prepared Statements 사용
];

try {
    // PDO 인스턴스 생성 (접속 시도)
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 접속 성공 확인용 (실제 서비스 시 삭제)
    //echo "데이터베이스 연결 성공!"; 

} catch (\PDOException $e) {
    // 접속 실패 시 에러 메시지 출력
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
    
    
}
?>