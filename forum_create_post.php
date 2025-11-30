<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

// --- BƯỚC 1: XỬ LÝ LOGIC (ĐĂNG BÀI) ---
if (isset($_SESSION['user']) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = (int)$_SESSION['user']['id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title !== '') {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, title, content) VALUES (?,?,?)");
        $stmt->bind_param("iss", $user_id, $title, $content);
        $stmt->execute();
        $post_id = $stmt->insert_id;

        // Upload files
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = "uploads/posts/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['files']['name'] as $i => $name) {
                $tmp = $_FILES['files']['tmp_name'][$i];
                $error = $_FILES['files']['error'][$i];
                if ($error !== UPLOAD_ERR_OK || !file_exists($tmp)) continue;

                $mime = mime_content_type($tmp);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!(strpos($mime, 'image/') === 0 || $mime === 'video/mp4')) continue;

                $newName = time() . "_" . rand(1000,9999) . "." . $ext;
                $target = $uploadDir . $newName;
                move_uploaded_file($tmp, $target);

                $type = strpos($mime, "video") === 0 ? "video" : "image";

                $stmtF = $conn->prepare("INSERT INTO post_files (post_id, file_path, file_type) VALUES (?,?,?)");
                $stmtF->bind_param("iss", $post_id, $target, $type);
                $stmtF->execute();
            }
        }

        header('Location: forum_list.php');
        exit;
    }
}

// HIỂN THỊ HTML SAU
include 'includes/header.php';

if (!isset($_SESSION['user'])) {
    echo '<div class="profile-container"><div class="profile-message">
            Vui lòng <a class="btn-login" href="login.php">đăng nhập</a> để đăng bài.
          </div></div>';
    include 'includes/footer.php';
    exit;
}

$prefillScore = (int)($_GET['score'] ?? 0);
$prefillTitle = $prefillScore ? "Mình vừa đạt {$prefillScore} điểm ở Tower Defense!" : "";
?>

<style>
/* ----- GIAO DIỆN EDITOR ----- */
.post-create {
    width: 700px;
    margin: 0px auto 25px auto;
    background: #fff;
    padding: 28px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0,0,0,.06);
    font-family: "Segoe UI", Tahoma, sans-serif;
    font-size: 15px;
}

.editor-box {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    border-radius: 10px;
    margin-bottom: 12px;
    overflow: hidden;
}

.editor-toolbar {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 8px;
    display: flex;
    gap: 8px;
    align-items: center;
    position: relative;
}

.tool-btn {
    background: none;
    border: none;
    padding: 6px 10px;
    cursor: pointer;
    border-radius: 6px;
    font-size: 14px;
    color: #475569;
}

.editor-area {
    min-height: 120px;
    padding: 12px 14px;
    outline: none;
}

/* Emoji panel tách ra ngoài hoàn toàn */
#emojiPanel {
    position: fixed;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 8px;
    border-radius: 8px;
    width: 220px;
    display: none;
    flex-wrap: wrap;
    gap: 6px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    z-index: 9999;
}
#emojiPanel span {
    font-size: 18px;
    cursor: pointer;
    padding: 6px;
}
#emojiPanel span:hover { background:#e2e8f0; }
/* Placeholder cho contenteditable đúng cách */
.editor-area[contenteditable="true"]:empty:before,
.editor-area[contenteditable="true"]:not(:focus):before {
    content: attr(placeholder);
    color: #94a3b8;
    pointer-events: none;
}

.editor-area[contenteditable="true"]:not(:focus):not(:empty):before {
    content: "";
}

</style>


<div style="width:700px; margin:0 auto 15px auto;">
    <a href="javascript:history.back()" style="font-size:2.2em; text-decoration:none;">←</a>
</div>

<div class="post-create">
    <h2>✍️ Đăng bài</h2>

    <form method="post" enctype="multipart/form-data">

        <!-- TIÊU ĐỀ -->
        <label class="pc-label">Tiêu đề bài viết:</label>
        <div class="editor-box">
            <div class="editor-toolbar">
                <button type="button" class="tool-btn" data-cmd="bold"><b>B</b></button>
                <button type="button" class="tool-btn" data-cmd="italic"><i>I</i></button>
                <button type="button" class="tool-btn" data-cmd="underline"><u>U</u></button>
                <button type="button" class="tool-btn emojiBtn" data-target="titleEditor">😊</button>
            </div>

            <div id="titleEditor" class="editor-area" contenteditable="true"
                 placeholder="Tiêu đề bài viết..."><?= htmlspecialchars($prefillTitle) ?></div>
        </div>
        <textarea name="title" id="title" hidden></textarea>


        <!-- NỘI DUNG -->
        <label class="pc-label">Nội dung bài viết:</label>
        <div class="editor-box">
            <div class="editor-toolbar">
                <button type="button" class="tool-btn" data-cmd="bold"><b>B</b></button>
                <button type="button" class="tool-btn" data-cmd="italic"><i>I</i></button>
                <button type="button" class="tool-btn" data-cmd="underline"><u>U</u></button>
                <button type="button" class="tool-btn emojiBtn" data-target="contentEditor">😊</button>
            </div>

            <div id="contentEditor" class="editor-area" contenteditable="true"
                 placeholder="Chia sẻ thêm chiến thuật, cảm nhận..."></div>
        </div>
        <textarea name="content" id="content" hidden></textarea>


        <!-- EMOJI PANEL -->
        <div id="emojiPanel"></div>


        <!-- FILES -->
        <label class="pc-label">Ảnh / Video:</label>
        <input type="file" name="files[]" multiple accept="image/*,video/mp4" class="pc-file">
        <div id="previewArea" class="pc-preview"></div>

        <div class="pc-actions">
            <button class="pc-btn" type="submit">Đăng bài</button>
        </div>
    </form>
</div>


<!-- ===================== JS ===================== -->
<script>
// -------- FILE PREVIEW --------
const fileInput = document.querySelector('input[name="files[]"]');
const preview = document.getElementById('previewArea');
let filesBuffer = [];
const MAX_SIZE = 40 * 1024 * 1024;

fileInput.addEventListener("change", () => {
    for (let f of fileInput.files) {
        if (f.size <= MAX_SIZE) filesBuffer.push(f);
    }
    fileInput.value = "";
    renderPreview();
});

function renderPreview() {
    preview.innerHTML = "";
    filesBuffer.forEach((f, i) => {
        const box = document.createElement("div");
        box.className = "pc-preview-item";
        const url = URL.createObjectURL(f);
        box.innerHTML = f.type.startsWith("image/")
            ? `<img src="${url}">`
            : `<video src="${url}" autoplay muted loop></video>`;
        const del = document.createElement("button");
        del.textContent = "×";
        del.onclick = () => { filesBuffer.splice(i,1); renderPreview(); };
        box.appendChild(del);
        preview.appendChild(box);
    });
}

document.querySelector("form").addEventListener("submit", () => {

    function stripHtml(input) {
        return input
            .replace(/<[^>]*>/g, "")   // xoá toàn bộ thẻ HTML
            .replace(/&nbsp;/g, " ")  // đổi &nbsp; thành khoảng trắng
            .trim();
    }

    document.getElementById("title").value = stripHtml(titleEditor.innerHTML);
    document.getElementById("content").value = stripHtml(contentEditor.innerHTML);

    // xử lý file
    const dt = new DataTransfer();
    filesBuffer.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
});



// -------- EDITOR RANGE SAVING --------

let activeEditor = null;
let savedRange = { titleEditor: null, contentEditor: null };

const editors = [
    document.getElementById("titleEditor"),
    document.getElementById("contentEditor")
];

// Xóa <br> ẩn để placeholder hoạt động
editors.forEach(ed => {
    if (ed.innerHTML.trim() === "<br>") ed.innerHTML = "";
});


function saveSelection(editorId) {
    let sel = window.getSelection();
    if (sel.rangeCount > 0) savedRange[editorId] = sel.getRangeAt(0);
}

["titleEditor", "contentEditor"].forEach(id => {
    const ed = document.getElementById(id);
    ed.addEventListener("focus",()=>activeEditor=ed);
    ed.addEventListener("keyup",()=>saveSelection(id));
    ed.addEventListener("mouseup",()=>saveSelection(id));
});


// -------- FORMAT BUTTONS --------
document.querySelectorAll(".tool-btn[data-cmd]").forEach(btn => {
    btn.addEventListener("click", () => {
        if (!activeEditor) return;
        activeEditor.focus();
        document.execCommand(btn.dataset.cmd, false, null);
        saveSelection(activeEditor.id);
    });
});


// -------- EMOJI PANEL --------
const emojis = ["😀","😁","😂","🤣","😎","😍","🤔","😡","😭","👍","🔥","💯","🤯","🤝","🎮"];
const emojiPanel = document.getElementById("emojiPanel");
let emojiTargetEditor = null;

emojis.forEach(e=>{
    let span=document.createElement("span");
    span.textContent=e;
    span.onclick=()=>insertEmoji(e);
    emojiPanel.appendChild(span);
});

document.querySelectorAll(".emojiBtn").forEach(btn=>{
    btn.addEventListener("click",(ev)=>{
        ev.stopPropagation();

        let id = btn.dataset.target;
        emojiTargetEditor = document.getElementById(id);
        activeEditor = emojiTargetEditor;

        let rect = btn.getBoundingClientRect();
        emojiPanel.style.left = rect.left + "px";
        emojiPanel.style.top = (rect.bottom + 5) + "px";
        emojiPanel.style.display = "flex";

        emojiTargetEditor.focus();
        saveSelection(id);
    });
});

// Không tắt khi click vào panel
emojiPanel.addEventListener("click",(e)=>e.stopPropagation());

// Click ngoài → ẩn panel
document.addEventListener("click", ()=> emojiPanel.style.display="none");


// -------- CHÈN EMOJI --------
function insertEmoji(emo) {
    if (!emojiTargetEditor) return;

    emojiTargetEditor.focus();
    let sel = window.getSelection();
    sel.removeAllRanges();

    let range = savedRange[emojiTargetEditor.id];
    if (range) sel.addRange(range);

    range = sel.getRangeAt(0);
    range.deleteContents();
    range.insertNode(document.createTextNode(emo));

    // caret sau emoji
    range.collapse(false);
    savedRange[emojiTargetEditor.id] = range;
}
</script>


<?php include 'includes/footer.php'; ?>
