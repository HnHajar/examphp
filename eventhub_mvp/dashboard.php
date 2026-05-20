<?php
/**
 * dashboard.php — Tableau de bord temps réel (Partie 4.2)
 * Accessible uniquement aux organisateurs connectés.
 * Mise à jour automatique toutes les 30 secondes via fetch().
 */

require_once __DIR__ . '/config/db.php';
session_start();

// ── Contrôle d'accès organisateur ──────────────────────────────────────────
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'organizer') {
    header('Location: login.php');
    exit;
}

$organizerName = htmlspecialchars($_SESSION['username'] ?? 'Organisateur');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Tableau de bord</title>

<!-- Google Fonts : Syne (display) + DM Mono (chiffres) -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ═══════════════════════════════════════
   VARIABLES & RESET
═══════════════════════════════════════ */
:root {
  --bg:        #0a0c10;
  --surface:   #111318;
  --border:    #1e2230;
  --accent:    #5b6ef5;
  --accent2:   #f5a623;
  --danger:    #f55b5b;
  --success:   #4ecb71;
  --text:      #e8eaf2;
  --muted:     #5a607a;
  --font-disp: 'Syne', sans-serif;
  --font-mono: 'DM Mono', monospace;
  --radius:    14px;
  --transition: 0.4s cubic-bezier(.22,.68,0,1.2);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--font-disp);
  min-height: 100vh;
  /* grain overlay */
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
}

/* ═══════════════════════════════════════
   TOPBAR
═══════════════════════════════════════ */
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 40px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: rgba(10,12,16,.92);
  backdrop-filter: blur(12px);
  z-index: 100;
}

.topbar-brand {
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}
.topbar-brand span { color: var(--accent); }

.topbar-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

/* Indicateur de statut live */
#status-dot {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-mono);
  font-size: .78rem;
  color: var(--muted);
}
#status-dot::before {
  content: '';
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--success);
  display: block;
  animation: pulse 2s infinite;
}
#status-dot.error::before { background: var(--danger); animation: none; }
#status-dot.retrying::before { background: var(--accent2); }

@keyframes pulse {
  0%,100% { opacity: 1; transform: scale(1); }
  50%      { opacity: .4; transform: scale(1.4); }
}

/* Countdown */
#countdown-ring {
  width: 36px; height: 36px;
  position: relative;
}
#countdown-ring svg { transform: rotate(-90deg); }
#countdown-ring circle.track { fill: none; stroke: var(--border); stroke-width: 3; }
#countdown-ring circle.prog  {
  fill: none; stroke: var(--accent); stroke-width: 3;
  stroke-linecap: round;
  stroke-dasharray: 88;
  stroke-dashoffset: 0;
  transition: stroke-dashoffset 1s linear;
}
#countdown-label {
  position: absolute;
  inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-mono);
  font-size: .65rem;
  color: var(--muted);
}

/* ═══════════════════════════════════════
   LAYOUT
═══════════════════════════════════════ */
.main { padding: 36px 40px; max-width: 1400px; margin: 0 auto; }

.section-title {
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 18px;
}

/* ═══════════════════════════════════════
   KPI CARDS (ligne du haut)
═══════════════════════════════════════ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 40px;
}

.kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px 22px;
  position: relative;
  overflow: hidden;
  transition: border-color .3s;
}
.kpi-card:hover { border-color: var(--accent); }
.kpi-card::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 2px;
  background: var(--accent);
  transform: scaleX(0);
  transition: transform .4s;
}
.kpi-card:hover::after { transform: scaleX(1); }

.kpi-label {
  font-size: .75rem;
  color: var(--muted);
  margin-bottom: 10px;
  letter-spacing: .04em;
}
.kpi-value {
  font-family: var(--font-mono);
  font-size: 2.4rem;
  font-weight: 500;
  line-height: 1;
  transition: color .3s;
}
/* Animation flash quand la valeur change */
.kpi-value.changed {
  animation: flash-num .5s ease;
}
@keyframes flash-num {
  0%   { color: var(--accent); transform: scale(1.06); }
  100% { color: var(--text);   transform: scale(1); }
}

.kpi-sub {
  font-size: .72rem;
  color: var(--muted);
  margin-top: 6px;
}
.kpi-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 20px;
  font-size: .68rem;
  font-family: var(--font-mono);
  margin-top: 8px;
}
.badge-up   { background: rgba(78,203,113,.15); color: var(--success); }
.badge-full { background: rgba(245,91,91,.15);  color: var(--danger);  }
.badge-warn { background: rgba(245,166,35,.15); color: var(--accent2); }

/* ═══════════════════════════════════════
   SECTION BASSE : Top3 + Inscrits récents
═══════════════════════════════════════ */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 40px;
}
@media (max-width: 860px) { .bottom-grid { grid-template-columns: 1fr; } }

.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 24px;
}
.panel-title {
  font-size: .8rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.panel-title .dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--accent);
}

/* Top 3 */
.top3-list { list-style: none; display: flex; flex-direction: column; gap: 14px; }

.top3-item {
  display: grid;
  grid-template-columns: 28px 1fr auto;
  align-items: center;
  gap: 12px;
  transition: opacity .3s;
}
.top3-rank {
  font-family: var(--font-mono);
  font-size: .72rem;
  color: var(--muted);
  text-align: center;
}
.top3-rank.gold   { color: #f5c842; }
.top3-rank.silver { color: #a8b4c8; }
.top3-rank.bronze { color: #cd7f52; }

.top3-info { min-width: 0; }
.top3-name {
  font-size: .88rem;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.top3-bar-wrap {
  height: 4px;
  background: var(--border);
  border-radius: 2px;
  margin-top: 6px;
  overflow: hidden;
}
.top3-bar {
  height: 100%;
  border-radius: 2px;
  background: var(--accent);
  transition: width .6s cubic-bezier(.22,.68,0,1.2);
}
.top3-bar.full { background: var(--danger); }

.top3-pct {
  font-family: var(--font-mono);
  font-size: .8rem;
  color: var(--muted);
  white-space: nowrap;
}

/* Nouveaux inscrits */
#recent-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }

.recent-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: 8px;
  background: rgba(255,255,255,.03);
  border: 1px solid var(--border);
  animation: slide-in .35s ease both;
}
@keyframes slide-in {
  from { opacity: 0; transform: translateX(-12px); }
  to   { opacity: 1; transform: translateX(0); }
}

.recent-avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--accent);
  display: flex; align-items: center; justify-content: center;
  font-size: .78rem;
  font-weight: 700;
  flex-shrink: 0;
  color: #fff;
}
.recent-info { min-width: 0; }
.recent-name { font-size: .85rem; font-weight: 600; }
.recent-event {
  font-size: .72rem;
  color: var(--muted);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.recent-time {
  margin-left: auto;
  font-family: var(--font-mono);
  font-size: .68rem;
  color: var(--muted);
  white-space: nowrap;
}

/* ═══════════════════════════════════════
   TABLEAU COMPLET INSCRIPTIONS PAR EVENT
═══════════════════════════════════════ */
.table-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  margin-bottom: 40px;
}
.table-panel table {
  width: 100%;
  border-collapse: collapse;
  font-size: .84rem;
}
.table-panel thead tr {
  background: rgba(91,110,245,.08);
  border-bottom: 1px solid var(--border);
}
.table-panel th {
  padding: 14px 18px;
  text-align: left;
  font-size: .7rem;
  font-weight: 600;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--muted);
}
.table-panel td {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  transition: background .2s;
}
.table-panel tr:last-child td { border-bottom: none; }
.table-panel tbody tr:hover td { background: rgba(255,255,255,.025); }

/* Barre de remplissage dans le tableau */
.fill-bar-wrap {
  height: 6px;
  background: var(--border);
  border-radius: 3px;
  width: 120px;
  overflow: hidden;
}
.fill-bar {
  height: 100%;
  border-radius: 3px;
  background: var(--accent);
  transition: width .6s cubic-bezier(.22,.68,0,1.2), background .3s;
}
.fill-bar.warn { background: var(--accent2); }
.fill-bar.full { background: var(--danger); }

/* ═══════════════════════════════════════
   ERREUR / CHARGEMENT
═══════════════════════════════════════ */
.error-banner {
  display: none;
  background: rgba(245,91,91,.1);
  border: 1px solid var(--danger);
  color: var(--danger);
  border-radius: 10px;
  padding: 14px 20px;
  margin-bottom: 24px;
  font-size: .85rem;
  align-items: center;
  gap: 10px;
}
.error-banner.visible { display: flex; }

/* Skeleton loader */
.skeleton {
  background: linear-gradient(90deg, var(--surface) 25%, var(--border) 50%, var(--surface) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 4px;
}
@keyframes shimmer {
  from { background-position: 200% 0; }
  to   { background-position: -200% 0; }
}

/* ═══════════════════════════════════════
   TOAST NOTIFICATIONS
═══════════════════════════════════════ */
#toast-container {
  position: fixed;
  bottom: 30px;
  right: 30px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  z-index: 9999;
  pointer-events: none;
}
.toast {
  background: var(--surface);
  border: 1px solid var(--border);
  border-left: 4px solid var(--accent);
  border-radius: 10px;
  padding: 14px 20px;
  max-width: 340px;
  font-size: .84rem;
  pointer-events: all;
  animation: toast-in .4s cubic-bezier(.22,.68,0,1.2) both;
  box-shadow: 0 8px 32px rgba(0,0,0,.4);
}
.toast.full  { border-left-color: var(--danger); }
.toast.warn  { border-left-color: var(--accent2); }
.toast.info  { border-left-color: var(--accent); }
.toast-title { font-weight: 700; margin-bottom: 4px; }
.toast-body  { color: var(--muted); font-size: .78rem; }
@keyframes toast-in {
  from { opacity: 0; transform: translateX(40px) scale(.95); }
  to   { opacity: 1; transform: none; }
}
@keyframes toast-out {
  to   { opacity: 0; transform: translateX(40px) scale(.9); }
}

/* ═══════════════════════════════════════
   POINT CRÉATIF — Recherche AJAX live
═══════════════════════════════════════ */
/*
 * FONCTIONNALITÉ AJAX CRÉATIVE :
 * Barre de recherche "Live Event Lookup" : l'utilisateur tape le nom d'un événement,
 * un fetch() interroge api/events.php?search=... toutes les 300 ms (debounce),
 * et affiche les résultats dans un dropdown inline avec statut de remplissage.
 * → Améliore la navigation sur de grandes listes sans quitter le dashboard.
 */
.search-widget {
  position: relative;
  max-width: 420px;
  margin-bottom: 40px;
}
.search-widget input {
  width: 100%;
  padding: 13px 18px 13px 44px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  color: var(--text);
  font-family: var(--font-disp);
  font-size: .9rem;
  outline: none;
  transition: border-color .2s;
}
.search-widget input:focus { border-color: var(--accent); }
.search-icon {
  position: absolute;
  left: 14px; top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  pointer-events: none;
}
.search-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 6px);
  left: 0; right: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  z-index: 200;
  box-shadow: 0 12px 40px rgba(0,0,0,.5);
}
.search-dropdown.open { display: block; }
.search-result-item {
  padding: 12px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: .84rem;
  cursor: pointer;
  border-bottom: 1px solid var(--border);
  transition: background .15s;
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover { background: rgba(91,110,245,.1); }
.search-result-name { font-weight: 600; }
.search-result-meta { color: var(--muted); font-size: .72rem; font-family: var(--font-mono); }
.search-empty { padding: 16px 18px; color: var(--muted); font-size: .84rem; text-align: center; }
</style>
</head>
<body>

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<header class="topbar">
  <div class="topbar-brand">Événe<span>Board</span></div>
  <div class="topbar-right">
    <div id="status-dot">En direct</div>
    <div id="countdown-ring" title="Prochaine mise à jour">
      <svg width="36" height="36" viewBox="0 0 36 36">
        <circle class="track" cx="18" cy="18" r="14"/>
        <circle class="prog"  cx="18" cy="18" r="14" id="ring-prog"/>
      </svg>
      <div id="countdown-label">30</div>
    </div>
    <span style="font-size:.85rem;color:var(--muted)">Bonjour, <?= $organizerName ?></span>
    <a href="logout.php" style="font-size:.8rem;color:var(--muted);text-decoration:none;border:1px solid var(--border);padding:6px 14px;border-radius:8px;">Déconnexion</a>
  </div>
</header>

<!-- ── Main ──────────────────────────────────────────────────── -->
<main class="main">

  <!-- Bannière d'erreur -->
  <div class="error-banner" id="error-banner">
    <span>⚠</span>
    <span id="error-text">Impossible de joindre l'API. Nouvelle tentative dans <b id="retry-countdown">10</b>s…</span>
  </div>

  <!-- KPI -->
  <p class="section-title">Vue d'ensemble</p>
  <div class="kpi-grid" id="kpi-grid">
    <!-- rempli par JS -->
    <?php for($i=0;$i<4;$i++): ?>
    <div class="kpi-card">
      <div class="kpi-label skeleton" style="width:60%;height:12px;margin-bottom:14px"></div>
      <div class="kpi-value skeleton" style="width:50%;height:40px"></div>
    </div>
    <?php endfor; ?>
  </div>

  <!-- ✨ Point créatif : Live search -->
  <p class="section-title">Recherche rapide d'événement</p>
  <div class="search-widget">
    <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>
    <input type="text" id="live-search" placeholder="Rechercher un événement…" autocomplete="off">
    <div class="search-dropdown" id="search-dropdown"></div>
  </div>

  <!-- Top3 + Récents -->
  <div class="bottom-grid">
    <div class="panel">
      <div class="panel-title"><span class="dot"></span>Top 3 événements</div>
      <ul class="top3-list" id="top3-list">
        <li style="color:var(--muted);font-size:.84rem">Chargement…</li>
      </ul>
    </div>
    <div class="panel">
      <div class="panel-title"><span class="dot" style="background:var(--success)"></span>Nouveaux inscrits (24h)</div>
      <ul id="recent-list">
        <li style="color:var(--muted);font-size:.84rem;padding:10px 0">Chargement…</li>
      </ul>
    </div>
  </div>

  <!-- Tableau inscrits par événement -->
  <p class="section-title">Inscrits par événement</p>
  <div class="table-panel">
    <table>
      <thead>
        <tr>
          <th>Événement</th>
          <th>Date</th>
          <th>Inscrits</th>
          <th>Capacité</th>
          <th>Remplissage</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody id="events-tbody">
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:28px">Chargement…</td></tr>
      </tbody>
    </table>
  </div>

</main>

<!-- Toast container -->
<div id="toast-container"></div>

<!-- ══════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════ -->
<script>
/* ─────────────────────────────────────────
   CONFIG
───────────────────────────────────────── */
const REFRESH_INTERVAL = 30;   // secondes entre chaque mise à jour
const RETRY_DELAY      = 10;   // secondes avant de réessayer après erreur
const API_STATS        = 'api/stats.php';
const API_EVENTS       = 'api/events.php';

/* ─────────────────────────────────────────
   ÉTAT
───────────────────────────────────────── */
let prevFillRates    = {};   // { event_id: pct } pour détecter passage à 100%
let prevKpiValues    = {};   // pour l'animation flash
let refreshTimer     = null;
let countdownTimer   = null;
let retryTimer       = null;
let countdown        = REFRESH_INTERVAL;
let isError          = false;

/* ─────────────────────────────────────────
   UTILITAIRES
───────────────────────────────────────── */
function initials(name) {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
}

function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)  return diff + 's';
  if (diff < 3600) return Math.floor(diff/60) + 'min';
  return Math.floor(diff/3600) + 'h';
}

function statusBadge(pct) {
  if (pct >= 100) return '<span class="kpi-badge badge-full">Complet</span>';
  if (pct >= 80)  return '<span class="kpi-badge badge-warn">≥ 80%</span>';
  return '<span class="kpi-badge badge-up">Disponible</span>';
}

/* ─────────────────────────────────────────
   TOAST (sans lib externe)
───────────────────────────────────────── */
function showToast(title, body, type = 'info', duration = 5000) {
  const container = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<div class="toast-title">${title}</div><div class="toast-body">${body}</div>`;
  container.appendChild(t);
  setTimeout(() => {
    t.style.animation = 'toast-out .35s ease forwards';
    t.addEventListener('animationend', () => t.remove());
  }, duration);
}

/* ─────────────────────────────────────────
   GESTION ERREUR
───────────────────────────────────────── */
function setError(msg) {
  isError = true;
  const banner = document.getElementById('error-banner');
  const errText = document.getElementById('error-text');
  document.getElementById('status-dot').className = 'error';
  document.getElementById('status-dot').textContent = 'Hors ligne';

  banner.classList.add('visible');

  // Compte à rebours de réessai affiché dans la bannière
  let rc = RETRY_DELAY;
  document.getElementById('retry-countdown').textContent = rc;
  clearInterval(retryTimer);
  retryTimer = setInterval(() => {
    rc--;
    const el = document.getElementById('retry-countdown');
    if (el) el.textContent = rc;
    if (rc <= 0) {
      clearInterval(retryTimer);
      fetchStats();
    }
  }, 1000);
}

function clearError() {
  isError = false;
  document.getElementById('error-banner').classList.remove('visible');
  document.getElementById('status-dot').className = '';
  document.getElementById('status-dot').textContent = 'En direct';
  clearInterval(retryTimer);
}

/* ─────────────────────────────────────────
   RING COUNTDOWN
───────────────────────────────────────── */
function startCountdown() {
  clearInterval(countdownTimer);
  countdown = REFRESH_INTERVAL;
  const CIRCUM = 88; // 2π × 14

  countdownTimer = setInterval(() => {
    countdown--;
    const label = document.getElementById('countdown-label');
    const prog  = document.getElementById('ring-prog');
    if (label) label.textContent = countdown;
    if (prog)  prog.style.strokeDashoffset = CIRCUM * (1 - countdown / REFRESH_INTERVAL);
    if (countdown <= 0) {
      clearInterval(countdownTimer);
      fetchStats();
    }
  }, 1000);
}

/* ─────────────────────────────────────────
   RENDER KPI
───────────────────────────────────────── */
function renderKpi(data) {
  const grid = document.getElementById('kpi-grid');
  const cards = [
    { label: 'Total inscrits',       value: data.total_registrations,   sub: 'tous événements confondus' },
    { label: 'Événements actifs',    value: data.active_events,         sub: 'avec au moins 1 inscrit' },
    { label: 'Nouveaux (24h)',       value: data.new_last_24h,          sub: 'inscriptions récentes' },
    { label: 'Événements complets',  value: data.full_events,           sub: '100% de capacité atteinte' },
  ];

  grid.innerHTML = cards.map(c => {
    const prev     = prevKpiValues[c.label] ?? null;
    const changed  = (prev !== null && prev !== c.value) ? 'changed' : '';
    prevKpiValues[c.label] = c.value;
    return `
    <div class="kpi-card">
      <div class="kpi-label">${c.label}</div>
      <div class="kpi-value ${changed}" data-key="${c.label}">${c.value}</div>
      <div class="kpi-sub">${c.sub}</div>
    </div>`;
  }).join('');

  // retire la classe "changed" après l'animation
  grid.querySelectorAll('.kpi-value.changed').forEach(el => {
    el.addEventListener('animationend', () => el.classList.remove('changed'), { once: true });
  });
}

/* ─────────────────────────────────────────
   RENDER TOP 3
───────────────────────────────────────── */
function renderTop3(top3) {
  const ranks = ['gold', 'silver', 'bronze'];
  const medals = ['🥇', '🥈', '🥉'];
  const list = document.getElementById('top3-list');

  list.innerHTML = top3.map((ev, i) => {
    const pct = ev.fill_rate;
    const barClass = pct >= 100 ? 'full' : '';
    return `
    <li class="top3-item">
      <span class="top3-rank ${ranks[i]}">${medals[i]}</span>
      <div class="top3-info">
        <div class="top3-name">${ev.title}</div>
        <div class="top3-bar-wrap">
          <div class="top3-bar ${barClass}" style="width:${Math.min(pct,100)}%"></div>
        </div>
      </div>
      <span class="top3-pct">${ev.registrations}/${ev.capacity}</span>
    </li>`;
  }).join('');
}

/* ─────────────────────────────────────────
   RENDER RÉCENTS
───────────────────────────────────────── */
function renderRecent(recent) {
  const list = document.getElementById('recent-list');
  if (!recent.length) {
    list.innerHTML = '<li style="color:var(--muted);font-size:.84rem;padding:10px 0">Aucune inscription récente</li>';
    return;
  }
  list.innerHTML = recent.map(r => `
  <li class="recent-item">
    <div class="recent-avatar" style="background:hsl(${r.user_name.charCodeAt(0)*5 % 360},55%,48%)">${initials(r.user_name)}</div>
    <div class="recent-info">
      <div class="recent-name">${r.user_name}</div>
      <div class="recent-event">${r.event_title}</div>
    </div>
    <div class="recent-time">${timeAgo(r.created_at)}</div>
  </li>`).join('');
}

/* ─────────────────────────────────────────
   RENDER TABLEAU
───────────────────────────────────────── */
function renderTable(events) {
  const tbody = document.getElementById('events-tbody');
  tbody.innerHTML = events.map(ev => {
    const pct      = ev.fill_rate;
    const barClass = pct >= 100 ? 'full' : pct >= 80 ? 'warn' : '';
    const newFill  = prevFillRates[ev.id] !== undefined && prevFillRates[ev.id] < 100 && pct >= 100;
    prevFillRates[ev.id] = pct;
    if (newFill) {
      showToast('🎉 Événement complet !', `"${ev.title}" vient d'atteindre 100% de remplissage.`, 'full');
    }
    return `
    <tr>
      <td><b>${ev.title}</b></td>
      <td style="font-family:var(--font-mono);font-size:.78rem;color:var(--muted)">${ev.event_date ?? '—'}</td>
      <td style="font-family:var(--font-mono)">${ev.registrations}</td>
      <td style="font-family:var(--font-mono)">${ev.capacity}</td>
      <td>
        <div class="fill-bar-wrap">
          <div class="fill-bar ${barClass}" style="width:${Math.min(pct,100)}%"></div>
        </div>
        <span style="font-size:.72rem;font-family:var(--font-mono);color:var(--muted)">${pct}%</span>
      </td>
      <td>${statusBadge(pct)}</td>
    </tr>`;
  }).join('');
}

/* ─────────────────────────────────────────
   FETCH PRINCIPAL
───────────────────────────────────────── */
async function fetchStats() {
  try {
    const res = await fetch(API_STATS, { cache: 'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    if (data.error) throw new Error(data.error);

    clearError();
    renderKpi(data);
    renderTop3(data.top3 ?? []);
    renderRecent(data.recent_registrations ?? []);
    renderTable(data.events ?? []);
    startCountdown();

  } catch (err) {
    console.error('[Dashboard]', err);
    setError(err.message);
  }
}

/* ─────────────────────────────────────────
   ✨ POINT CRÉATIF — Live search (debounce)
   Permet de chercher un événement depuis
   le dashboard sans changer de page.
   Un fetch() est déclenché 300ms après la
   dernière frappe ; les résultats s'affichent
   en dropdown avec taux de remplissage.
───────────────────────────────────────── */
(function initLiveSearch() {
  const input    = document.getElementById('live-search');
  const dropdown = document.getElementById('search-dropdown');
  let debounce   = null;

  input.addEventListener('input', () => {
    clearTimeout(debounce);
    const q = input.value.trim();
    if (!q) { dropdown.classList.remove('open'); return; }

    debounce = setTimeout(async () => {
      try {
        const res  = await fetch(`${API_EVENTS}?search=${encodeURIComponent(q)}&limit=5`);
        const data = await res.json();
        const list = data.events ?? data ?? [];

        if (!list.length) {
          dropdown.innerHTML = '<div class="search-empty">Aucun résultat</div>';
        } else {
          dropdown.innerHTML = list.map(ev => {
            const pct = ev.fill_rate ?? Math.round((ev.registrations / ev.capacity) * 100);
            return `
            <div class="search-result-item" onclick="window.location='events/view.php?id=${ev.id}'">
              <div>
                <div class="search-result-name">${ev.title}</div>
                <div class="search-result-meta">${ev.event_date ?? ''}</div>
              </div>
              <div style="text-align:right">
                <div class="search-result-meta" style="margin-bottom:4px">${ev.registrations ?? 0}/${ev.capacity}</div>
                ${statusBadge(pct)}
              </div>
            </div>`;
          }).join('');
        }
        dropdown.classList.add('open');
      } catch {
        dropdown.innerHTML = '<div class="search-empty" style="color:var(--danger)">Erreur de recherche</div>';
        dropdown.classList.add('open');
      }
    }, 300);
  });

  // Ferme le dropdown au clic ailleurs
  document.addEventListener('click', e => {
    if (!e.target.closest('.search-widget')) dropdown.classList.remove('open');
  });
})();

/* ─────────────────────────────────────────
   INIT
───────────────────────────────────────── */
fetchStats();
</script>
</body>
</html>