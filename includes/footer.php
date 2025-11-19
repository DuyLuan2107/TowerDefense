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
<link rel="stylesheet" href="/TowerDefense/chatbotai/style.css" />

<?php
// Include the chatbot HTML fragment (button + popup).
// __DIR__ is the includes/ directory, so go up one level to the project root.
include_once __DIR__ . '/../chatbotai/chatbot.php';
?>

<!-- External scripts used by chatbot -->
<script src="https://cdn.jsdelivr.net/npm/emoji-mart@latest/dist/browser.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.17.2/dist/sweetalert2.all.min.js"></script>
<!-- Chatbot script (root-relative) -->
<script src="/TowerDefense/chatbotai/script.js"></script>

</body>
</html>