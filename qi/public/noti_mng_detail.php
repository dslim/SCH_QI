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

if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT NOTICE_ID as id, TITLE as title, CONTENT as content, STATUS as status FROM NOTICES WHERE NOTICE_ID = ?");
        $stmt->execute([$id]);
        $noticeData = $stmt->fetch(PDO::FETCH_ASSOC);
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
        /* [v-cloak] 추가: Vue 로딩 전 {{ }} 숨김 */
        [v-cloak] { display: none; }
        body { font-family: -apple-system, sans-serif; background-color: #f5f7fa; margin: 0; padding: 0; }
        .header { background-color: #ffffff; padding: 15px; border-bottom: 1px solid #ddd; display: flex; align-items: center; position: sticky; top: 0; z-index: 100; }
        .back-btn { font-size: 20px; background: none; border: none; cursor: pointer; margin-right: 10px; }
        h2 { margin: 0; font-size: 1.1rem; flex-grow: 1; text-align: center; margin-right: 30px; }
        .container { padding: 20px; padding-bottom: 40px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; font-weight: bold; color: #555; margin-bottom: 8px; }
        input[type="text"], select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 15px; outline: none; }
        input:focus, textarea:focus { border-color: #007bff; }
        textarea { height: 180px; resize: none; line-height: 1.5; }
        .btn-group { display: flex; flex-direction: column; gap: 10px; margin-top: 30px; }
        .btn { width: 100%; padding: 15px; border-radius: 8px; border: none; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:disabled { background-color: #ccc; cursor: not-allowed; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-outline { background-color: white; color: #007bff; border: 1px solid #007bff; }
        input:disabled { background-color: #eee; color: #777; }
    </style>
</head>
<body>

<div id="app" v-cloak>
    <div class="header">
        <button class="back-btn" @click="goBack">←</button>
        <h2>공지사항 {{ isEdit ? '상세 수정' : '신규 등록' }}</h2>
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
            <label>상태 설정</label>
            <select v-model="form.status">
                <option value="1">공지(진행)</option>
                <option value="3">완료</option>
                <option value="9">폐기</option>
            </select>
        </div>

        <div class="btn-group">
            <button class="btn btn-outline" @click="goToReceiver">
                {{ isEdit ? '수신인 읽음 현황 확인' : '수신인 대상 설정' }}
            </button>
            <button class="btn btn-primary" @click="saveNotice" :disabled="loading">
                {{ loading ? '처리 중...' : (isEdit ? '수정 내용 저장하기' : '새 공지 등록하기') }}
            </button>
            <button class="btn btn-secondary" @click="goBack">취소하고 돌아가기</button>
        </div>
    </div>
</div>

<script>
    const { createApp, ref } = Vue;

    createApp({
        setup() {
            // PHP 데이터를 JSON으로 안전하게 변환
            const rawData = <?php echo json_encode($noticeData); ?>;
            const phpId = <?php echo json_encode($id); ?>;

            const isEdit = ref(phpId !== null);
            const loading = ref(false);
            
            const form = ref({
                id: rawData ? rawData.id : '',
                title: rawData ? rawData.title : '',
                content: rawData ? rawData.content : '',
                status: rawData ? rawData.status : '1'
            });

            const goToReceiver = () => {
                if (!form.value.id) {
                    alert('공지사항을 저장한 후 수신인 설정이 가능합니다.');
                    return;
                }
                const page = isEdit.value ? 'receiver_state.php' : 'set_receiver.php';
                window.location.href = `${page}?id=${form.value.id}`;
            };

            const saveNotice = async () => {
                if (!form.value.title.trim()) { alert('제목을 입력해주세요.'); return; }
                if (!form.value.content.trim()) { alert('내용을 입력해주세요.'); return; }
                
                if (!confirm(isEdit.value ? '수정하시겠습니까?' : '등록하시겠습니까?')) return;

                loading.value = true;
                
                const formData = new FormData();
                formData.append('id', form.value.id);
                formData.append('title', form.value.title);
                formData.append('content', form.value.content);
                formData.append('status', form.value.status);
                
                try {
                    const response = await fetch('save_notice_process.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        window.location.href = 'noti_mng_list.php';
                    } else {
                        alert('저장 실패: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('서버와 통신 중 오류가 발생했습니다.');
                } finally {
                    loading.value = false;
                }
            };

            const goBack = () => window.history.back();

            return {
                isEdit, form, loading,
                goToReceiver, saveNotice, goBack
            };
        }
    }).mount('#app');
</script>

</body>
</html>