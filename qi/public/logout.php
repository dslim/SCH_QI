<?php
session_start();

// 1. 모든 세션 변수 해제
$_SESSION = array();

// 2. 세션 쿠키를 삭제하고 싶다면 (권장 사항)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. 최종적으로 세션 파기
session_destroy();

// 4. 로그인 페이지 또는 메인 페이지로 리다이렉트
header("Location: login.php");
exit;
?>