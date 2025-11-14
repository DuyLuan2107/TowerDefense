// === Tower Defense (Canvas) — Bản ghép: PATH theo lưới ===

const canvas = document.getElementById("gameCanvas");
const ctx = canvas.getContext("2d");

// --- Cấu hình Canvas (base) ---
const BASE_WIDTH = 1080;
const BASE_HEIGHT = 540;

let scaleX = 1;
let scaleY = 1;

// Keep track of previous scales so we can proportionally move objects when resizing
let _prevScaleX = scaleX;
let _prevScaleY = scaleY;

// --- GRID (theo bản 2) ---
const BASE_GRID_SIZE = 50; // mỗi ô 50px trong hệ toạ độ base 800x600
const GRID_COLS = Math.floor(BASE_WIDTH / BASE_GRID_SIZE);
const GRID_ROWS = Math.floor(BASE_HEIGHT / BASE_GRID_SIZE);

// PATH dạng lưới (col,row) — bạn tuỳ ý sửa pattern ở đây
const PATH_GRID = [
  {col:0,row:3},{col:6,row:3},
  {col:6,row:5},{col:3,row:5},
  {col:3,row:8},{col:9,row:8},
  {col:9,row:4},{col:11,row:4},
  {col:11,row:2},{col:14,row:2},
  {col:14,row:7},{col:12,row:7},
  {col:12,row:9},{col:17,row:9},
  {col:17,row:6},{col:20,row:6}
];


// Quy đổi ô lưới -> pixel "base"
function gridToBasePixel(col, row) {
  return {
    x: col * BASE_GRID_SIZE + BASE_GRID_SIZE / 2,
    y: row * BASE_GRID_SIZE + BASE_GRID_SIZE / 2
  };
}

// *** Sinh PATH pixel từ PATH_GRID (để logic cũ dùng như bình thường) ***
const PATH = PATH_GRID.map(p => gridToBasePixel(p.col, p.row));

// Chi phí tháp & kẻ địch
const TOWER_COST = 20;
const ENEMY_BASE_SPEED = 100; // px/sec

// --- Trạng thái Game ---
let towers = [];
let enemies = [];
let bullets = [];
let gold = 100;
let lives = 20;
// === Scoring/Runs ===
let gameStartedAt = null;   // timestamp (ms) khi bắt đầu lượt
let enemiesKilled = 0;      // số quái diệt được trong lượt
let runSubmitted = false; // tránh submit nhiều lần

// UI / placement state
let mouseX = 0;
let mouseY = 0;
let selectedTowerType = null; // index vào towerTypes hoặc null

// Sidebar chọn tháp
const SIDEBAR_WIDTH = 140;

// Đồng bộ cell grid với base cell
const BASE_CELL = BASE_GRID_SIZE;

// Các loại tháp
const towerTypes = [
  { id: 1, name: 'Tháp Thường', cost: 20, damage: 40, range: 120, fireRate: 1.0 },
  { id: 2, name: 'Tháp Mạnh', cost: 30, damage: 60, range: 130, fireRate: 0.9 },
  { id: 3, name: 'Tháp Siêu Mạnh', cost: 40, damage: 100, range: 150, fireRate: 1.2 }
];

// Hiệu ứng
let hitEffects = [];

// Vàng rơi bay về UI
let goldPickups = [];
// Hàm tiện ích
function getElapsedSeconds() {
  if (!gameStartedAt) return 0;
  return Math.floor((performance.now() - gameStartedAt) / 1000);
}
function getLiveScore() {
  const durationSec = getElapsedSeconds();
  const goldLeft = gold;
  const score = Math.max(0, Math.round(enemiesKilled * 10 + goldLeft * 1 - durationSec * 0.5));
  return { score, durationSec };
}
// --- Resize + scale ---
function updateCanvasSize() {
  // Kích thước nền base (đang dùng trong game)
  const baseW = BASE_WIDTH;   // 800
  const baseH = BASE_HEIGHT;  // 600
  const aspect = baseW / baseH;

  // Lấy kích thước còn lại trên màn hình (trừ navbar, footer, padding container)
  const header = document.querySelector('.navbar');
  const footer = document.querySelector('footer');
  const container = document.querySelector('.container');

  const headerH = header ? header.offsetHeight : 0;         // ~60
  const footerH = footer ? footer.offsetHeight : 0;         // tuỳ trang
  const padTop   = container ? parseFloat(getComputedStyle(container).paddingTop)  : 0;
  const padBot   = container ? parseFloat(getComputedStyle(container).paddingBottom) : 0;

  const availW = window.innerWidth  - 40;                    // trừ 20px padding 2 bên .container
  const availH = window.innerHeight - headerH - footerH - padTop - padBot - 4;

  // Co theo đúng tỉ lệ, KHÔNG vượt quá vùng khả dụng
  const scale = Math.min(availW / baseW, availH / baseH);

  const newCssW = Math.max(320, Math.floor(baseW * scale));
  const newCssH = Math.max(240, Math.floor(newCssW / aspect));

  // Cập nhật kích thước vẽ (thuộc tính) + kích thước hiển thị (CSS)
  canvas.width  = newCssW;
  canvas.height = newCssH;
  canvas.style.width  = newCssW + 'px';
  canvas.style.height = newCssH + 'px';

  // cập nhật factor scale để mọi tính toán/đường đi/tower khớp
  const oldScaleX = scaleX || 1;
  const oldScaleY = scaleY || 1;
  const newScaleX = newCssW / baseW;
  const newScaleY = newCssH / baseH;

  const ratioX = (oldScaleX !== 0) ? (newScaleX / oldScaleX) : 1;
  const ratioY = (oldScaleY !== 0) ? (newScaleY / oldScaleY) : 1;
  const avgRatio = (ratioX + ratioY) / 2;

  for (const t of towers) { t.x *= ratioX; t.y *= ratioY; t.range *= avgRatio; }
  for (const e of enemies) { e.x *= ratioX; e.y *= ratioY; e.speed *= avgRatio; }
  for (const b of bullets) { b.x *= ratioX; b.y *= ratioY; b.speed *= avgRatio; }
  for (const p of goldPickups) { p.x *= ratioX; p.y *= ratioY; }
  for (const h of hitEffects) { h.x *= ratioX; h.y *= ratioY; }

  scaleX = newScaleX;
  scaleY = newScaleY;
  _prevScaleX = scaleX;
  _prevScaleY = scaleY;
}
function startRun() {
  gameStartedAt = performance.now();
  enemiesKilled = 0;
  runSubmitted = false;     // reset cờ
}

// Wave system (giữ nguyên)
const waves = [
  { count: 6, spawnInterval: 1.0 },
  { count: 10, spawnInterval: 0.9 },
  { count: 15, spawnInterval: 0.8 }
];
let currentWave = 0;
let waveSpawnTimer = 0;
let waveSpawned = 0;
let waveActive = false;

// Game state
let gameState = 'menu';

// --- Audio (giữ nguyên) ---
let shootAudio = null, deathAudio = null, collectAudio = null, bgAudio = null;
try { shootAudio = new Audio('assets/shoot.mp3'); } catch (e) { shootAudio = null; }
try { deathAudio = new Audio('assets/death.mp3'); } catch (e) { deathAudio = null; }
try { collectAudio = new Audio('assets/collect.mp3'); } catch (e) { collectAudio = null; }
try { bgAudio = new Audio('assets/bg.mp3'); if (bgAudio) bgAudio.loop = true; } catch (e) { bgAudio = null; }

if (shootAudio) {
  shootAudio.addEventListener('canplaythrough', () => console.log('shoot.mp3 loaded'));
  shootAudio.addEventListener('error', () => console.warn('Could not load assets/shoot.mp3'));
}
if (deathAudio) {
  deathAudio.addEventListener('canplaythrough', () => console.log('death.mp3 loaded'));
  deathAudio.addEventListener('error', () => console.warn('Could not load assets/death.mp3'));
}
if (collectAudio) {
  collectAudio.addEventListener('canplaythrough', () => console.log('collect.mp3 loaded'));
  collectAudio.addEventListener('error', () => console.warn('Could not load assets/collect.mp3'));
}
if (bgAudio) {
  bgAudio.addEventListener('canplaythrough', () => console.log('bg.mp3 loaded'));
  bgAudio.addEventListener('error', () => console.warn('Could not load assets/bg.mp3'));
}

let audioUnlocked = false;
function unlockAudio() {
  if (audioUnlocked) return;
  audioUnlocked = true;
  try { if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume(); } catch (e) {}
  try {
    if (bgAudio && bgAudio.play) {
      bgAudio.volume = 0.25;
      bgAudio.play().catch(() => {});
    }
  } catch (e) {}
}

let selectedTowerForUpgrade = null;

let audioCtx = null;
function ensureAudio() {
  if (!audioCtx) {
    try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); }
    catch (e) { audioCtx = null; }
  }
}
function playShoot() {
  if (shootAudio && shootAudio.play) { try { shootAudio.currentTime = 0; shootAudio.play(); return; } catch (e) {} }
  ensureAudio(); if (!audioCtx) return;
  const o = audioCtx.createOscillator(), g = audioCtx.createGain();
  o.type = 'sine'; o.frequency.value = 900; g.gain.value = 0.05;
  o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(audioCtx.currentTime + 0.06);
}
function playDeath() {
  if (deathAudio && deathAudio.play) { try { deathAudio.currentTime = 0; deathAudio.play(); return; } catch (e) {} }
  ensureAudio(); if (!audioCtx) return;
  const o = audioCtx.createOscillator(), g = audioCtx.createGain();
  o.type = 'sawtooth'; o.frequency.value = 220; g.gain.value = 0.06;
  o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(audioCtx.currentTime + 0.18);
}
function playCollect() {
  if (collectAudio && collectAudio.play) { try { collectAudio.currentTime = 0; collectAudio.play(); return; } catch (e) {} }
  ensureAudio(); if (!audioCtx) return;
  const o = audioCtx.createOscillator(), g = audioCtx.createGain();
  const now = audioCtx.currentTime;
  o.type = 'triangle'; o.frequency.value = 800; g.gain.value = 0.03;
  o.frequency.setValueAtTime(800, now); o.frequency.linearRampToValueAtTime(400, now + 0.1);
  g.gain.setValueAtTime(0.03, now); g.gain.linearRampToValueAtTime(0, now + 0.1);
  o.connect(g); g.connect(audioCtx.destination); o.start(); o.stop(now + 0.1);
}

// --- Asset Loading ---
const assets = {
  map: new Image(),
  tower: new Image(),
  tower2: new Image(),
  tower3: new Image(),
  enemy: new Image(),
  goldPile: new Image(),
  heart: new Image(),
  castle: new Image()
};

let assetsLoaded = 0;
const totalAssets = Object.keys(assets).length;

function loadAssets() {
  return new Promise(resolve => {
    assets.map.src = 'assets/map_background.png';
    assets.tower.src = 'assets/tower.png';
    assets.tower2.src = 'assets/tower2.png';
    assets.tower3.src = 'assets/tower3.png';
    assets.enemy.src = 'assets/enemy.png';
    assets.goldPile.src = 'assets/gold_pile.png';
    assets.heart.src = 'assets/heart.png';
    assets.castle.src = 'assets/castle.png';

    for (const key in assets) {
      assets[key].onload = () => {
        assetsLoaded++;
        if (assetsLoaded === totalAssets) resolve();
      };
    }
  });
}

// --- Utility: Distance ---
function distance(x1, y1, x2, y2) {
  return Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2);
}

// --- Vẽ Map (lưới + tô đường đi) ---
function drawMap() {
  // Nền
  ctx.drawImage(assets.map, 0, 0, canvas.width, canvas.height);

  // Tô ô đường đi theo PATH_GRID
  const cellW = BASE_GRID_SIZE * scaleX;
  const cellH = BASE_GRID_SIZE * scaleY;

  ctx.fillStyle = "rgba(200,0,0,0.25)";
  for (let i = 0; i < PATH_GRID.length - 1; i++) {
    const a = PATH_GRID[i], b = PATH_GRID[i + 1];

    if (a.row === b.row) {
      // ngang
      const r = a.row;
      const minC = Math.min(a.col, b.col), maxC = Math.max(a.col, b.col);
      for (let c = minC; c <= maxC; c++) {
        const x = c * BASE_GRID_SIZE * scaleX;
        const y = r * BASE_GRID_SIZE * scaleY;
        ctx.fillRect(x, y, cellW, cellH);
      }
    } else if (a.col === b.col) {
      // dọc
      const c = a.col;
      const minR = Math.min(a.row, b.row), maxR = Math.max(a.row, b.row);
      for (let r = minR; r <= maxR; r++) {
        const x = c * BASE_GRID_SIZE * scaleX;
        const y = r * BASE_GRID_SIZE * scaleY;
        ctx.fillRect(x, y, cellW, cellH);
      }
    }
  }

  // Lưới nhẹ
  ctx.strokeStyle = 'rgba(0,0,0,0.06)';
  for (let gx = 0; gx <= GRID_COLS; gx++) {
    const x = gx * BASE_GRID_SIZE * scaleX;
    ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, canvas.height); ctx.stroke();
  }
  for (let gy = 0; gy <= GRID_ROWS; gy++) {
    const y = gy * BASE_GRID_SIZE * scaleY;
    ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(canvas.width, y); ctx.stroke();
  }

  // Castle ở ô cuối PATH
  try {
    const last = PATH_GRID[PATH_GRID.length - 1];
    const endBase = gridToBasePixel(last.col, last.row);
    const ex = endBase.x * scaleX, ey = endBase.y * scaleY;
    const castleScale = (scaleX + scaleY) / 2;
    // đặt ở đầu file (khu constants)
    const CASTLE_SIZE_MULT = 2.0; // 1.0 = bằng 1 ô, 1.4 = to hơn ~40%

    // trong drawMap(), thay dòng cSize = ...
    const cSize = CASTLE_SIZE_MULT * BASE_GRID_SIZE * castleScale;


    const CASTLE_OFFSET_BASE = { x: 200, y: -14}; // px trong hệ BASE_WIDTH/HEIGHT

      let drawX = ex - cSize / 2 + CASTLE_OFFSET_BASE.x * scaleX;
      let drawY = ey - cSize / 2 + CASTLE_OFFSET_BASE.y * scaleY;

    const pad = 6;
    if (drawX < pad) drawX = pad;
    if (drawY < pad) drawY = pad;
    if (drawX + cSize > canvas.width - pad) drawX = Math.max(pad, canvas.width - cSize - pad);
    if (drawY + cSize > canvas.height - pad) drawY = Math.max(pad, canvas.height - cSize - pad);

    if (assets.castle && assets.castle.complete && assets.castle.naturalWidth > 0) {
      ctx.drawImage(assets.castle, drawX, drawY, cSize, cSize);
    } else {
      ctx.fillStyle = 'saddlebrown';
      ctx.fillRect(drawX, drawY, cSize, cSize);
    }
  } catch (e) {}
}

// --- Vẽ Tower/Enemy/Bullet/Effects (giữ nguyên) ---
function drawTower(t) {
  // Phạm vi
  ctx.strokeStyle = "rgba(100, 100, 255, 0.3)";
  ctx.beginPath();
  ctx.arc(t.x, t.y, t.range, 0, Math.PI * 2);
  ctx.stroke();

  const drawSize = 30 * ((scaleX + scaleY) / 2);
  let img = assets.tower;
  if (t.type === 2) img = assets.tower2;
  if (t.type === 3) img = assets.tower3;
  ctx.drawImage(img, t.x - drawSize/2, t.y - drawSize/2, drawSize, drawSize);
}

function drawEnemy(e) {
  const drawSize = 30 * ((scaleX + scaleY) / 2);
  ctx.drawImage(assets.enemy, e.x - drawSize/2, e.y - drawSize/2, drawSize, drawSize);

  const hp_bar_width = 30;
  const hp_bar_height = 5;
  const hp_ratio = e.hp / e.maxHp;
  ctx.fillStyle = "gray";
  ctx.fillRect(e.x - hp_bar_width/2, e.y - 25, hp_bar_width, hp_bar_height);
  ctx.fillStyle = "green";
  ctx.fillRect(e.x - hp_bar_width/2, e.y - 25, hp_bar_width * hp_ratio, hp_bar_height);
}

function drawBullet(b) {
  ctx.fillStyle = "yellow";
  ctx.beginPath();
  ctx.arc(b.x, b.y, 5, 0, Math.PI * 2);
  ctx.fill();
}

function drawHitEffects() {
  hitEffects = hitEffects.filter(h => h.life > 0);
  hitEffects.forEach(h => {
    ctx.fillStyle = `rgba(255,0,0,${h.life/0.4})`;
    ctx.beginPath();
    ctx.arc(h.x, h.y, 8 * (1 - h.life/0.4), 0, Math.PI*2);
    ctx.fill();
    h.life -= 1/60;
  });
}

function getGoldUIPos() { return { x: 60, y: 22 }; }

function updateGoldPickups(deltaTime) {
  const target = getGoldUIPos();
  for (let i = goldPickups.length - 1; i >= 0; i--) {
    const p = goldPickups[i];
    const dx = target.x - p.x, dy = target.y - p.y;
    const dist = distance(p.x, p.y, target.x, target.y);
    const speed = 420;
    const move = Math.min(dist, speed * deltaTime);
    if (dist > 0.1) {
      p.x += (dx / dist) * move;
      p.y += (dy / dist) * move;
    }
    p.scale = Math.max(0.6, p.scale - deltaTime * 0.2);
    p.alpha = Math.min(1, p.alpha + deltaTime * 4);
    if (dist < 10) {
      gold += p.value;
      hitEffects.push({ x: target.x, y: target.y, life: 0.25 });
      goldPickups.splice(i, 1);
    }
  }
}

function drawGoldPickups() {
  goldPickups.forEach(p => {
    if (assets.goldPile && assets.goldPile.complete && assets.goldPile.naturalWidth > 0) {
      const size = 18 * p.scale;
      ctx.globalAlpha = p.alpha;
      ctx.drawImage(assets.goldPile, p.x - size/2, p.y - size/2, size, size);
      ctx.globalAlpha = 1;
    } else {
      ctx.fillStyle = `rgba(255,215,0,${p.alpha})`;
      ctx.beginPath(); ctx.arc(p.x, p.y, 8 * p.scale, 0, Math.PI*2); ctx.fill();
    }
  });
}

// --- UI (giữ nguyên) ---
function drawUI() {
  const goldUI = { x: 46, y: 30 };
  const heartUI = { x: 46, y: goldUI.y + 40 };

  ctx.textAlign = 'left';
  ctx.textBaseline = 'middle';

  // GOLD
  if (assets.goldPile && assets.goldPile.complete && assets.goldPile.naturalWidth > 0) {
    const gs = 34;
    ctx.drawImage(assets.goldPile, goldUI.x - gs/2, goldUI.y - gs/2, gs, gs);
  } else {
    ctx.fillStyle = 'gold';
    ctx.beginPath(); ctx.arc(goldUI.x, goldUI.y, 12, 0, Math.PI*2); ctx.fill();
  }
  ctx.fillStyle = "black";
  ctx.font = "22px Arial";
  ctx.fillText(`${gold}`, goldUI.x + 30, goldUI.y);

  // HEART
  if (assets.heart && assets.heart.complete && assets.heart.naturalWidth > 0) {
    const hs = 26;
    ctx.drawImage(assets.heart, heartUI.x - hs/2, heartUI.y - hs/2, hs, hs);
  } else {
    ctx.fillStyle = 'red';
    ctx.fillRect(heartUI.x - 10, heartUI.y - 10, 22, 20);
  }
  ctx.fillStyle = "black";
  ctx.font = "22px Arial";
  ctx.fillText(`${lives}`, heartUI.x + 30, heartUI.y);

  // Wave info
  const infoX = goldUI.x + 30;
  const infoTop = heartUI.y + 25;
  const lineHeight = 22;
  ctx.textBaseline = 'top';
  ctx.fillStyle = 'black';
  ctx.font = '16px Arial';
  const infoOffsetX = -34;
  ctx.fillText(`Wave: ${Math.min(currentWave + 1, waves.length)}/${waves.length}`, infoX + infoOffsetX, infoTop);
  //ctx.fillText(`Gold/kill: 1`, infoX + infoOffsetX, infoTop + lineHeight);
  ctx.textBaseline = 'alphabetic';
  // ... sau phần Wave info
  const live = getLiveScore();
  ctx.fillText(`Thời gian: ${live.durationSec}s`, infoX + infoOffsetX, infoTop + lineHeight*1.5);
  ctx.fillText(`Điểm: ${live.score}`,           infoX + infoOffsetX, infoTop + lineHeight*2.5);

  // Panel chọn tháp
  const panelW = SIDEBAR_WIDTH;
  const panelX = canvas.width - panelW - 10;
  const panelY = 10;
  ctx.fillStyle = 'rgba(240,240,240,0.95)';
  ctx.fillRect(panelX, panelY, panelW, 232);
  ctx.strokeStyle = 'black';
  ctx.strokeRect(panelX, panelY, panelW, 232);

  const slotHeight = 72;
  const iconSize = 44;
  for (let i = 0; i < towerTypes.length; i++) {
    const tt = towerTypes[i];
    const x = panelX + 10;
    const y = panelY + 15 + i * slotHeight;
    const w = panelW - 20;
    const h = slotHeight - 10;

    ctx.fillStyle = (selectedTowerType === i) ? 'rgba(180,220,255,0.95)' : 'rgba(255,255,255,0.98)';
    ctx.fillRect(x, y, w, h);
    ctx.strokeStyle = '#ccc';
    ctx.strokeRect(x, y, w, h);

    let img = assets.tower;
    if (i === 1) img = assets.tower2;
    if (i === 2) img = assets.tower3;
    ctx.drawImage(img, x + 8, y + (h - iconSize)/2 + 2, iconSize, iconSize);

    ctx.fillStyle = 'rgba(255,240,200,0.95)';
    ctx.fillRect(x + w - 56, y + 8, 46, 24);
    ctx.fillStyle = 'black'; ctx.font = '12px Arial';
    ctx.fillText(`${tt.cost}`, x + w - 36, y + 24);
  }

  if (lives <= 0) {
    ctx.fillStyle = "red";
    ctx.font = "60px Arial";
    ctx.fillText("THẤT BẠI!", canvas.width / 2 - 120, canvas.height / 2);
  }

  if (gameState === 'menu') {
    ctx.fillStyle = 'rgba(0,0,0,0.6)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = 'white';
    ctx.font = '48px Arial';
    ctx.fillText('Phòng Thủ Tháp', canvas.width/2 - 180, canvas.height/2 - 40);
    const bw = 220, bh = 60;
    const bx = canvas.width/2 - bw/2, by = canvas.height/2 + 10;
    ctx.fillStyle = 'green';
    ctx.fillRect(bx, by, bw, bh);
    ctx.fillStyle = 'white';
    ctx.font = '28px Arial';
    ctx.fillText('BẮT ĐẦU', bx + 60, by + 40);
  }

  if (gameState === 'paused') {
    ctx.fillStyle = 'rgba(0,0,0,0.5)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = 'white';
    ctx.font = '48px Arial';
    ctx.fillText('TẠM DỪNG', canvas.width/2 - 120, canvas.height/2);
    ctx.fillStyle = 'blue';
    ctx.fillRect(canvas.width/2 - 110, canvas.height/2 + 20, 220, 50);
    ctx.fillStyle = 'white';
    ctx.font = '24px Arial';
    ctx.fillText('TIẾP TỤC', canvas.width/2 - 46, canvas.height/2 + 56);
  }

  if (gameState === 'win') {
    ctx.fillStyle = 'rgba(255,255,255,0.9)';
    ctx.fillRect(0,0,canvas.width, canvas.height);
    ctx.fillStyle = 'black';
    ctx.font = '48px Arial';
    ctx.fillText('CHIẾN THẮNG!', canvas.width/2 - 150, canvas.height/2);
  }
}

// --- Logic Game (giữ nguyên) ---
function updateTowers(deltaTime) {
  towers.forEach(t => {
    if (lives <= 0) return;
    t.cooldown -= deltaTime;

    let target = enemies.find(e => distance(t.x, t.y, e.x, e.y) <= t.range);
    if (target && t.cooldown <= 0) {
      bullets.push({ x: t.x, y: t.y, target, damage: t.damage, speed: 400, spawnTime: performance.now() });
      t.cooldown = t.fireRate;
      playShoot();
    }
  });
}

function updateBullets(deltaTime) {
  bullets = bullets.filter(b => {
    if (!b.target || b.target.hp <= 0) return false;
    const dx = b.target.x - b.x;
    const dy = b.target.y - b.y;
    const dist = distance(b.x, b.y, b.target.x, b.target.y);
    const move = b.speed * deltaTime;
    if (dist < move || dist === 0) {
      b.target.hp -= b.damage;
      hitEffects.push({ x: b.target.x, y: b.target.y, life: 0.4 });
      return false;
    } else {
      b.x += (dx / dist) * move;
      b.y += (dy / dist) * move;
      return true;
    }
  });
}

function updateEnemies(deltaTime) {
  enemies.forEach(e => {
    if (lives <= 0) return;

    // scaled path từ PATH (đã sinh từ PATH_GRID)
    const scaledPath = PATH.map(p => ({ x: p.x * scaleX, y: p.y * scaleY }));
    const targetPoint = scaledPath[e.waypointIndex];

    if (!targetPoint) { lives--; e.hp = -1; return; }

    const dx = targetPoint.x - e.x;
    const dy = targetPoint.y - e.y;
    const dist = distance(e.x, e.y, targetPoint.x, targetPoint.y);

    if (dist > 0) {
      const move = e.speed * deltaTime;
      if (dist > move) { e.x += (dx / dist) * move; e.y += (dy / dist) * move; }
      else { e.x = targetPoint.x; e.y = targetPoint.y; e.waypointIndex++; }
    }
  });

  enemies = enemies.filter(e => {
    if (e.hp <= 0) {
      if (e.waypointIndex < PATH.length) {
        enemiesKilled++;
        try { playDeath(); setTimeout(() => playCollect(), 50); } catch (ex) {}
        goldPickups.push({ x: e.x, y: e.y, value: e.goldValue || 1, scale: 1.2, alpha: 0 });
      }
      return false;
    }
    return true;
  });
}
function showWinOverlay(finalScore) {
  const ov = document.getElementById('winOverlay');
  const lbTable = document.getElementById('lbTable');
  const lbBody = lbTable.querySelector('tbody');
  const lbStatus = document.getElementById('lbStatus');
  const yourRank = document.getElementById('yourRank');
  const btnOk = document.getElementById('btnOk');
  const btnReplay = document.getElementById('btnReplay');
  const btnShare = document.getElementById('btnShare');
  const finalScoreText = document.getElementById('finalScoreText');

  finalScoreText.textContent = `Điểm của bạn: ${finalScore}`;

  ov.classList.remove('hidden');
  lbTable.classList.add('hidden');
  lbStatus.classList.remove('hidden');
  lbStatus.textContent = 'Đang tải BXH...';
  yourRank.classList.add('hidden');

  // Nút OK: về Home (hoặc bạn đổi thành resetGameToMenu() cũng được)
  btnOk.onclick = () => { window.location.href = 'index.php'; };

  // Nút chơi lại: reload trang
  btnReplay.onclick = () => { window.location.reload(); };

  // Nút khoe điểm lên forum
  btnShare.style.display = 'inline-block';
  btnShare.href = 'forum_create_post.php?score=' + finalScore;

  // Gọi API BXH
  fetch('api/leaderboard_top.php', { credentials: 'include' })
    .then(r => r.json())
    .then(data => {
      if (!data.ok) {
        lbStatus.textContent = 'Không tải được BXH.';
        return;
      }
      lbBody.innerHTML = '';
      data.top.forEach((row, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML =
          `<td>${idx + 1}</td>
           <td>${row.name}</td>
           <td style="text-align:right">${row.best_score}</td>`;
        lbBody.appendChild(tr);
      });
      lbStatus.classList.add('hidden');
      lbTable.classList.remove('hidden');

      if (data.userRank) {
        yourRank.textContent = `Vị trí của bạn: hạng #${data.userRank} (điểm cao nhất: ${data.userBest})`;
        yourRank.classList.remove('hidden');
      } else {
        yourRank.textContent = 'Hãy đăng nhập và hoàn thành một lượt để có xếp hạng!';
        yourRank.classList.remove('hidden');
      }
    })
    .catch(() => {
      lbStatus.textContent = 'Không thể kết nối máy chủ để lấy BXH.';
    });
}
function resetGameToMenu() {
  // reset state tối thiểu để chơi lại
  towers = [];
  enemies = [];
  bullets = [];
  gold = 100;
  lives = 20;

  currentWave = 0;
  waveSpawnTimer = 0;
  waveSpawned = 0;
  waveActive = false;

  gameStartedAt = null;
  enemiesKilled = 0;
  runSubmitted = false;

  gameState = 'menu';
  document.getElementById('winOverlay').classList.add('hidden');
}

function endRunAndSubmit(isWin) {
  if (runSubmitted) return;
  runSubmitted = true;

  const finishedAt = performance.now();
  const durationSec = Math.round((finishedAt - (gameStartedAt || finishedAt)) / 1000);
  const goldLeft = gold;

  // điểm = quái diệt × 10 + vàng × 1 – thời gian × 0.5
  const score = Math.max(0, Math.round(enemiesKilled * 10 + goldLeft - durationSec * 0.5));

  // Hiện overlay (dù thắng hay thua – nếu muốn riêng UI khi thua thì tách thêm)
  showWinOverlay(score);

  // Gửi điểm lên server (chỉ lưu được nếu đã đăng nhập)
  fetch('api/save_score.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({
      score,
      enemies_killed: enemiesKilled,
      gold_left: goldLeft,
      duration_seconds: durationSec
    })
  }).catch(() => {
    // Không cần báo lỗi cho user ở đây, overlay vẫn hiển thị bình thường
  });
}

// Helper: khoảng cách điểm - đoạn
function pointToSegmentDistance(px, py, x1, y1, x2, y2) {
  const A = px - x1, B = py - y1, C = x2 - x1, D = y2 - y1;
  const dot = A * C + B * D;
  const len_sq = C * C + D * D;
  let param = -1;
  if (len_sq !== 0) param = dot / len_sq;
  let xx, yy;
  if (param < 0) { xx = x1; yy = y1; }
  else if (param > 1) { xx = x2; yy = y2; }
  else { xx = x1 + param * C; yy = y1 + param * D; }
  return distance(px, py, xx, yy);
}

// --- Game Loop ---
let lastTime = 0;
function loop(currentTime) {
  if (lives <= 0 && gameState === 'running' && !runSubmitted) {
  gameState = 'lose';
  endRunAndSubmit(false);
 }
  if (gameState !== 'running') {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    drawMap();
    towers.forEach(drawTower);
    enemies.forEach(drawEnemy);
    bullets.forEach(drawBullet);
    drawGoldPickups();
    drawHitEffects();
    drawUI();
    requestAnimationFrame(loop);
    return;
  }

  const deltaTime = (currentTime - lastTime) / 1000;
  lastTime = currentTime;

  updateTowers(deltaTime);
  updateBullets(deltaTime);
  updateEnemies(deltaTime);
  updateGoldPickups(deltaTime);

  // Wave spawn logic (giữ nguyên)
  // Wave spawn logic
if (currentWave < waves.length) {
  if (!waveActive) { waveActive = true; waveSpawnTimer = 0; waveSpawned = 0; }
  if (waveActive) {
    waveSpawnTimer += deltaTime;
    const cfg = waves[currentWave];
    if (waveSpawned < cfg.count && waveSpawnTimer >= cfg.spawnInterval) {
      waveSpawnTimer = 0;
      const start = { x: PATH[0].x * scaleX, y: PATH[0].y * scaleY };
      enemies.push({
        x: start.x, y: start.y, waypointIndex: 1,
        speed: ENEMY_BASE_SPEED, hp: 100, maxHp: 100, goldValue: 1
      });
      waveSpawned++;
    }
    if (waveSpawned >= cfg.count && enemies.length === 0) {
      waveActive = false;
      currentWave++;
    }
  }
}

// === WIN check: đã qua hết waves, không còn quái, không còn wave đang spawn ===
if (gameState === 'running' && !runSubmitted &&
    currentWave >= waves.length &&
    enemies.length === 0 &&
    !waveActive) {
  gameState = 'win';
  endRunAndSubmit(true);
}


  // Vẽ
  drawMap();
  towers.forEach(drawTower);
  enemies.forEach(drawEnemy);
  bullets.forEach(drawBullet);
  drawGoldPickups();
  drawHitEffects();

  // --- Preview đặt tháp: snap tâm ô lưới ---
  if (selectedTowerType !== null) {
    const cellW = BASE_GRID_SIZE * scaleX, cellH = BASE_GRID_SIZE * scaleY;
    const col = Math.floor(mouseX / cellW);
    const row = Math.floor(mouseY / cellH);
    const snappedX = col * cellW + cellW / 2;
    const snappedY = row * cellH + cellH / 2;

    const tt = towerTypes[selectedTowerType];
    ctx.fillStyle = 'rgba(100,150,255,0.2)';
    ctx.beginPath(); ctx.arc(snappedX, snappedY, tt.range, 0, Math.PI*2); ctx.fill();
    const drawSize = 30 * ((scaleX + scaleY) / 2);
    let img = assets.tower;
    if (selectedTowerType === 1) img = assets.tower2;
    if (selectedTowerType === 2) img = assets.tower3;
    ctx.drawImage(img, snappedX - drawSize/2, snappedY - drawSize/2, drawSize, drawSize);
  }

  // Panel nâng cấp (giữ nguyên)
  if (selectedTowerForUpgrade !== null) {
    const t = towers[selectedTowerForUpgrade];
    const panelW = 200, panelH = 140;
    const px = Math.min(t.x + 20, canvas.width - panelW - 10);
    const py = Math.max(10, t.y - panelH - 10);
    ctx.fillStyle = 'rgba(220,220,220,0.95)';
    ctx.fillRect(px, py, panelW, panelH);
    ctx.strokeStyle = 'black';
    ctx.strokeRect(px, py, panelW, panelH);
    ctx.fillStyle = 'black';
    ctx.font = '14px Arial';
    ctx.fillText(`Cấp Tháp: ${t.level||1}`, px + 10, py + 20);
    ctx.fillText(`Sát thương: ${t.damage}`, px + 10, py + 40);
    ctx.fillText(`Tầm bắn: ${Math.round(t.range)}`, px + 10, py + 60);
    ctx.fillText(`Tốc độ bắn: ${t.fireRate}`, px + 10, py + 80);
    const btnW = 80, btnH = 26;
    ctx.fillStyle = 'lightgreen';
    ctx.fillRect(px + 10, py + 90, btnW, btnH);
    ctx.fillStyle = 'black'; ctx.fillText('Tăng Sát Thương', px + 12, py + 108);
    ctx.fillStyle = 'lightgreen';
    ctx.fillRect(px + 100, py + 90, btnW, btnH);
    ctx.fillStyle = 'black'; ctx.fillText('Tăng Tầm Bắn', px + 102, py + 108);
  }

  drawUI();
  requestAnimationFrame(loop);
}

// --- Mouse + Input ---
canvas.addEventListener('mousemove', (e) => {
  mouseX = e.offsetX; mouseY = e.offsetY;
});

canvas.addEventListener('pointerdown', function onFirstPointer() {
  try { unlockAudio(); } catch (e) {}
  canvas.removeEventListener('pointerdown', onFirstPointer);
});

canvas.addEventListener('click', (e) => {
  if (lives <= 0) return;
  const x = e.offsetX, y = e.offsetY;

  // Menu start
  if (gameState === 'menu') {
    const bw = 220, bh = 60;
    const bx = canvas.width/2 - bw/2, by = canvas.height/2 + 10;
    if (x >= bx && x <= bx + bw && y >= by && y <= by + bh) {
      try { unlockAudio(); } catch (e) {}
      gameState = 'running';
      startRun();
    }
    return;
  }
  // Paused resume
  if (gameState === 'paused') {
    const bx = canvas.width/2 - 110, by = canvas.height/2 + 20, bw = 220, bh = 50;
    if (x >= bx && x <= bx + bw && y >= by && y <= by + bh) gameState = 'running';
    return;
  }

  // Panel chọn tháp
  const panelW = SIDEBAR_WIDTH;
  const panelX = canvas.width - panelW - 10;
  const panelY = 10;
  const panelH = 232;
  if (x >= panelX && x <= panelX + panelW && y >= panelY && y <= panelY + panelH) {
    const relY = y - (panelY + 15);
    const slotHeight = 72;
    const idx = Math.floor(relY / slotHeight);
    if (idx >= 0 && idx < towerTypes.length) selectedTowerType = idx;
    return;
  }

  // Click vào tháp -> mở panel nâng cấp
  for (let i = 0; i < towers.length; i++) {
    const t = towers[i];
    const drawSize = 30 * ((scaleX + scaleY) / 2);
    if (distance(x, y, t.x, t.y) <= drawSize/2 + 6) { selectedTowerForUpgrade = i; return; }
  }

  // Đặt tháp: snap tâm ô lưới
  if (selectedTowerType === null) return;
  const cellW = BASE_GRID_SIZE * scaleX, cellH = BASE_GRID_SIZE * scaleY;
  const col = Math.floor(x / cellW);
  const row = Math.floor(y / cellH);
  const snappedX = col * cellW + cellW / 2;
  const snappedY = row * cellH + cellH / 2;

  // Cấm đặt quá gần đường (dựa trên PATH pixel như cũ)
  const scaledPath = PATH.map(p => ({ x: p.x * scaleX, y: p.y * scaleY }));
  let tooNear = false;
  for (let i = 0; i < scaledPath.length - 1; i++) {
    const d = pointToSegmentDistance(snappedX, snappedY, scaledPath[i].x, scaledPath[i].y, scaledPath[i+1].x, scaledPath[i+1].y);
    if (d < 30 * ((scaleX + scaleY)/2)) { tooNear = true; break; }
  }
  if (tooNear) { alert('Không thể đặt tháp quá gần đường đi!'); return; }

  // Cấm đặt chồng (cùng tâm ô)
  const isOccupied = towers.some(t => Math.abs(t.x - snappedX) < 1 && Math.abs(t.y - snappedY) < 1);
  if (isOccupied) { alert('Ô này đã có tháp rồi!'); return; }

  const tt = towerTypes[selectedTowerType];
  if (gold < tt.cost) { alert('Không đủ vàng để mua tháp!'); return; }

  towers.push({ x: snappedX, y: snappedY, range: tt.range, damage: tt.damage, fireRate: tt.fireRate, cooldown: 0, type: tt.id });
  gold -= tt.cost;
  selectedTowerType = null;
  selectedTowerForUpgrade = null;
});

// Đóng panel nâng cấp bằng double click
canvas.addEventListener('dblclick', () => { selectedTowerForUpgrade = null; });

// Click vào nút trong panel nâng cấp
canvas.addEventListener('mousedown', (e) => {
  if (selectedTowerForUpgrade === null) return;
  const x = e.offsetX, y = e.offsetY;
  const t = towers[selectedTowerForUpgrade];
  const panelW = 200, panelH = 140;
  const px = Math.min(t.x + 20, canvas.width - panelW - 10);
  const py = Math.max(10, t.y - panelH - 10);
  // dmg
  if (x >= px + 10 && x <= px + 90 && y >= py + 90 && y <= py + 116) {
    const cost = Math.round((t.damage || 10) * 0.5);
    if (gold >= cost) { gold -= cost; t.damage = Math.round((t.damage || 10) * 1.4); t.level = (t.level || 1) + 1; }
    else alert('Không đủ vàng để nâng cấp!');
    return;
  }
  // range
  if (x >= px + 100 && x <= px + 180 && y >= py + 90 && y <= py + 116) {
    const cost = Math.round((t.range || 100) * 0.2);
    if (gold >= cost) { gold -= cost; t.range = (t.range || 100) * 1.18; t.level = (t.level || 1) + 1; }
    else alert('Không đủ vàng để nâng cấp!');
    return;
  }
});

// --- Khởi động ---
updateCanvasSize();
window.addEventListener('resize', updateCanvasSize);

console.log("Đang tải tài nguyên...");
loadAssets().then(() => {
  console.log("Bắt đầu game...");
  try {
    if (bgAudio && bgAudio.play) {
      bgAudio.volume = 0.25;
      bgAudio.play().catch(() => {/* autoplay blocked */});
    }
  } catch (e) {}
  loop(0);
});

