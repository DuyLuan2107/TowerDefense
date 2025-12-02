<?php
// (Nội dung file này chỉ là HTML)
?>

<footer>
    <p>&copy; <?php echo date("Y"); ?> - Game Thủ Thành PHP</p>
</footer>

<!-- Chatbot AI: styles, embed and scripts -->
<!-- Google Material Symbols (icons) -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@48,400,1,0" />
<!-- Chatbot styles (root-relative to project folder) -->
<?php
$docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
$projectDir = realpath(__DIR__ . '/..'); // includes/ -> project root
$root = '';
if ($docRoot !== false && $projectDir !== false) {
    $docRoot = str_replace('\\', '/', $docRoot);
    $projectDir = str_replace('\\', '/', $projectDir);
    if (strpos($projectDir, $docRoot) === 0) {
        $root = substr($projectDir, strlen($docRoot));
        $root = $root === '' ? '' : '/' . trim($root, '/');
    }
}
if ($root === '') {
    // Fallback to older behavior: use first segment of SCRIPT_NAME
    $script_name = trim($_SERVER['SCRIPT_NAME'], '/');
    $parts = $script_name === '' ? [] : explode('/', $script_name);
    $root = (isset($parts[0]) && $parts[0] !== '') ? '/' . $parts[0] : '';
}
$style_url = $root . '/chatbotai/style.css';
?>
<link rel="stylesheet" href="<?php echo $style_url; ?>" />

<?php
// Include the chatbot HTML fragment (button + popup).
// __DIR__ is the includes/ directory, so go up one level to the project root.
include_once __DIR__ . '/../chatbotai/chatbot.php';
?>

<!-- External scripts used by chatbot -->
<script src="https://cdn.jsdelivr.net/npm/emoji-mart@latest/dist/browser.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.17.2/dist/sweetalert2.all.min.js"></script>

<!-- Chatbot script (root-relative) -->
<?php
$script_url = $root . '/chatbotai/script.js';
?>
<script src="<?php echo $script_url; ?>"></script>
<button id="scrollTopBtn" title="Lên đầu trang" aria-label="Lên đầu trang" type="button">
  <span class="material-symbols-rounded">vertical_align_top</span>
</button>

</body>
</html>
<script>
window.addEventListener('scroll', function () {
    const btn = document.getElementById('scrollTopBtn');
    btn.style.display = window.scrollY > 200 ? 'flex' : 'none';
});

document.getElementById('scrollTopBtn').addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>
