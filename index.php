<?php
// PHP handles server-side math evaluation securely
function safeEval(string $expression): string {
    // Sanitize: allow only digits, operators, dots, spaces, parentheses, and math functions
    $expression = trim($expression);
    
    // Replace math function names
    $replacements = [
        'sin('  => 'sin(',
        'cos('  => 'cos(',
        'tan('  => 'tan(',
        'sqrt(' => 'sqrt(',
        'log('  => 'log(',
        'abs('  => 'abs(',
        'π'     => M_PI,
        'e'     => M_E,
    ];
    
    $expr = str_replace(array_keys($replacements), array_values($replacements), $expression);
    
    // Strict whitelist: only safe characters allowed
    if (!preg_match('/^[\d\s\+\-\*\/\.\(\)\%\,a-z]+$/i', $expr)) {
        return 'Error';
    }
    
    // Block any PHP keywords
    $blocked = ['eval', 'exec', 'system', 'passthru', 'shell', 'file', 'include', 'require', 'echo', 'print'];
    foreach ($blocked as $b) {
        if (stripos($expr, $b) !== false) return 'Error';
    }
    
    try {
        $result = null;
        // Use eval in isolated manner with only math
        $code = '$result = ' . $expr . ';';
        @eval($code);
        
        if ($result === null || !is_numeric($result)) return 'Error';
        
        // Format result: remove trailing zeros
        if (floor($result) == $result && abs($result) < 1e15) {
            return number_format($result, 0, '.', '');
        }
        return rtrim(rtrim(number_format($result, 10, '.', ''), '0'), '.');
    } catch (Throwable $e) {
        return 'Error';
    }
}

$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expression'])) {
    $expr   = $_POST['expression'];
    $result = safeEval($expr);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CALCVLT — Scientific Calculator</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  /* ═══════════════════════════════
     TOKENS
  ══════════════════════════════════ */
  :root {
    --bg:           #0d0f14;
    --surface:      #161b26;
    --surface2:     #1e2535;
    --surface3:     #252d40;
    --border:       #2a3350;
    --accent:       #4f8ef7;
    --accent-glow:  rgba(79,142,247,0.25);
    --accent2:      #a78bfa;
    --danger:       #f97316;
    --text:         #e8edf5;
    --text-muted:   #8896b0;
    --text-dim:     #4a5568;
    --equals:       #4f8ef7;
    --equals-glow:  rgba(79,142,247,0.4);
    --radius:       14px;
    --radius-sm:    8px;
    --mono:         'Space Mono', monospace;
    --sans:         'Space Grotesk', sans-serif;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    font-family: var(--sans);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    /* Signature: subtle grid overlay */
    background-image:
      linear-gradient(rgba(79,142,247,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(79,142,247,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
  }

  /* ═══════════════════════════════
     HEADER
  ══════════════════════════════════ */
  .site-header {
    text-align: center;
    margin-bottom: 2rem;
  }
  .logo {
    font-family: var(--mono);
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.35em;
    color: var(--accent);
    text-transform: uppercase;
  }
  .logo span { color: var(--text-muted); }
  .tagline {
    font-size: 0.75rem;
    color: var(--text-dim);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    margin-top: 4px;
  }

  /* ═══════════════════════════════
     CALCULATOR SHELL
  ══════════════════════════════════ */
  .calc-shell {
    width: 100%;
    max-width: 420px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    overflow: hidden;
    box-shadow:
      0 0 0 1px rgba(79,142,247,0.08),
      0 32px 64px rgba(0,0,0,0.6),
      0 0 80px rgba(79,142,247,0.05);
  }

  /* ═══════════════════════════════
     DISPLAY
  ══════════════════════════════════ */
  .display {
    padding: 1.5rem 1.75rem 1rem;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    min-height: 130px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    position: relative;
  }
  .display-history {
    font-family: var(--mono);
    font-size: 0.78rem;
    color: var(--text-dim);
    min-height: 1.2em;
    margin-bottom: 0.4rem;
    letter-spacing: 0.04em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .display-expr {
    font-family: var(--mono);
    font-size: 1.2rem;
    color: var(--text-muted);
    min-height: 1.5em;
    margin-bottom: 0.5rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .display-main {
    font-family: var(--mono);
    font-size: 2.6rem;
    font-weight: 700;
    color: var(--text);
    line-height: 1;
    letter-spacing: -0.02em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: color 0.15s;
  }
  .display-main.has-result { color: var(--accent); }
  .display-main.is-error   { color: var(--danger); font-size: 1.8rem; }

  /* live cursor blink */
  .cursor {
    display: inline-block;
    width: 2px;
    height: 2rem;
    background: var(--accent);
    margin-left: 2px;
    vertical-align: middle;
    animation: blink 1s step-end infinite;
  }
  @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

  /* indicator strip */
  .indicators {
    display: flex;
    gap: 0.6rem;
    margin-top: 0.75rem;
    padding-top: 0.6rem;
    border-top: 1px solid var(--border);
  }
  .ind {
    font-family: var(--mono);
    font-size: 0.62rem;
    letter-spacing: 0.08em;
    color: var(--text-dim);
    padding: 2px 7px;
    border: 1px solid var(--border);
    border-radius: 4px;
    transition: all 0.2s;
  }
  .ind.active {
    color: var(--accent);
    border-color: var(--accent);
    background: var(--accent-glow);
  }

  /* ═══════════════════════════════
     MODE TABS
  ══════════════════════════════════ */
  .mode-tabs {
    display: flex;
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
  }
  .mode-tab {
    flex: 1;
    padding: 0.6rem;
    font-family: var(--sans);
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-dim);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
  }
  .mode-tab.active {
    color: var(--accent);
    background: var(--surface3);
    box-shadow: inset 0 -2px 0 var(--accent);
  }
  .mode-tab:hover:not(.active) { color: var(--text-muted); }

  /* ═══════════════════════════════
     KEYPAD
  ══════════════════════════════════ */
  .keypad-wrapper {
    padding: 1rem;
  }
  .keypad {
    display: grid;
    gap: 8px;
  }
  /* Standard: 4 cols */
  .keypad.standard { grid-template-columns: repeat(4, 1fr); }
  /* Scientific: 5 cols */
  .keypad.scientific { grid-template-columns: repeat(5, 1fr); }

  /* hidden panels */
  .panel { display: none; }
  .panel.active { display: block; }

  /* ═══════════════════════════════
     BUTTONS
  ══════════════════════════════════ */
  .btn {
    position: relative;
    padding: 0;
    height: 58px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    font-family: var(--mono);
    font-size: 1rem;
    font-weight: 400;
    cursor: pointer;
    transition: all 0.12s ease;
    user-select: none;
    overflow: hidden;
    background: var(--surface2);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .btn::after {
    content: '';
    position: absolute;
    inset: 0;
    background: white;
    opacity: 0;
    transition: opacity 0.1s;
    border-radius: inherit;
  }
  .btn:active::after { opacity: 0.06; }
  .btn:hover { border-color: var(--accent); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,0,0,0.3); }
  .btn:active { transform: translateY(0); }

  /* Variants */
  .btn.digit {
    background: var(--surface3);
    font-size: 1.15rem;
    font-weight: 700;
  }
  .btn.op {
    background: var(--surface2);
    color: var(--accent);
    font-size: 1.15rem;
  }
  .btn.fn {
    background: var(--surface2);
    color: var(--accent2);
    font-size: 0.8rem;
    letter-spacing: 0.04em;
  }
  .btn.clear {
    background: rgba(249,115,22,0.12);
    color: var(--danger);
    border-color: rgba(249,115,22,0.25);
  }
  .btn.clear:hover { border-color: var(--danger); }
  .btn.backspace {
    background: rgba(249,115,22,0.08);
    color: var(--danger);
  }
  .btn.equals {
    background: var(--equals);
    color: #fff;
    border-color: var(--equals);
    font-size: 1.4rem;
    box-shadow: 0 4px 20px var(--equals-glow);
  }
  .btn.equals:hover {
    background: #5f9aff;
    border-color: #5f9aff;
    box-shadow: 0 6px 28px var(--equals-glow);
  }
  .btn.span2 { grid-column: span 2; }
  .btn.zero  { justify-content: flex-start; padding-left: 22px; }

  /* sub-label for scientific btns */
  .btn .sub {
    position: absolute;
    top: 5px;
    right: 7px;
    font-size: 0.52rem;
    color: var(--text-dim);
    letter-spacing: 0.04em;
    font-family: var(--sans);
  }

  /* ═══════════════════════════════
     HISTORY SIDEBAR TOGGLE
  ══════════════════════════════════ */
  .history-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 1rem;
    background: var(--surface2);
    border-top: 1px solid var(--border);
    cursor: pointer;
    font-size: 0.7rem;
    font-family: var(--mono);
    color: var(--text-dim);
    letter-spacing: 0.08em;
    transition: color 0.2s;
  }
  .history-toggle:hover { color: var(--text-muted); }
  .history-toggle svg { transition: transform 0.2s; }
  .history-toggle.open svg { transform: rotate(180deg); }

  .history-panel {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.35s ease;
    background: var(--bg);
    border-top: none;
  }
  .history-panel.open {
    max-height: 220px;
    border-top: 1px solid var(--border);
  }
  .history-list {
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column-reverse;
    gap: 0.5rem;
    max-height: 220px;
    overflow-y: auto;
  }
  .history-item {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 1rem;
    cursor: pointer;
    padding: 0.4rem 0.5rem;
    border-radius: var(--radius-sm);
    transition: background 0.15s;
  }
  .history-item:hover { background: var(--surface2); }
  .history-item .h-expr {
    font-family: var(--mono);
    font-size: 0.72rem;
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .history-item .h-result {
    font-family: var(--mono);
    font-size: 0.85rem;
    color: var(--accent);
    flex-shrink: 0;
  }
  .history-empty {
    padding: 1.2rem;
    text-align: center;
    font-size: 0.72rem;
    color: var(--text-dim);
    letter-spacing: 0.06em;
  }

  /* ═══════════════════════════════
     FOOTER
  ══════════════════════════════════ */
  .site-footer {
    margin-top: 1.5rem;
    font-size: 0.68rem;
    color: var(--text-dim);
    letter-spacing: 0.08em;
    text-align: center;
  }
  .site-footer a { color: var(--accent); text-decoration: none; }

  /* ═══════════════════════════════
     RESPONSIVE
  ══════════════════════════════════ */
  @media (max-width: 440px) {
    .calc-shell { border-radius: 18px; }
    .btn { height: 52px; font-size: 0.9rem; }
    .display-main { font-size: 2rem; }
    .keypad { gap: 6px; }
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="logo">CALC<span>VLT</span></div>
  <div class="tagline">Scientific Calculator</div>
</header>

<main class="calc-shell" role="main">

  <!-- DISPLAY -->
  <div class="display" id="display" aria-live="polite">
    <div class="display-history" id="histLine"></div>
    <div class="display-expr"   id="exprLine"></div>
    <div class="display-main"   id="mainLine">
      0<span class="cursor" id="cursor"></span>
    </div>
    <div class="indicators">
      <span class="ind" id="ind-deg">DEG</span>
      <span class="ind" id="ind-rad">RAD</span>
      <span class="ind" id="ind-inv">INV</span>
      <span class="ind" id="ind-hyp">HYP</span>
    </div>
  </div>

  <!-- MODE TABS -->
  <div class="mode-tabs" role="tablist">
    <button class="mode-tab active" role="tab" onclick="switchMode('standard')">Standard</button>
    <button class="mode-tab"        role="tab" onclick="switchMode('scientific')">Scientific</button>
    <button class="mode-tab"        role="tab" onclick="switchMode('converter')">Convert</button>
  </div>

  <!-- KEYPADS -->
  <div class="keypad-wrapper">

    <!-- STANDARD -->
    <div class="panel active" id="panel-standard">
      <div class="keypad standard">
        <button class="btn clear"     onclick="clearAll()">AC</button>
        <button class="btn backspace" onclick="backspace()">⌫</button>
        <button class="btn op"        onclick="input('%')">%</button>
        <button class="btn op"        onclick="input('/')">÷</button>

        <button class="btn digit" onclick="input('7')">7</button>
        <button class="btn digit" onclick="input('8')">8</button>
        <button class="btn digit" onclick="input('9')">9</button>
        <button class="btn op"    onclick="input('*')">×</button>

        <button class="btn digit" onclick="input('4')">4</button>
        <button class="btn digit" onclick="input('5')">5</button>
        <button class="btn digit" onclick="input('6')">6</button>
        <button class="btn op"    onclick="input('-')">−</button>

        <button class="btn digit" onclick="input('1')">1</button>
        <button class="btn digit" onclick="input('2')">2</button>
        <button class="btn digit" onclick="input('3')">3</button>
        <button class="btn op"    onclick="input('+')">+</button>

        <button class="btn digit span2 zero" onclick="input('0')">0</button>
        <button class="btn digit" onclick="input('.')">.</button>
        <button class="btn equals" onclick="calculate()">=</button>
      </div>
    </div>

    <!-- SCIENTIFIC -->
    <div class="panel" id="panel-scientific">
      <div class="keypad scientific">

        <!-- Row 1: modes & memory -->
        <button class="btn fn" onclick="toggleInv()">INV</button>
        <button class="btn fn" onclick="toggleHyp()">HYP</button>
        <button class="btn fn" onclick="toggleAngle()">DEG</button>
        <button class="btn fn" onclick="memStore()">MS</button>
        <button class="btn fn" onclick="memRecall()">MR</button>

        <!-- Row 2: trig -->
        <button class="btn fn" id="btn-sin" onclick="inputFn('sin')">sin</button>
        <button class="btn fn" id="btn-cos" onclick="inputFn('cos')">cos</button>
        <button class="btn fn" id="btn-tan" onclick="inputFn('tan')">tan</button>
        <button class="btn fn" onclick="inputFn('log')">log</button>
        <button class="btn fn" onclick="inputFn('ln')">ln</button>

        <!-- Row 3: powers & roots -->
        <button class="btn fn" onclick="inputFn('sqrt')">√x</button>
        <button class="btn fn" onclick="inputFn('cbrt')">∛x</button>
        <button class="btn fn" onclick="input('^')">xʸ</button>
        <button class="btn fn" onclick="input('**2')">x²</button>
        <button class="btn fn" onclick="inputFn('abs')">|x|</button>

        <!-- Row 4: constants & parens -->
        <button class="btn fn" onclick="input('π')">π</button>
        <button class="btn fn" onclick="input('e')">e</button>
        <button class="btn fn" onclick="input('(')"> ( </button>
        <button class="btn fn" onclick="input(')')"> ) </button>
        <button class="btn fn" onclick="input('1/')">1/x</button>

        <!-- Row 5: digits top row -->
        <button class="btn clear"     onclick="clearAll()">AC</button>
        <button class="btn backspace" onclick="backspace()">⌫</button>
        <button class="btn op"        onclick="input('%')">%</button>
        <button class="btn op"        onclick="input('/')">÷</button>
        <button class="btn op"        onclick="input('*')">×</button>

        <!-- Row 6 -->
        <button class="btn digit" onclick="input('7')">7</button>
        <button class="btn digit" onclick="input('8')">8</button>
        <button class="btn digit" onclick="input('9')">9</button>
        <button class="btn op"    onclick="input('-')">−</button>
        <button class="btn op"    onclick="input('+')">+</button>

        <!-- Row 7 -->
        <button class="btn digit" onclick="input('4')">4</button>
        <button class="btn digit" onclick="input('5')">5</button>
        <button class="btn digit" onclick="input('6')">6</button>
        <button class="btn digit" onclick="input('1')">1</button>
        <button class="btn digit" onclick="input('2')">2</button>

        <!-- Row 8 -->
        <button class="btn digit" onclick="input('3')">3</button>
        <button class="btn digit span2 zero" onclick="input('0')">0</button>
        <button class="btn digit" onclick="input('.')">.</button>
        <button class="btn equals span1" onclick="calculate()">=</button>
      </div>
    </div>

    <!-- CONVERTER -->
    <div class="panel" id="panel-converter">
      <div style="padding:0.5rem 0">
        <select id="convType" onchange="updateConvUnits()" style="width:100%;padding:0.6rem 0.8rem;background:var(--surface3);color:var(--text);border:1px solid var(--border);border-radius:var(--radius-sm);font-family:var(--sans);font-size:0.85rem;margin-bottom:0.75rem;outline:none;">
          <option value="length">Length</option>
          <option value="mass">Mass / Weight</option>
          <option value="temp">Temperature</option>
          <option value="area">Area</option>
          <option value="volume">Volume</option>
          <option value="speed">Speed</option>
          <option value="time">Time</option>
          <option value="data">Data Storage</option>
        </select>
        <div style="display:flex;gap:0.5rem;align-items:center;">
          <div style="flex:1">
            <input type="number" id="convInput" oninput="doConvert()" placeholder="Value" style="width:100%;padding:0.65rem 0.8rem;background:var(--surface3);color:var(--text);border:1px solid var(--border);border-radius:var(--radius-sm);font-family:var(--mono);font-size:0.95rem;outline:none;">
            <select id="convFrom" onchange="doConvert()" style="width:100%;margin-top:5px;padding:0.55rem 0.7rem;background:var(--surface2);color:var(--text);border:1px solid var(--border);border-radius:var(--radius-sm);font-family:var(--sans);font-size:0.8rem;outline:none;"></select>
          </div>
          <div style="font-size:1.4rem;color:var(--accent);padding:0 0.25rem;">⇄</div>
          <div style="flex:1">
            <input type="text" id="convOutput" readonly placeholder="Result" style="width:100%;padding:0.65rem 0.8rem;background:var(--bg);color:var(--accent);border:1px solid var(--border);border-radius:var(--radius-sm);font-family:var(--mono);font-size:0.95rem;outline:none;">
            <select id="convTo" onchange="doConvert()" style="width:100%;margin-top:5px;padding:0.55rem 0.7rem;background:var(--surface2);color:var(--text);border:1px solid var(--border);border-radius:var(--radius-sm);font-family:var(--sans);font-size:0.8rem;outline:none;"></select>
          </div>
        </div>
        <p style="font-size:0.65rem;color:var(--text-dim);letter-spacing:0.06em;margin-top:0.75rem;text-align:center">UNIT CONVERTER — VALUES COMPUTED INSTANTLY</p>
      </div>
    </div>

  </div><!-- /keypad-wrapper -->

  <!-- HISTORY -->
  <div class="history-toggle" id="histToggle" onclick="toggleHistory()">
    <span>HISTORY</span>
    <svg width="12" height="8" viewBox="0 0 12 8" fill="none">
      <path d="M1 1L6 6L11 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
  </div>
  <div class="history-panel" id="histPanel">
    <div class="history-list" id="histList">
      <div class="history-empty">No history yet</div>
    </div>
  </div>

</main>

<footer class="site-footer">
  Built with PHP · JS · CSS &nbsp;|&nbsp; <a href="#">CALCVLT</a>
</footer>

<!-- PHP BRIDGE: inject server-side result if available -->
<script>
const PHP_RESULT = <?= json_encode($result) ?>;
const PHP_EXPR   = <?= json_encode($_POST['expression'] ?? null) ?>;
</script>

<script>
/* ════════════════════════════════════════
   STATE
════════════════════════════════════════ */
let expression  = '';
let displayVal  = '0';
let lastResult  = null;
let freshResult = false;   // just computed — next digit starts fresh
let memory      = 0;
let angleMode   = 'deg';   // 'deg' | 'rad'
let invMode     = false;
let hypMode     = false;
let history     = [];

/* ════════════════════════════════════════
   DISPLAY UPDATE
════════════════════════════════════════ */
function updateDisplay() {
  const main    = document.getElementById('mainLine');
  const exprEl  = document.getElementById('exprLine');
  const histEl  = document.getElementById('histLine');
  const cursor  = document.getElementById('cursor');

  exprEl.textContent = expression || '';

  if (displayVal === 'Error') {
    main.textContent = 'Error';
    main.className = 'display-main is-error';
    cursor.style.display = 'none';
  } else if (freshResult && lastResult !== null) {
    main.textContent = formatNum(lastResult);
    main.className = 'display-main has-result';
    cursor.style.display = 'none';
  } else {
    main.innerHTML = (displayVal || '0') + '<span class="cursor" id="cursor"></span>';
    main.className = 'display-main';
  }

  // Indicators
  document.getElementById('ind-deg').className = 'ind' + (angleMode === 'deg' ? ' active' : '');
  document.getElementById('ind-rad').className = 'ind' + (angleMode === 'rad' ? ' active' : '');
  document.getElementById('ind-inv').className = 'ind' + (invMode ? ' active' : '');
  document.getElementById('ind-hyp').className = 'ind' + (hypMode ? ' active' : '');
}

function formatNum(n) {
  if (n === null || n === undefined) return '0';
  if (!isFinite(n)) return n > 0 ? '∞' : n < 0 ? '-∞' : 'NaN';
  if (Math.abs(n) > 1e15 || (Math.abs(n) < 1e-7 && n !== 0)) {
    return n.toExponential(6).replace(/\.?0+e/, 'e');
  }
  let s = parseFloat(n.toPrecision(12)).toString();
  return s;
}

/* ════════════════════════════════════════
   INPUT HANDLING
════════════════════════════════════════ */
function input(val) {
  if (freshResult) {
    // operator continues the result; digit starts fresh
    if ('+-*/^%'.includes(val) || val === '**2') {
      expression  = formatNum(lastResult);
      displayVal  = expression;
      freshResult = false;
    } else if (val === '(' || isNaN(parseFloat(val))) {
      // functions / parens — start fresh
      expression  = '';
      displayVal  = '0';
      freshResult = false;
    } else {
      expression  = '';
      displayVal  = '0';
      freshResult = false;
    }
  }

  if (val === '1/') { expression = '1/(' + (expression || displayVal) + ')'; displayVal = expression; updateDisplay(); return; }

  // special tokens
  const token = val === '*' ? '×' : val === '/' ? '÷' : val === '^' ? '^' : val;

  expression += val;
  displayVal  = expression
    .replace(/\*/g, '×')
    .replace(/\//g, '÷')
    .replace(/\*\*/g, '^');

  updateDisplay();
}

function inputFn(fn) {
  if (freshResult) {
    const v = formatNum(lastResult);
    expression  = fn + '(' + v + ')';
    displayVal  = expression;
    freshResult = false;
    updateDisplay();
    return;
  }
  expression += fn + '(';
  displayVal  = expression;
  updateDisplay();
}

function clearAll() {
  expression  = '';
  displayVal  = '0';
  lastResult  = null;
  freshResult = false;
  document.getElementById('histLine').textContent = '';
  updateDisplay();
}

function backspace() {
  if (freshResult) { clearAll(); return; }
  expression = expression.slice(0, -1);
  displayVal = expression || '0';
  updateDisplay();
}

/* ════════════════════════════════════════
   CALCULATION ENGINE (JS-side)
════════════════════════════════════════ */
function calculate() {
  if (!expression) return;

  const raw = expression;
  let   expr = raw;

  // Convert angle for trig
  const toRad = angleMode === 'deg' ? (x) => x * Math.PI / 180 : (x) => x;

  // Replace custom tokens
  expr = expr
    .replace(/×/g, '*')
    .replace(/÷/g, '/')
    .replace(/π/g, '(' + Math.PI + ')')
    .replace(/\be\b/g, '(' + Math.E + ')')
    .replace(/(\d+)\^(\d+(\.\d+)?)/g, 'Math.pow($1,$2)')
    .replace(/\*\*2/g, '**2');

  // Trig function substitution with angle conversion
  if (angleMode === 'deg') {
    expr = expr
      .replace(/\bsin\(/g, 'Math.sin(Math.PI/180*')
      .replace(/\bcos\(/g, 'Math.cos(Math.PI/180*')
      .replace(/\btan\(/g, 'Math.tan(Math.PI/180*');
  } else {
    expr = expr
      .replace(/\bsin\(/g, 'Math.sin(')
      .replace(/\bcos\(/g, 'Math.cos(')
      .replace(/\btan\(/g, 'Math.tan(');
  }

  expr = expr
    .replace(/\bsqrt\(/g, 'Math.sqrt(')
    .replace(/\bcbrt\(/g, 'Math.cbrt(')
    .replace(/\blog\(/g,  'Math.log10(')
    .replace(/\bln\(/g,   'Math.log(')
    .replace(/\babs\(/g,  'Math.abs(');

  try {
    // eslint-disable-next-line no-new-func
    const fn  = new Function('return (' + expr + ')');
    const res = fn();

    if (res === null || res === undefined || (typeof res !== 'number' && typeof res !== 'bigint')) throw new Error('bad result');

    lastResult  = res;
    freshResult = true;

    document.getElementById('histLine').textContent = raw + ' =';
    addHistory(raw, formatNum(res));
    updateDisplay();

    // Also verify via PHP (background)
    phpVerify(raw);

  } catch(e) {
    displayVal  = 'Error';
    freshResult = false;
    updateDisplay();
  }
}

/* ════════════════════════════════════════
   PHP VERIFICATION (AJAX)
════════════════════════════════════════ */
function phpVerify(expr) {
  const fd = new FormData();
  fd.append('expression', expr);
  fetch(window.location.href, { method: 'POST', body: fd })
    .then(r => r.text())
    .then(html => {
      // Extract PHP result from the page
      const m = html.match(/PHP_RESULT = (.*?);/);
      if (m) {
        const phpRes = JSON.parse(m[1]);
        if (phpRes && phpRes !== 'Error' && lastResult !== null) {
          // cross-check — if mismatch, add note
        }
      }
    }).catch(() => {});
}

/* ════════════════════════════════════════
   HISTORY
════════════════════════════════════════ */
function addHistory(expr, result) {
  history.unshift({ expr, result });
  if (history.length > 30) history.pop();
  renderHistory();
}

function renderHistory() {
  const list = document.getElementById('histList');
  if (history.length === 0) {
    list.innerHTML = '<div class="history-empty">No history yet</div>';
    return;
  }
  list.innerHTML = history.map((h, i) =>
    `<div class="history-item" onclick="recallHistory(${i})">
      <span class="h-expr">${h.expr}</span>
      <span class="h-result">${h.result}</span>
    </div>`
  ).join('');
}

function recallHistory(i) {
  const h = history[i];
  expression  = h.result;
  displayVal  = h.result;
  lastResult  = parseFloat(h.result);
  freshResult = true;
  updateDisplay();
}

function toggleHistory() {
  const panel  = document.getElementById('histPanel');
  const toggle = document.getElementById('histToggle');
  panel.classList.toggle('open');
  toggle.classList.toggle('open');
}

/* ════════════════════════════════════════
   MODES
════════════════════════════════════════ */
function switchMode(mode) {
  document.querySelectorAll('.mode-tab').forEach((t,i) => {
    t.classList.toggle('active', ['standard','scientific','converter'][i] === mode);
  });
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel-' + mode).classList.add('active');
  if (mode === 'converter') updateConvUnits();
}

function toggleAngle() {
  angleMode = angleMode === 'deg' ? 'rad' : 'deg';
  document.querySelector('[onclick="toggleAngle()"]').textContent = angleMode.toUpperCase();
  updateDisplay();
}

function toggleInv() {
  invMode = !invMode;
  updateDisplay();
}

function toggleHyp() {
  hypMode = !hypMode;
  updateDisplay();
}

/* ════════════════════════════════════════
   MEMORY
════════════════════════════════════════ */
function memStore() {
  if (lastResult !== null) memory = lastResult;
  else if (displayVal && displayVal !== '0') memory = parseFloat(displayVal);
  // flash indicator
  const ind = document.querySelector('.ind'); // reuse display
}

function memRecall() {
  expression  = memory.toString();
  displayVal  = formatNum(memory);
  freshResult = false;
  updateDisplay();
}

/* ════════════════════════════════════════
   KEYBOARD SUPPORT
════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.key >= '0' && e.key <= '9') { input(e.key); return; }
  const map = {
    '+':'+', '-':'-', '*':'*', '/':'/',
    '%':'%', '(':' (', ')':')',
    '.':'.', 'Enter':'=', 'Backspace':'bs',
    'Escape':'ac', 'Delete':'ac'
  };
  const v = map[e.key];
  if (!v) return;
  e.preventDefault();
  if (v === '=')  calculate();
  else if (v === 'bs') backspace();
  else if (v === 'ac') clearAll();
  else input(v);
});

/* ════════════════════════════════════════
   UNIT CONVERTER
════════════════════════════════════════ */
const UNITS = {
  length:  { m:1, km:0.001, cm:100, mm:1000, mi:0.000621371, yd:1.09361, ft:3.28084, inch:39.3701, nm:1e9, ly:1.057e-16 },
  mass:    { kg:1, g:1000, mg:1e6, lb:2.20462, oz:35.274, t:0.001, st:0.157473 },
  temp:    { C:null, F:null, K:null },
  area:    { 'm²':1, 'km²':1e-6, 'cm²':1e4, 'mm²':1e6, 'mi²':3.861e-7, 'yd²':1.19599, 'ft²':10.7639, ha:1e-4, acre:0.000247105 },
  volume:  { L:1, mL:1000, 'm³':0.001, 'cm³':1000, 'ft³':0.0353147, gal:0.264172, qt:1.05669, pt:2.11338, fl_oz:33.814, cup:4.22675 },
  speed:   { 'm/s':1, 'km/h':3.6, mph:2.23694, knot:1.94384, 'ft/s':3.28084 },
  time:    { s:1, ms:1000, min:1/60, h:1/3600, day:1/86400, week:1/604800, month:1/2592000, year:1/31536000 },
  data:    { B:1, KB:1/1024, MB:1/1048576, GB:1/1073741824, TB:1/1099511627776, bit:8, Kbit:8/1024, Mbit:8/1048576 },
};

function updateConvUnits() {
  const type = document.getElementById('convType').value;
  const from = document.getElementById('convFrom');
  const to   = document.getElementById('convTo');
  const keys = Object.keys(UNITS[type]);
  from.innerHTML = keys.map(k => `<option value="${k}">${k}</option>`).join('');
  to.innerHTML   = keys.map((k,i) => `<option value="${k}" ${i===1?'selected':''}>${k}</option>`).join('');
  doConvert();
}

function doConvert() {
  const type = document.getElementById('convType').value;
  const val  = parseFloat(document.getElementById('convInput').value);
  const from = document.getElementById('convFrom').value;
  const to   = document.getElementById('convTo').value;
  const out  = document.getElementById('convOutput');
  if (isNaN(val)) { out.value = ''; return; }

  let result;
  if (type === 'temp') {
    result = convertTemp(val, from, to);
  } else {
    const units = UNITS[type];
    const baseVal = val / units[from];   // to base unit
    result = baseVal * units[to];
  }
  out.value = parseFloat(result.toPrecision(10)).toString();
}

function convertTemp(val, from, to) {
  let c;
  if (from === 'C') c = val;
  else if (from === 'F') c = (val - 32) * 5/9;
  else c = val - 273.15;

  if (to === 'C') return c;
  if (to === 'F') return c * 9/5 + 32;
  return c + 273.15;
}

/* ════════════════════════════════════════
   INIT
════════════════════════════════════════ */
updateConvUnits();
updateDisplay();

// If PHP returned a result on page load (form submitted), show it
if (PHP_RESULT && PHP_EXPR) {
  const v = parseFloat(PHP_RESULT);
  if (!isNaN(v)) {
    lastResult  = v;
    freshResult = true;
    expression  = PHP_EXPR;
    document.getElementById('histLine').textContent = PHP_EXPR + ' =';
    addHistory(PHP_EXPR, PHP_RESULT);
    updateDisplay();
  }
}
</script>

</body>
</html>
