<?php include "includes/header.php"; ?>

<!-- Vùng chơi game -->
<canvas id="gameCanvas" width="800" height="500"></canvas>

<!-- Overlay thắng cuộc -->
<div id="winOverlay" class="overlay hidden">
  <div class="overlay-inner">
    <div class="col left">
      <h3>🏆 Bảng Xếp Hạng</h3>
      <div id="lbStatus" class="muted">Đang tải BXH...</div>
      <table id="lbTable" class="lb-table hidden">
        <thead>
          <tr><th>#</th><th>Người chơi</th><th>Điểm cao nhất</th></tr>
        </thead>
        <tbody></tbody>
      </table>
      <div id="yourRank" class="your-rank hidden"></div>
    </div>

    <div class="col right">
      <h3>🎉 Vượt qua các màn!</h3>
      <p id="finalScoreText"></p>
      <div class="actions">
        <button id="btnOk" class="btn primary">OK</button>
        <a id="btnShare" class="btn" href="#" style="display:none">Đăng bài khoe điểm</a>
        <button id="btnReplay" class="btn">Chơi lại</button>
      </div>
    </div>
  </div>
</div>

<!-- Script game -->
<script src="assets/game.js"></script>

<?php include "includes/footer.php"; ?>
