<?php
/**
 * WiFi QR Code Generator
 * Scan the QR code with a phone camera and it joins the network
 * without anyone typing the password.
 *
 * Nothing is stored: the password is only used to build the QR payload
 * for the current request (POST, so it never appears in URLs or logs).
 */

// Escape special characters required by the WIFI: QR format
function wifi_escape(string $s): string
{
    return preg_replace('/([\\\\;,:"])/', '\\\\$1', $s);
}

$ssid     = '';
$password = '';
$security = 'WPA';
$hidden   = false;
$payload  = null;
$error    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ssid     = trim($_POST['ssid'] ?? '');
    $password = $_POST['password'] ?? '';
    $security = $_POST['security'] ?? 'WPA';
    $hidden   = isset($_POST['hidden']);

    if (!in_array($security, ['WPA', 'WEP', 'nopass'], true)) {
        $security = 'WPA';
    }

    if ($ssid === '') {
        $error = 'Please enter your hotspot name (SSID).';
    } elseif (strlen($ssid) > 32) {
        $error = 'SSID cannot be longer than 32 characters.';
    } elseif ($security !== 'nopass' && $password === '') {
        $error = 'Enter the password, or choose "No password (open network)".';
    } elseif ($security === 'WPA' && (strlen($password) < 8 || strlen($password) > 63)) {
        $error = 'WPA passwords must be 8 to 63 characters.';
    } else {
        $payload = 'WIFI:T:' . $security
                 . ';S:' . wifi_escape($ssid)
                 . ';P:' . ($security === 'nopass' ? '' : wifi_escape($password))
                 . ';H:' . ($hidden ? 'true' : 'false')
                 . ';;';
    }
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WiFi QR Code Generator</title>
<style>
  :root {
    --bg: #f1f5f9;
    --card: #ffffff;
    --ink: #0f172a;
    --muted: #64748b;
    --brand: #2563eb;
    --brand-dark: #1d4ed8;
    --border: #e2e8f0;
    --danger: #dc2626;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--ink);
    line-height: 1.5;
  }
  .wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 32px 16px 64px;
  }
  header h1 { margin: 0 0 4px; font-size: 1.75rem; }
  header p  { margin: 0 0 24px; color: var(--muted); }

  .grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    align-items: start;
  }
  @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } }

  .panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
  }
  label { display: block; font-weight: 600; margin: 16px 0 6px; }
  label:first-of-type { margin-top: 0; }
  input[type=text], input[type=password], select {
    width: 100%;
    padding: 11px 12px;
    font-size: 1rem;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: #fff;
    color: var(--ink);
  }
  input:focus, select:focus {
    outline: 2px solid var(--brand);
    outline-offset: 1px;
  }
  .row { display: flex; gap: 8px; }
  .row input { flex: 1; }
  .ghost {
    border: 1px solid var(--border);
    background: #fff;
    border-radius: 10px;
    padding: 0 14px;
    cursor: pointer;
    font-size: .9rem;
  }
  .check { display: flex; align-items: center; gap: 8px; margin-top: 16px; font-weight: 500; }
  .check input { width: 18px; height: 18px; }

  .btn {
    display: inline-block;
    width: 100%;
    margin-top: 24px;
    padding: 13px 16px;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    background: var(--brand);
    border: 0;
    border-radius: 10px;
    cursor: pointer;
  }
  .btn:hover { background: var(--brand-dark); }
  .btn.secondary { background: #0f172a; margin-top: 12px; }
  .btn.outline {
    background: #fff; color: var(--ink);
    border: 1px solid var(--border); margin-top: 12px;
  }
  .error {
    background: #fef2f2; color: var(--danger);
    border: 1px solid #fecaca;
    padding: 10px 12px; border-radius: 10px; margin-bottom: 16px;
  }
  .hint { color: var(--muted); font-size: .85rem; margin-top: 6px; }

  /* ---------- Printable card ---------- */
  .empty { color: var(--muted); text-align: center; padding: 48px 8px; }
  .card {
    text-align: center;
    border: 2px dashed var(--border);
    border-radius: 16px;
    padding: 28px 20px;
  }
  .card h2 { margin: 0 0 4px; font-size: 1.4rem; }
  .card .sub { margin: 0 0 18px; color: var(--muted); }
  #qrcode { display: inline-block; padding: 14px; background: #fff; border-radius: 12px; }
  #qrcode img, #qrcode canvas { display: block; }
  .card .network { margin-top: 18px; font-size: 1.1rem; }
  .card .network strong { font-size: 1.25rem; }
  .card .steps { margin: 16px auto 0; max-width: 320px; text-align: left; color: var(--muted); font-size: .92rem; }
  .card .steps li { margin-bottom: 2px; }

  .sizes { display: flex; gap: 8px; margin-top: 16px; }
  .sizes button {
    flex: 1; padding: 9px; border-radius: 10px; cursor: pointer;
    border: 1px solid var(--border); background: #fff; font-size: .9rem;
  }
  .sizes button.active { background: var(--brand); color: #fff; border-color: var(--brand); }

  .note {
    margin-top: 24px; padding: 14px 16px;
    background: #fffbeb; border: 1px solid #fde68a;
    border-radius: 12px; font-size: .9rem; color: #78350f;
  }

  /* ---------- Print ---------- */
  @media print {
    @page { margin: 12mm; }
    body { background: #fff; }
    header, .form-panel, .actions, .sizes, .note, .no-print { display: none !important; }
    .wrap { padding: 0; max-width: none; }
    .grid { display: block; }
    .panel { border: 0; padding: 0; }
    .card { border: 2px solid #000; page-break-inside: avoid; margin: 0 auto; max-width: 140mm; }
    .card .sub, .card .steps { color: #000; }
  }
</style>
</head>
<body>
<div class="wrap">

  <header>
    <h1>WiFi QR Code Generator</h1>
    <p>Scan the code with a phone camera and join your hotspot instantly, with no typing.</p>
  </header>

  <div class="grid">

    <!-- ===== Form ===== -->
    <section class="panel form-panel">
      <?php if ($error): ?>
        <div class="error" role="alert"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" action="" autocomplete="off">
        <label for="ssid">Hotspot name (SSID)</label>
        <input type="text" id="ssid" name="ssid" maxlength="32"
               value="<?= h($ssid) ?>" placeholder="My Hotspot" required>

        <label for="security">Security type</label>
        <select id="security" name="security">
          <option value="WPA"    <?= $security === 'WPA'    ? 'selected' : '' ?>>WPA / WPA2 / WPA3 (recommended)</option>
          <option value="WEP"    <?= $security === 'WEP'    ? 'selected' : '' ?>>WEP (legacy)</option>
          <option value="nopass" <?= $security === 'nopass' ? 'selected' : '' ?>>No password (open network)</option>
        </select>

        <div id="pwField">
          <label for="password">Password</label>
          <div class="row">
            <input type="password" id="password" name="password"
                   value="<?= h($password) ?>" placeholder="Hotspot password">
            <button type="button" class="ghost" id="togglePw">Show</button>
          </div>
          <p class="hint">The password is embedded in the QR code so phones can join automatically. It is never stored on the server.</p>
        </div>

        <label class="check">
          <input type="checkbox" name="hidden" <?= $hidden ? 'checked' : '' ?>>
          Hidden network
        </label>

        <button class="btn" type="submit">Generate QR code</button>
      </form>
    </section>

    <!-- ===== Result ===== -->
    <section class="panel">
      <?php if ($payload): ?>
        <div class="card" id="printCard">
          <h2>Scan to connect to WiFi</h2>
          <p class="sub">Point your phone camera at the code</p>
          <div id="qrcode"></div>
          <div class="network">
            Network: <strong><?= h($ssid) ?></strong>
          </div>
          <ol class="steps">
            <li>Open your phone's camera</li>
            <li>Point it at the QR code</li>
            <li>Tap the "Join network" prompt</li>
          </ol>
        </div>

        <div class="sizes no-print" id="sizes">
          <button type="button" data-size="200">Small</button>
          <button type="button" data-size="280" class="active">Medium</button>
          <button type="button" data-size="360">Large</button>
        </div>

        <div class="actions">
          <button class="btn secondary" type="button" id="printBtn">Print QR code</button>
          <button class="btn outline" type="button" id="downloadBtn">Download PNG</button>
        </div>

        <div class="note">
          <strong>Heads up:</strong> anyone who has the QR code (or a printed copy) can join the
          network. Keep printed copies somewhere appropriate and regenerate the code if you change
          your password. Not all phones prompt on scan: iPhone (iOS 11+) and Android 10+ do; some
          older Android versions need a scanner app.
        </div>
      <?php else: ?>
        <div class="empty">
          Your QR code will appear here after you fill in the form.
        </div>
      <?php endif; ?>
    </section>

  </div>
</div>

<?php if ($payload): ?>
<!-- Client-side QR library (loaded from CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  // Payload built and escaped safely on the server
  const WIFI_PAYLOAD = <?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
  const SSID_NAME    = <?= json_encode($ssid, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>

<script>
  // Show/hide password
  const pw = document.getElementById('password');
  const toggle = document.getElementById('togglePw');
  toggle.addEventListener('click', () => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
  });

  // Hide the password field for open networks
  const security = document.getElementById('security');
  const pwField  = document.getElementById('pwField');
  function syncSecurity() {
    const open = security.value === 'nopass';
    pwField.style.display = open ? 'none' : 'block';
    pw.required = !open;
  }
  security.addEventListener('change', syncSecurity);
  syncSecurity();

  // QR rendering, sizing, print and download
  if (typeof WIFI_PAYLOAD !== 'undefined') {
    const box = document.getElementById('qrcode');
    let currentSize = 280;

    function render(size) {
      currentSize = size;
      box.innerHTML = '';
      new QRCode(box, {
        text: WIFI_PAYLOAD,
        width: size,
        height: size,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H   // high error correction: survives print wear
      });
    }
    render(currentSize);

    document.querySelectorAll('#sizes button').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#sizes button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        render(parseInt(btn.dataset.size, 10));
      });
    });

    document.getElementById('printBtn').addEventListener('click', () => window.print());

    document.getElementById('downloadBtn').addEventListener('click', () => {
      const canvas = box.querySelector('canvas');
      if (!canvas) return;
      // Add a white quiet-zone border around the code for reliable scanning
      const pad = 24;
      const out = document.createElement('canvas');
      out.width = out.height = canvas.width + pad * 2;
      const ctx = out.getContext('2d');
      ctx.fillStyle = '#fff';
      ctx.fillRect(0, 0, out.width, out.height);
      ctx.drawImage(canvas, pad, pad);
      const a = document.createElement('a');
      a.download = 'wifi-qr-' + SSID_NAME.replace(/[^\w-]+/g, '_') + '.png';
      a.href = out.toDataURL('image/png');
      a.click();
    });
  }
</script>
</body>
</html>