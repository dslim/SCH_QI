<?php
// 1. 서버 사이드 데이터 준비 (실제로는 DB에서 가져오는 쿼리가 위치합니다)
// 예시 데이터 구조
$host = 'localhost';
$dbname = 'your_database_name';
$username = 'your_db_user';
$password = 'your_db_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. 테이블에서 부서와 이름 조회 (예시 테이블명: users)
    // 부서별로 정렬하여 가져오면 데이터 처리가 쉽습니다.
    $stmt = $pdo->query("SELECT DEPARTMENT, USER_NAME FROM USERS ORDER BY DEPARTMENT ASC, USER_NAME ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Vue.js가 사용하기 편한 구조로 데이터 가공
    $departmentData = [];
    foreach ($rows as $row) {
        $dept = $row['DEPARTMENT'];
        $name = $row['USER_NAME'];
        
        if (!isset($departmentData[$dept])) {
            $departmentData[$dept] = [];
        }
        $departmentData[$dept][] = $name;
    }
// JSON으로 변환
    $jsonData = json_encode($departmentData, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    die("DB 연결 실패: " . $e->getMessage());
}

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>수신인 설정</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        body { font-family: -apple-system, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; }
        .back-btn { font-size: 20px; background: none; border: none; cursor: pointer; margin-right: 10px; }
        h2 { margin: 0; font-size: 1.1rem; flex-grow: 1; text-align: center; margin-right: 30px; }
        
        .container { padding: 15px; padding-bottom: 80px; }
        
        /* 부서별 그룹 스타일 */
        .dept-group { background: #fff; border-radius: 10px; margin-bottom: 15px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .dept-header { padding: 15px; background: #f8f9fa; display: flex; align-items: center; border-bottom: 1px solid #eee; cursor: pointer; }
        .dept-title { font-weight: bold; flex-grow: 1; font-size: 15px; }
        
        /* 사용자 리스트 (그리드 레이아웃) */
        .user-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; padding: 15px; }
        .user-item { display: flex; align-items: center; gap: 5px; font-size: 14px; }
        .user-item label { cursor: pointer; }
        
        /* 체크박스 커스텀 */
        input[type="checkbox"] { width: 18px; height: 18px; accent-color: #007bff; cursor: pointer; }

        /* 하단 고정 버튼 */
        .footer-actions { position: fixed; bottom: 0; left: 0; right: 0; padding: 15px; background: #fff; border-top: 1px solid #ddd; display: flex; gap: 10px; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
        .btn { flex: 1; padding: 15px; border-radius: 8px; border: none; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn-confirm { background-color: #007bff; color: white; }
        .btn-cancel { background-color: #6c757d; color: white; }

        .count-badge { background: #007bff; color: #fff; font-size: 12px; padding: 2px 8px; border-radius: 10px; margin-left: 5px; }
    </style>
</head>
<body>

<div id="app">
    <div class="header">
        <button class="back-btn" @click="goBack">←</button>
        <h2>수신인 설정 <span class="count-badge" v-if="selectedUsers.length > 0">{{ selectedUsers.length }}</span></h2>
    </div>

    <div class="container">
        <div v-for="(users, dept) in departmentData" :key="dept" class="dept-group">
            <div class="dept-header" @click.self="triggerCheckbox(dept)">
                <input type="checkbox" :id="dept" @change="toggleDept(dept, $event)" :checked="isAllSelected(dept)">
                <label :for="dept" class="dept-title" style="margin-left: 10px;">{{ dept }}</label>
                <span style="font-size: 12px; color: #888;">{{ users.length }}명</span>
            </div>
            
            <div class="user-grid">
                <div v-for="user in users" :key="user" class="user-item">
                    <input type="checkbox" :id="user" :value="user" v-model="selectedUsers">
                    <label :for="user">{{ user }}</label>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-actions">
        <button class="btn btn-cancel" @click="goBack">취소</button>
        <button class="btn btn-confirm" @click="confirmSelection">선택 완료</button>
    </div>
</div>

<script>
    const { createApp, ref, onMounted } = Vue;

    createApp({
        setup() {
            // PHP에서 전달된 데이터를 JS 변수로 할당
            const departmentData = ref(<?php echo $jsonData; ?>);
            const selectedUsers = ref([]);

            // 초기 로드 시 기존 선택된 데이터가 있다면 불러오기 (선택사항)
            onMounted(() => {
                const saved = localStorage.getItem('temp_selected_users');
                if (saved) {
                    selectedUsers.value = JSON.parse(saved);
                }
            });

            // 부서 전체 선택/해제
            const toggleDept = (dept, event) => {
                const users = departmentData.value[dept];
                if (event.target.checked) {
                    users.forEach(user => {
                        if (!selectedUsers.value.includes(user)) {
                            selectedUsers.value.push(user);
                        }
                    });
                } else {
                    selectedUsers.value = selectedUsers.value.filter(user => !users.includes(user));
                }
            };

            // 부서 헤더 클릭 시 체크박스 트리거 보조
            const triggerCheckbox = (dept) => {
                const el = document.getElementById(dept);
                if (el) el.click();
            };

            // 해당 부서가 모두 선택되었는지 확인
            const isAllSelected = (dept) => {
                const users = departmentData.value[dept];
                return users.every(user => selectedUsers.value.includes(user));
            };

            const confirmSelection = () => {
                if (selectedUsers.value.length === 0) {
                    alert('수신인을 한 명 이상 선택해주세요.');
                    return;
                }
                
                // 선택된 데이터를 로컬 스토리지에 저장하여 부모 페이지에서 참조하게 함
                localStorage.setItem('selected_receivers', JSON.stringify(selectedUsers.value));
                
                alert(`${selectedUsers.value.length}명이 선택되었습니다.`);
                window.history.back(); 
            };

            const goBack = () => {
                if(confirm('선택을 취소하고 돌아가시겠습니까?')) {
                    window.history.back();
                }
            };

            return {
                departmentData, selectedUsers,
                toggleDept, isAllSelected, confirmSelection, goBack, triggerCheckbox
            };
        }
    }).mount('#app');
</script>

</body>
</html>