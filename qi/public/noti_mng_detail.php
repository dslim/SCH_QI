<?php
session_start();
define('ROOT_PATH', dirname(__DIR__));

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once ROOT_PATH . '/src/db_connect.php';

$id = isset($_GET['id']) ? $_GET['id'] : null;
$noticeData = null;
$savedReceivers = []; 

if ($id) {
    try {
        // 1. 공지사항 본문 조회
        $stmt = $pdo->prepare("SELECT NOTICE_ID as id, TITLE as title, CONTENT as content, STATUS as status FROM NOTICES WHERE NOTICE_ID = ?");
        $stmt->execute([$id]);
        $noticeData = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. 기존 수신인 목록 조회 (USER_ID 리스트)
        $stmtRec = $pdo->prepare("SELECT B.USER_ID, B.USER_NAME AS USER_NAME FROM NOTICE_RECEIVERS A, USERS B WHERE A.USER_ID=B.USER_ID AND A.NOTICE_ID = ?");
        $stmtRec->execute([$id]);
        $savedReceivers = $stmtRec->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>공지사항 상세/등록</title>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <style>
        [v-cloak] { display: none; }
        body { font-family: -apple-system, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; }
        .back-btn { font-size: 20px; background: none; border: none; cursor: pointer; margin-right: 10px; }
        h2 { margin: 0; font-size: 1.1rem; flex-grow: 1; text-align: center; margin-right: 30px; }
        .container { padding: 20px; padding-bottom: 40px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: bold; color: #555; margin-bottom: 8px; }
        input[type="text"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 15px; outline: none; }
        textarea { height: 180px; resize: none; line-height: 1.5; }
        
        /* 수신인 목록 스타일 */
        .receiver-box { background: #fff; padding: 12px; border: 1px dashed #007bff; border-radius: 8px; font-size: 14px; margin-top: 5px; transition: all 0.2s; }
        
        /* 수신현황 링크를 위한 스타일 (수정모드일 때) */
        .receiver-box.clickable { cursor: pointer; border-style: solid; position: relative; }
        .receiver-box.clickable:hover { background-color: #eef6ff; border-color: #0056b3; }
        .receiver-box.clickable:after { content: ''; position: absolute; top: 10px; right: 10px; font-size: 11px; color: #007bff; font-weight: bold; }

        .dept-tag-group { margin-bottom: 8px; line-height: 1.6; }
        .dept-label { font-weight: bold; color: #333; background: #e7f1ff; padding: 2px 6px; border-radius: 4px; margin-right: 6px; font-size: 12px; }
        .user-names { color: #007bff; }

        .btn-group { display: flex; flex-direction: column; gap: 10px; margin-top: 30px; }
        .btn { width: 100%; padding: 15px; border-radius: 8px; border: none; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-outline { background-color: white; color: #007bff; border: 1px solid #007bff; }

        /* 모달 스타일 */
        .modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:200; display:flex; align-items:center; justify-content:center; }
        .modal-content { background:#fff; width:90%; max-width:500px; height:80%; border-radius:15px; display:flex; flex-direction:column; overflow:hidden; }
        .modal-body { flex:1; overflow-y:auto; padding:15px; }
        .dept-group { border:1px solid #eee; border-radius:8px; margin-bottom:10px; }
        .dept-header { background:#f8f9fa; padding:10px; font-weight:bold; font-size:14px; }
        .user-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; padding:10px; }
        .user-item { font-size:12px; display:flex; align-items:center; gap:3px; }
    </style>
</head>
<body>

<div id="app" v-cloak>
    <div class="header">
        <button class="back-btn" @click="goBack">←</button>
        <h2>{{ isEdit ? '' : '새로운 ' }}공지사항 {{ isEdit ? '상세 수정' : '등록' }}</h2>
    </div>

    <div class="container">
        <div class="form-group" v-if="isEdit">
            <label>등록번호</label>
            <input type="text" v-model="form.id" disabled>
        </div>

        <div class="form-group">
            <label>제목</label>
            <input type="text" v-model="form.title" placeholder="제목을 입력하세요">
        </div>

        <div class="form-group">
            <label>내용</label>
            <textarea v-model="form.content" placeholder="공지 내용을 입력하세요"></textarea>
        </div>

        <div class="form-group">
            <label>수신 대상 ({{ selectedUsers.length }}명)</label>
            <div class="receiver-box" 
                 :class="{ 'clickable': isEdit }" 
                 @click="handleReceiverClick"
                 v-if="selectedUsers.length > 0">
                <div v-for="(names, dept) in groupedSelectedUsers" :key="dept" class="dept-tag-group">
                    <span class="dept-label">{{ dept }}</span>
                    <span class="user-names">{{ names.join(', ') }}</span>
                </div>
            </div>
            <div v-else @click="showModal = true" style="font-size: 13px; color: #999; cursor:pointer;">
                선택된 수신인이 없습니다. 클릭하여 설정하세요.
            </div>
        </div>
                
        <div class="form-group">
            <label>상태 설정</label>
            <select v-model="form.status">
                <option value="1">공지</option>
                <option value="3">완료</option>
                <option value="9">폐기</option>
            </select>
        </div>

        <div class="btn-group">
            <button class="btn btn-outline" @click="showModal = true">수신인 대상 설정</button>
            <button class="btn btn-primary" @click="saveNotice" :disabled="loading">
                {{ loading ? '처리 중...' : (isEdit ? '수정 내용 저장하기' : '새 공지 등록하기') }}
            </button>
            <button class="btn btn-secondary" @click="goBack">취소하고 돌아가기</button>
        </div>
    </div>

    <div class="modal-overlay" v-if="showModal">
        <div class="modal-content">
            <div class="header"><h2 style="margin-right:0">수신인 선택</h2></div>
            <div class="modal-body">
                <div v-for="(users, dept) in departmentData" :key="dept" class="dept-group">
                    <div class="dept-header">
                        <input type="checkbox" 
                               :id="'modal-'+dept" 
                               @change="toggleDept(dept, $event)" 
                               :checked="isAllSelected(dept)">
                        <label :for="'modal-'+dept"> {{ dept }}</label>
                    </div>
                    <div class="user-grid">
                        <div v-for="user in users" :key="user.id" class="user-item">
                            <input type="checkbox" 
                                   :id="'user-'+user.id" 
                                   :value="user.id" 
                                   v-model="selectedUsers">
                            <label :for="'user-'+user.id">{{ user.name }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div style="padding:15px;"><button class="btn btn-primary" @click="showModal = false">선택 완료</button></div>
        </div>
    </div>
</div>

<script>
    const { createApp, ref, computed, onMounted } = Vue;

    createApp({
        setup() {
            const rawData = <?php echo json_encode($noticeData); ?>;
            const paramId = <?php echo json_encode($id); ?>;
            const savedReceivers = <?php echo json_encode($savedReceivers); ?>; 

            const isEdit = ref(paramId !== null);
            const loading = ref(false);
            const showModal = ref(false);
            const departmentData = ref({}); 
            const selectedUsers = ref(savedReceivers || []); 

            const form = ref({
                id: rawData ? rawData.id : '',
                title: rawData ? rawData.title : '',
                content: rawData ? rawData.content : '',
                status: rawData ? rawData.status : '1'
            });

            const groupedSelectedUsers = computed(() => {
                const grouped = {};
                for (const [dept, users] of Object.entries(departmentData.value)) {
                    const matchedNames = users
                        .filter(u => selectedUsers.value.includes(u.id))
                        .map(u => u.name);
                    
                    if (matchedNames.length > 0) grouped[dept] = matchedNames;
                }
                return grouped;
            });

            const fetchUsers = async () => {
                try {
                    const resp = await fetch('get_user_json.php');
                    departmentData.value = await resp.json();
                } catch (e) { 
                    console.error('사용자 목록 로드 실패'); 
                }
            };

            onMounted(fetchUsers);

            // [추가 기능] 수신 대상 클릭 핸들러
            const handleReceiverClick = () => {
                if (isEdit.value && form.value.id) {
                    // 수정 모드인 경우 수신 현황 페이지로 이동
                    location.href = `receiver_state.php?id=${form.value.id}`;
                } else {
                    // 신규 등록이거나 ID가 없는 경우 모달 표시
                    showModal.value = true;
                }
            };

            const toggleDept = (dept, event) => {
                const usersInDept = departmentData.value[dept];
                const idsInDept = usersInDept.map(u => u.id);

                if (event.target.checked) {
                    idsInDept.forEach(id => {
                        if(!selectedUsers.value.includes(id)) selectedUsers.value.push(id);
                    });
                } else {
                    selectedUsers.value = selectedUsers.value.filter(id => !idsInDept.includes(id));
                }
            };

            const isAllSelected = (dept) => {
                const usersInDept = departmentData.value[dept];
                if (!usersInDept || usersInDept.length === 0) return false;
                return usersInDept.every(u => selectedUsers.value.includes(u.id));
            };

            const saveNotice = async () => {
                if (!form.value.title.trim() || !form.value.content.trim()) {
                    alert('제목과 내용을 입력해주세요.'); return;
                }
                if (selectedUsers.value.length === 0) {
                    alert('수신인을 최소 한 명 이상 선택해주세요.'); return;
                }
                if (!confirm('저장하시겠습니까?')) return;

                loading.value = true;
                const formData = new FormData();
                formData.append('id', form.value.id);
                formData.append('title', form.value.title);
                formData.append('content', form.value.content);
                formData.append('status', form.value.status);
                formData.append('receivers', JSON.stringify(selectedUsers.value));
                
                try {
                    const response = await fetch('save_notice_process.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        alert(result.message);
                        window.location.href = 'noti_mng_list.php';
                    } else { 
                        alert('에러: ' + result.message); 
                    }
                } catch (e) { 
                    alert('서버 통신 오류가 발생했습니다.'); 
                } finally { 
                    loading.value = false; 
                }
            };

            return { 
                isEdit, form, loading, showModal, departmentData, 
                selectedUsers, groupedSelectedUsers, toggleDept, 
                isAllSelected, saveNotice, 
                handleReceiverClick, // 리턴 추가
                goBack: () => window.history.back() 
            };
        }
    }).mount('#app');
</script>
</body>
</html>