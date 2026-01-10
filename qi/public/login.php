<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

// 이미 로그인된 경우 index.php로 이동
/*if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}*/

$error_msg = "";

// 폼이 제출되었을 때 (POST 방식)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once ROOT_PATH . '/src/db_connect.php';

    $user_id  = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // 1. 사용자 조회 (ID 기준)
        $stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        

        // 2. 검증 (비밀번호는 실제 운영 시 password_verify() 사용 권장)
        if ($user && $password === $user['PASSWORD']) {
            // 세션 데이터 저장
            $_SESSION['user_id']   = $user['USER_ID'];
            $_SESSION['user_name'] = $user['USER_NAME'];
            $_SESSION['role']      = $user['ROLE']; // MANAGER 또는 USER
            
            // 역할에 따른 이동
            if ($user['ROLE'] === 'MANAGER') {
                header("Location: /qi/public/noti_summary.php");
            } else {
                header("Location: /qi/public/recv_noti_summary.php");
            }
            exit;
        } else {
            $error_msg = "아이디 또는 비밀번호가 일치하지 않습니다.";
        }
    } catch (PDOException $e) {
        $error_msg = "로그인 처리 중 오류가 발생했습니다.";
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/qi/img/sch_qi_b_512.png"/> 
    <title>진지(진료지원간호팀)ONE TEAM - 로그인</title>
    <style>
        /* 기존 작성하신 스타일 유지 */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            width: 90%;
            max-width: 400px;
            background: #ffffff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        button:active {
            background-color: #0056b3;
        }

        .error-msg {
            color: #ff4d4d;
            font-size: 14px;
            text-align: center;
            margin-top: 15px;
            /* PHP 에러 메시지가 있을 때만 노출 */
            display: <?= $error_msg ? 'block' : 'none' ?>;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h2>LOGIN</h2>
    <form id="loginForm" method="POST" action="login.php">
        <div class="input-group">
            <input type="text" name="user_id" placeholder="아이디를 입력하세요" required value="admin">
        </div>
        <div class="input-group">
            <input type="password" name="password" placeholder="비밀번호를 입력하세요" required value="1">
        </div>
        <button type="submit">로그인</button>
        <p id="errorMessage" class="error-msg"><?= htmlspecialchars($error_msg) ?></p>
    </form>
</div>

</body>
</html>