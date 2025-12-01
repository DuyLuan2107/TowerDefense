<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db/connect.php';

if (!isset($_SESSION['user'])) {
    echo '<div class="profile-container"><div class="profile-message">
            Vui lòng <a class="btn-login" href="auth.php">đăng nhập</a> để sửa bài viết.
          </div></div>';
    include 'includes/footer.php';
    exit;
}

$post_id = (int)($_GET['id'] ?? 0);
if ($post_id <= 0) {
    echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Lấy bài viết
$stmt = $conn->prepare("SELECT * FROM posts WHERE id=?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

// Kiểm tra bài
if (!$post) {
    echo "<div class='profile-container'><p>Bài viết không tồn tại.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Kiểm tra quyền
if ($post['user_id'] != $_SESSION['user']['id']) {
    echo "<div class='profile-container'><p>Bạn không có quyền sửa bài này.</p></div>";
    include 'includes/footer.php';
    exit;
}

// Lấy file cũ
$oldFiles = $conn->query("SELECT * FROM post_files WHERE post_id=$post_id");

// ==================== XÓA FILE RIÊNG (POST) ====================
if (isset($_POST['delete_file'])) {
    $fid = (int)$_POST['delete_file'];
    $q = $conn->query("SELECT file_path FROM post_files WHERE id=$fid AND post_id=$post_id");
    if ($f = $q->fetch_assoc()) {
        if (file_exists($f['file_path'])) unlink($f['file_path']);
    }
    $conn->query("DELETE FROM post_files WHERE id=$fid");
    header("Location: forum_edit_post.php?id=".$post_id);
    exit;
}

// ==================== LƯU ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_file'])) {

    // Xóa file cũ đánh dấu
    if (!empty($_POST['delete_list'])) {
        $list = json_decode($_POST['delete_list'], true);
        foreach ($list as $fid) {
            $fid = (int)$fid;
            $q = $conn->query("SELECT file_path FROM post_files WHERE id=$fid AND post_id=$post_id");
            if ($f = $q->fetch_assoc()) {
                if (file_exists($f['file_path'])) unlink($f['file_path']);
            }
            $conn->query("DELETE FROM post_files WHERE id=$fid");
        }
    }

    // Update bài (nhận text thuần)
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === "") {
        $error = "Tiêu đề không được để trống.";
    } else {
        $upd = $conn->prepare("UPDATE posts SET title=?, content=? WHERE id=?");
        $upd->bind_param("ssi", $title, $content, $post_id);
        $upd->execute();

        // Upload file mới
        if (!empty($_FILES['files']['name'][0])) {
            $uploadDir = "uploads/posts/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            foreach ($_FILES['files']['name'] as $i => $name) {
                $tmp = $_FILES['files']['tmp_name'][$i];
                $err = $_FILES['files']['error'][$i];
                if ($err !== UPLOAD_ERR_OK || !file_exists($tmp)) continue;

                $mime = mime_content_type($tmp);
                $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (!(strpos($mime,"image/")===0 || $mime==="video/mp4")) continue;

                $newName = time()."_".rand(1000,9999).".".$ext;
                $path = $uploadDir.$newName;
                move_uploaded_file($tmp, $path);

                $type = strpos($mime,"video")===0 ? "video" : "image";

                $stmtF = $conn->prepare("INSERT INTO post_files (post_id,file_path,file_type) VALUES (?,?,?)");
                $stmtF->bind_param("iss", $post_id, $path, $type);
                $stmtF->execute();
            }
        }

        header("Location: forum_view.php?id=".$post_id);
        exit;
    }
}

// --- Vì user chọn: B = chỉ chỉnh sửa plain text
// Convert stored HTML to plain text for editing:
function html_to_plain($html) {
    // Decode HTML entities, then strip tags
    return trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

$prefillTitlePlain = htmlspecialchars(html_to_plain($post['title']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$prefillContentPlain = htmlspecialchars(html_to_plain($post['content']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

include 'includes/header.php';
?>

<style>
/* ========== Styles giống trang đăng bài ========== */
.post-create, .edit-post-container {
    width: 700px;
    margin: 12px auto 40px;
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 12px rgba(0,0,0,.06);
    font-family: "Segoe UI", Tahoma, sans-serif;
    font-size: 15px;
}
.edit-post h2 { margin-top:0; }

/* toolbar/editor */
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
.tool-btn { background:none; border:none; padding:6px 10px; cursor:pointer; border-radius:6px; font-size:14px; color:#475569; }
.tool-btn:hover { background:#e2e8f0; }

.editor-area {
    min-height: 120px;
    padding: 12px 14px;
    font-size: 15px;
    line-height:1.5;
    outline:none;
    color:#0f172a;
}

/* placeholder */
.editor-area[contenteditable="true"]:empty:before,
.editor-area[contenteditable="true"]:not(:focus):before {
    content: attr(placeholder);
    color: #94a3b8;
    pointer-events: none;
}
.editor-area[contenteditable="true"]:not(:focus):not(:empty):before { content: ""; }

/* preview files */
.edit-preview { display:flex; flex-wrap:wrap; gap:12px; margin-top:12px; }
.edit-preview-item { width:120px; height:120px; position:relative; border-radius:10px; overflow:hidden; background:#f1f5f9; border:1px solid #e2e8f0; }
.edit-preview-item img, .edit-preview-item video { width:100%; height:100%; object-fit:cover; }
.edit-preview-item button { position:absolute; right:6px; top:6px; width:22px; height:22px; background:rgba(0,0,0,0.65); color:#fff; border:none; font-size:14px; border-radius:50%; cursor:pointer; }

/* emoji panel (fixed) */
#emojiPanel {
    position: fixed;
    background: #fff;
    border: 1px solid #cbd5e1;
    padding: 8px;
    border-radius: 8px;
    width: 220px;
    display:none;
    flex-wrap:wrap;
    gap:6px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    z-index: 9999;
}
#emojiPanel span { font-size:18px; cursor:pointer; padding:6px; border-radius:6px; }
#emojiPanel span:hover { background:#e2e8f0; }

/* actions */
.edit-actions { margin-top:14px; display:flex; gap:12px; justify-content:flex-end; align-items:center; }
.edit-btn { padding:10px 18px; background:#2563eb; color:#fff; border:none; border-radius:8px; cursor:pointer; }
.edit-cancel { color:#666; text-decoration:none; }
</style>

<div class="post-create">
    <h2>✏️ Sửa bài viết</h2>

    <?php if (!empty($error)): ?>
        <div class="edit-error" style="color:#b91c1c; margin-bottom:10px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="editForm">
        <input type="hidden" name="delete_list" id="deleteList">

        <!-- TITLE (contenteditable plain text) -->
        <label class="pc-label">Tiêu đề bài viết:</label>
        <div class="editor-box">
            <div class="editor-toolbar">
                <button type="button" class="tool-btn" data-cmd="bold"><b>B</b></button>
                <button type="button" class="tool-btn" data-cmd="italic"><i>I</i></button>
                <button type="button" class="tool-btn" data-cmd="underline"><u>U</u></button>
                <button type="button" class="tool-btn emojiBtn" data-target="titleEditor">😊</button>
            </div>
            <div id="titleEditor" class="editor-area" contenteditable="true" placeholder="Tiêu đề bài viết..."><?= $prefillTitlePlain ?></div>
        </div>
        <textarea name="title" id="title" hidden></textarea>

        <!-- CONTENT (contenteditable plain text) -->
        <label class="pc-label">Nội dung bài viết:</label>
        <div class="editor-box">
            <div class="editor-toolbar">
                <button type="button" class="tool-btn" data-cmd="bold"><b>B</b></button>
                <button type="button" class="tool-btn" data-cmd="italic"><i>I</i></button>
                <button type="button" class="tool-btn" data-cmd="underline"><u>U</u></button>
                <button type="button" class="tool-btn emojiBtn" data-target="contentEditor">😊</button>
            </div>
            <div id="contentEditor" class="editor-area" contenteditable="true" placeholder="Nội dung bài viết..."><?= $prefillContentPlain ?></div>
        </div>
        <textarea name="content" id="content" hidden></textarea>

        <!-- EMOJI PANEL -->
        <div id="emojiPanel" aria-hidden="true"></div>

        <!-- FILES -->
        <label class="pc-label">Ảnh / Video (có thể chọn nhiều):</label>
        <input type="file" id="fileInput" name="files[]" multiple accept="image/*,video/mp4" class="pc-file">
        <div id="previewArea" class="edit-preview">
            <?php foreach ($oldFiles as $f): ?>
                <div class="edit-preview-item old-file" data-old-id="<?= $f['id'] ?>">
                    <?php if ($f['file_type'] === 'image'): ?>
                        <img src="<?= htmlspecialchars($f['file_path'], ENT_QUOTES) ?>" alt="">
                    <?php else: ?>
                        <video src="<?= htmlspecialchars($f['file_path'], ENT_QUOTES) ?>" muted autoplay loop></video>
                    <?php endif; ?>
                    <button type="button" class="edit-del-old" data-id="<?= $f['id'] ?>">×</button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="edit-actions">
            <button class="edit-btn" type="submit">💾 Lưu thay đổi</button>
            <a class="edit-cancel" href="javascript:history.back()">Hủy</a>
        </div>
    </form>
</div>

<script>
/* ================== FILE PREVIEW / UPLOAD ================== */
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('previewArea');
let filesBuffer = [];
const MAX = 40 * 1024 * 1024;
fileInput.addEventListener('change', () => {
    for (let f of fileInput.files) {
        if (f.size > MAX) { alert("File quá lớn (max 40MB)."); continue; }
        filesBuffer.push(f);
    }
    fileInput.value = "";
    renderNewFiles();
});

function renderNewFiles() {
    // Remove existing temporary previews
    document.querySelectorAll('.preview-new').forEach(e => e.remove());
    filesBuffer.forEach((file, idx) => {
        const wrap = document.createElement('div');
        wrap.className = 'edit-preview-item preview-new';
        const url = URL.createObjectURL(file);
        if (file.type.startsWith('image/')) wrap.innerHTML = `<img src="${url}">`;
        else wrap.innerHTML = `<video src="${url}" muted autoplay loop></video>`;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'edit-del-old';
        btn.innerText = '×';
        btn.onclick = () => { filesBuffer.splice(idx,1); renderNewFiles(); };
        wrap.appendChild(btn);
        preview.appendChild(wrap);
    });
}

// On form submit: sync editors -> hidden textareas, attach filesBuffer to fileInput
document.getElementById('editForm').addEventListener('submit', (ev) => {
    // use innerText to send plain text (user chose B)
    document.getElementById('title').value = document.getElementById('titleEditor').innerText.trim();
    document.getElementById('content').value = document.getElementById('contentEditor').innerText.trim();

    const dt = new DataTransfer();
    filesBuffer.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
});

/* ================ HANDLE OLD FILE DELETE ================ */
let deleteList = [];
document.querySelectorAll(".edit-del-old").forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        if (!id) return;
        deleteList.push(parseInt(id));
        document.getElementById('deleteList').value = JSON.stringify(deleteList);
        this.parentElement.remove();
    });
});

/* ================== EDITOR / TOOLBAR / EMOJI ================== */
let activeEditor = null;
const editors = [
    document.getElementById('titleEditor'),
    document.getElementById('contentEditor')
];

// Remove any stray <br> that browsers sometimes insert so :empty works
editors.forEach(ed => {
    if (ed && ed.innerHTML.trim() === '<br>') ed.innerHTML = '';
});

// saved ranges per editor id
let savedRange = { titleEditor: null, contentEditor: null };

function saveSelection(editorId) {
    const sel = window.getSelection();
    if (sel.rangeCount > 0) savedRange[editorId] = sel.getRangeAt(0);
}

// track focus and selection
editors.forEach(ed => {
    if (!ed) return;
    ed.addEventListener('focus', () => activeEditor = ed);
    ed.addEventListener('click', () => activeEditor = ed);
    ed.addEventListener('keyup', () => saveSelection(ed.id));
    ed.addEventListener('mouseup', () => saveSelection(ed.id));
});

// format buttons
document.querySelectorAll('.tool-btn[data-cmd]').forEach(btn => {
    btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (!activeEditor) return;
        activeEditor.focus();
        document.execCommand(btn.getAttribute('data-cmd'), false, null);
        saveSelection(activeEditor.id);
    });
});

/* ================ EMOJI PANEL (shared) ================= */
const emojis = ["😀","😁","😂","🤣","😎","😍","🤔","😡","😭","👍","🔥","💯","🤯","🤝","🎮"];
const emojiPanel = document.getElementById('emojiPanel');
let emojiTargetEditor = null;

emojis.forEach(e => {
    const s = document.createElement('span');
    s.textContent = e;
    s.type = 'button';
    s.addEventListener('click', (ev) => {
        ev.stopPropagation();
        insertEmoji(e);
        emojiPanel.style.display = 'none';
    });
    emojiPanel.appendChild(s);
});

document.querySelectorAll('.emojiBtn').forEach(btn => {
    btn.addEventListener('click', (ev) => {
        ev.stopPropagation();
        const id = btn.dataset.target;
        emojiTargetEditor = document.getElementById(id);
        activeEditor = emojiTargetEditor;

        // position panel under button
        const rect = btn.getBoundingClientRect();
        emojiPanel.style.left = rect.left + 'px';
        emojiPanel.style.top = (rect.bottom + 6) + 'px';
        emojiPanel.style.display = 'flex';

        // focus and save caret
        emojiTargetEditor.focus();
        saveSelection(id);
    });
});

// prevent panel clicks from closing it
emojiPanel.addEventListener('click', e => e.stopPropagation());
document.addEventListener('click', () => emojiPanel.style.display = 'none');

// insertEmoji uses savedRange for the targeted editor
function insertEmoji(emo) {
    if (!emojiTargetEditor) return;
    emojiTargetEditor.focus();

    const sel = window.getSelection();
    sel.removeAllRanges();

    const range = savedRange[emojiTargetEditor.id];
    if (range) sel.addRange(range);

    // if still no range, create one at end
    if (!sel.rangeCount) {
        const r = document.createRange();
        r.selectNodeContents(emojiTargetEditor);
        r.collapse(false);
        sel.addRange(r);
    }

    const r = sel.getRangeAt(0);
    r.deleteContents();
    // insert text node (plain emoji)
    const node = document.createTextNode(emo);
    r.insertNode(node);

    // move caret after inserted node
    r.setStartAfter(node);
    r.collapse(true);

    // update savedRange
    savedRange[emojiTargetEditor.id] = r;

    // ensure editor remains focused
    emojiTargetEditor.focus();
}

</script>

<?php include 'includes/footer.php'; ?>
