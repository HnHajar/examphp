<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EventHub Pro — MVP · ENSA Marrakech</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
  <style>
    :root {
      --navy:  #0f1f3d;
      --blue:  #2563eb;
      --amber: #f59e0b;
      --teal:  #0d9488;
      --slate: #64748b;
    }
    * { box-sizing: border-box; }
    body { font-family: 'DM Sans', sans-serif; background: #f1f5f9; color: #1e293b; }
    .font-display, h1,h2,h3,h4 { font-family: 'Syne', sans-serif; }

    /* gradient hero */
    .hero-bg {
      background: linear-gradient(135deg, #0f1f3d 0%, #1e3a5f 45%, #0c4a6e 100%);
      background-size: 200% 200%;
      animation: grad 10s ease infinite;
    }
    @keyframes grad { 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }

    .dot-bg {
      background-image: radial-gradient(circle, rgba(255,255,255,.07) 1px, transparent 1px);
      background-size: 26px 26px;
    }

    /* cards */
    .event-card { transition: transform .22s ease, box-shadow .22s ease; }
    .event-card:hover { transform: translateY(-5px); box-shadow: 0 18px 40px rgba(15,31,61,.14); }

    /* badge */
    .badge {
      font-family: 'Syne', sans-serif;
      font-size: .64rem; letter-spacing: .07em;
      text-transform: uppercase; font-weight: 700;
      padding: 3px 10px; border-radius: 99px;
    }

    /* capacity bar */
    .cap-bar { height: 5px; border-radius: 99px; background: #e2e8f0; overflow: hidden; }
    .cap-bar-fill { height: 100%; border-radius: 99px; transition: width .6s ease; }

    /* skeleton */
    @keyframes shimmer {
      0%   { background-position: -400px 0; }
      100% { background-position:  400px 0; }
    }
    .skeleton {
      background: linear-gradient(90deg, #e2e8f0 25%, #f8fafc 50%, #e2e8f0 75%);
      background-size: 800px 100%;
      animation: shimmer 1.4s infinite;
      border-radius: 6px;
    }

    /* toast */
    #toast-container { position:fixed; bottom:24px; right:24px; z-index:999; display:flex; flex-direction:column; gap:10px; }
    .toast {
      min-width:280px; padding:14px 18px; border-radius:10px;
      color:#fff; font-size:.9rem; font-family:'DM Sans',sans-serif;
      box-shadow: 0 8px 24px rgba(0,0,0,.2);
      animation: slideIn .3s ease;
    }
    @keyframes slideIn { from{transform:translateX(120%);opacity:0} to{transform:translateX(0);opacity:1} }
    .toast.success { background:#16a34a; }
    .toast.error   { background:#dc2626; }
    .toast.info    { background:#2563eb; }

    /* modal */
    .modal-overlay {
      position:fixed; inset:0; z-index:50;
      background:rgba(15,31,61,.55); backdrop-filter:blur(5px);
      display:flex; align-items:center; justify-content:center;
      animation:fadeIn .2s ease;
    }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    .modal-box {
      background:#fff; border-radius:16px;
      width:min(560px,94vw); max-height:90vh; overflow-y:auto;
      box-shadow:0 32px 64px rgba(15,31,61,.25);
      animation:popIn .25s ease;
    }
    @keyframes popIn { from{transform:scale(.92);opacity:0} to{transform:scale(1);opacity:1} }

    /* form */
    .form-input {
      width:100%; padding:10px 14px;
      border:1.5px solid #cbd5e1; border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:.95rem;
      background:#fff; color:#1e293b;
      transition:border-color .2s, box-shadow .2s; outline:none;
    }
    .form-input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.12); }

    /* spinner */
    .spinner {
      width:17px; height:17px;
      border:2.5px solid rgba(255,255,255,.35); border-top-color:#fff;
      border-radius:50%; animation:spin .7s linear infinite; display:inline-block;
    }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* tabs */
    .tab-btn {
      font-family:'Syne',sans-serif; font-size:.75rem; font-weight:700;
      letter-spacing:.06em; text-transform:uppercase;
      padding:8px 18px; border-radius:99px; cursor:pointer; transition:all .2s;
    }
    .tab-btn.active { background:#0f1f3d; color:#fff; }
    .tab-btn:not(.active) { background:transparent; color:#64748b; }
    .tab-btn:not(.active):hover { background:#e2e8f0; }

    /* nav */
    .nav-link {
      font-family:'Syne',sans-serif; font-size:.78rem; font-weight:600;
      letter-spacing:.07em; text-transform:uppercase;
      color:rgba(255,255,255,.65); transition:color .2s;
      padding-bottom:2px; border-bottom:2px solid transparent;
    }
    .nav-link:hover { color:#fff; }
    .nav-link.active { color:#f59e0b; border-bottom-color:#f59e0b; }

    /* stat */
    .stat-num { font-family:'Syne',sans-serif; font-size:2.4rem; font-weight:800; line-height:1; }

    /* todo */
    .todo-block {
      background:#fff7ed; border-left:4px solid #f59e0b;
      padding:10px 14px; border-radius:0 8px 8px 0;
      font-family:'Courier New',monospace; font-size:.8rem; color:#92400e;
      margin:6px 0; white-space:pre-wrap; line-height:1.5;
    }

    /* kpi card */
    .kpi { background:#fff; border-radius:16px; padding:20px; border:1px solid #e2e8f0; }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="hero-bg dot-bg sticky top-0 z-40 shadow-lg">
  <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:#f59e0b">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <span class="font-display text-white text-lg font-bold">EventHub <span style="color:#f59e0b">Pro</span></span>
    </div>
    <div class="hidden md:flex items-center gap-7">
      <button class="nav-link active" onclick="showSection('events',this)">Événements</button>
      <button class="nav-link" onclick="showSection('dashboard',this)">Dashboard</button>
      <button class="nav-link" onclick="showSection('create',this)">Créer</button>
    </div>
    <div class="flex items-center gap-3">
      <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
      <span class="text-white/50 text-xs font-display">Live</span>
      <button onclick="openLogin()"
        class="ml-3 px-4 py-2 rounded-lg text-xs font-display font-bold tracking-widest uppercase"
        style="background:#f59e0b; color:#0f1f3d">Connexion</button>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero-bg dot-bg py-14 px-6">
  <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
    <div>
      <span class="inline-block badge mb-4 text-xs" style="background:rgba(245,158,11,.2);color:#f59e0b">
        MVP · Examen PHP Avancé · ENSA Marrakech
      </span>
      <h1 class="text-4xl md:text-5xl font-display font-extrabold text-white leading-tight mb-4">
        Gérez vos<br/><span style="color:#f59e0b">événements</span><br/>intelligemment
      </h1>
      <p class="text-slate-300 text-sm leading-relaxed mb-8">
        Plateforme de gestion d'événements — Inscriptions, tickets PDF,<br/>notifications email, statistiques temps réel.
      </p>
      <div class="flex flex-wrap gap-3">
        <button onclick="showSection('events', document.querySelectorAll('.nav-link')[0])"
          class="px-6 py-3 rounded-xl font-display font-bold text-sm text-white"
          style="background:#2563eb">Voir les événements →</button>
        <button onclick="showSection('create', document.querySelectorAll('.nav-link')[2])"
          class="px-6 py-3 rounded-xl font-display font-bold text-sm border border-white/20 text-white hover:bg-white/10 transition">
          + Créer un événement</button>
      </div>
    </div>
    <!-- stats hero -->
    <div class="grid grid-cols-2 gap-4">
      <div class="rounded-2xl p-5" style="background:rgba(255,255,255,.08)">
        <div class="stat-num text-white" id="h-total">—</div>
        <div class="text-white/50 text-sm mt-1 font-display">Événements</div>
      </div>
      <div class="rounded-2xl p-5" style="background:rgba(255,255,255,.08)">
        <div class="stat-num text-white" id="h-inscrits">—</div>
        <div class="text-white/50 text-sm mt-1 font-display">Inscrits</div>
      </div>
      <div class="rounded-2xl p-5" style="background:rgba(255,255,255,.08)">
        <div class="stat-num" style="color:#f59e0b" id="h-complets">—</div>
        <div class="text-white/50 text-sm mt-1 font-display">Complets</div>
      </div>
      <div class="rounded-2xl p-5" style="background:rgba(255,255,255,.08)">
        <div class="stat-num text-teal-400" id="h-new24">—</div>
        <div class="text-white/50 text-sm mt-1 font-display">Nouvelles 24h</div>
      </div>
    </div>
  </div>
</section>

<!-- MAIN -->
<main class="max-w-6xl mx-auto px-6 py-10">

  <!-- ── EVENTS ── -->
  <section id="sec-events">
    <!-- Filtres -->
    <div class="flex flex-col md:flex-row gap-4 mb-8 items-start md:items-center">
      <div class="relative flex-1">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"
          fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input id="search-input" type="text" placeholder="Rechercher un événement…"
          class="form-input pl-10" oninput="debounceSearch()" />
      </div>
      <div class="flex gap-2 flex-wrap">
        <select id="filter-cat" class="form-input w-auto text-sm" onchange="loadEvents()">
          <option value="">Toutes catégories</option>
          <option value="tech">Tech</option>
          <option value="design">Design</option>
          <option value="business">Business</option>
          <option value="science">Science</option>
        </select>
        <select id="filter-places" class="form-input w-auto text-sm" onchange="loadEvents()">
          <option value="">Toutes places</option>
          <option value="1">Avec places dispo</option>
        </select>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-7 bg-white border border-slate-200 p-1 rounded-full w-fit shadow-sm">
      <button class="tab-btn active" onclick="filterTab('all',this)">Tous</button>
      <button class="tab-btn" onclick="filterTab('upcoming',this)">À venir</button>
      <button class="tab-btn" onclick="filterTab('full',this)">Complets</button>
    </div>

    <!-- Grid -->
    <div id="events-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6"></div>

    <!-- TODO info -->
    <div class="mt-8 p-5 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50">
      <p class="font-display font-bold text-amber-900 text-sm mb-2">🔧 TODO — Partie 1.3 + Partie 4.1</p>
      <div class="todo-block">// api/events.php → retourner les événements en JSON
// assets/js/app.js → loadEvents() : vrai fetch() + rendu des cartes
// Remplacer les données mock par l'appel réel</div>
      <p class="text-xs text-amber-800 mt-2">Les cartes affichées utilisent des <b>données mock locales</b>. Votre mission : les remplacer par un vrai appel AJAX vers api/events.php</p>
    </div>
  </section>

  <!-- ── DASHBOARD ── -->
  <section id="sec-dashboard" class="hidden">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h2 class="font-display text-2xl font-bold text-slate-900">Dashboard Organisateur</h2>
        <p class="text-slate-500 text-sm mt-1">Mise à jour automatique toutes les <b class="text-blue-600">30s</b></p>
      </div>
      <div class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-full shadow-sm">
        <div class="w-2.5 h-2.5 rounded-full bg-green-400 animate-pulse"></div>
        <span id="last-update" class="text-xs text-slate-500">En attente…</span>
      </div>
    </div>

    <!-- KPI -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="kpi">
        <p class="text-xs font-display font-bold text-slate-500 uppercase tracking-widest mb-3">Total inscrits</p>
        <div class="stat-num text-slate-900" id="d-total">—</div>
        <p class="text-xs text-slate-400 mt-2">tous événements</p>
      </div>
      <div class="kpi">
        <p class="text-xs font-display font-bold text-slate-500 uppercase tracking-widest mb-3">Nouvelles 24h</p>
        <div class="stat-num text-green-600" id="d-new">—</div>
        <p class="text-xs text-slate-400 mt-2">inscriptions récentes</p>
      </div>
      <div class="kpi">
        <p class="text-xs font-display font-bold text-slate-500 uppercase tracking-widest mb-3">Taux moyen</p>
        <div class="stat-num text-amber-500" id="d-taux">—</div>
        <p class="text-xs text-slate-400 mt-2">de remplissage</p>
      </div>
      <div class="kpi">
        <p class="text-xs font-display font-bold text-slate-500 uppercase tracking-widest mb-3">Alertes 80%</p>
        <div class="stat-num text-red-500" id="d-alert">—</div>
        <p class="text-xs text-slate-400 mt-2">seuil atteint</p>
      </div>
    </div>

    <!-- Top 3 -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm mb-6">
      <h3 class="font-display font-bold text-slate-900 text-base mb-5">Top 3 — Événements les plus remplis</h3>
      <div id="top-list" class="space-y-4"></div>
    </div>

    <!-- TODO -->
    <div class="p-5 rounded-xl border-2 border-dashed border-blue-300 bg-blue-50">
      <p class="font-display font-bold text-blue-900 text-sm mb-2">🔧 TODO — Partie 4.2</p>
      <div class="todo-block">// api/stats.php → endpoint JSON à créer
// fetch toutes les 30s → mettre à jour les KPI
// Notification toast si un événement passe à 100%
// Si erreur API → réessayer après 10s sans casser l'interface</div>
    </div>
  </section>

  <!-- ── CREATE ── -->
  <section id="sec-create" class="hidden">
    <div class="max-w-2xl mx-auto">
      <div class="mb-8">
        <h2 class="font-display text-2xl font-bold text-slate-900">Créer un événement</h2>
        <p class="text-slate-500 text-sm mt-1">Les champs marqués * sont obligatoires.</p>
      </div>

      <div class="bg-white rounded-2xl p-8 border border-slate-200 shadow-sm">
        <div class="space-y-5">
          <div>
            <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Titre *</label>
            <input type="text" id="f-title" class="form-input" placeholder="Ex : DevFest Marrakech 2025" />
          </div>
          <div>
            <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Description *</label>
            <textarea id="f-desc" rows="3" class="form-input resize-none" placeholder="Décrivez votre événement…"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Date *</label>
              <input type="datetime-local" id="f-date" class="form-input" />
            </div>
            <div>
              <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Lieu *</label>
              <input type="text" id="f-lieu" class="form-input" placeholder="Ex : ENSA Marrakech" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Capacité *</label>
              <input type="number" id="f-cap" min="1" class="form-input" placeholder="50" />
            </div>
            <div>
              <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Catégorie *</label>
              <select id="f-cat" class="form-input">
                <option value="">— Choisir —</option>
                <option value="tech">Tech</option>
                <option value="design">Design</option>
                <option value="business">Business</option>
                <option value="science">Science</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Email organisateur *</label>
            <input type="email" id="f-email" class="form-input" placeholder="organisateur@example.ma" />
          </div>

          <!-- TODO -->
          <div class="p-4 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50">
            <p class="font-display font-bold text-amber-900 text-xs mb-1">🔧 TODO — Partie 1.2 + Partie 4.1</p>
            <div class="todo-block">// events/create.php → corriger createEvent() (injection SQL !)
// submitCreate() → POST fetch() vers events/create.php
// Toast succès / erreur selon la réponse JSON</div>
          </div>

          <button onclick="submitCreate()"
            class="w-full py-3 rounded-xl font-display font-bold text-sm text-white flex items-center justify-center gap-2"
            style="background:#2563eb" id="btn-create">
            <span id="lbl-create">Créer l'événement</span>
            <span id="spn-create" class="spinner hidden"></span>
          </button>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- MODAL INSCRIPTION -->
<div id="modal-reg" class="modal-overlay hidden">
  <div class="modal-box">
    <div class="p-6 border-b border-slate-100 flex items-start justify-between">
      <div>
        <h3 class="font-display font-bold text-slate-900 text-lg" id="m-title">Inscription</h3>
        <p class="text-slate-500 text-sm mt-1" id="m-info">—</p>
      </div>
      <button onclick="closeReg()" class="text-slate-400 hover:text-slate-700 text-xl font-bold leading-none">✕</button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Nom complet *</label>
        <input id="r-name" type="text" class="form-input" placeholder="Votre nom" />
      </div>
      <div>
        <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Email *</label>
        <input id="r-email" type="email" class="form-input" placeholder="votre@email.ma" />
      </div>
      <div class="rounded-xl p-4 bg-slate-50 border border-slate-200">
        <div class="flex justify-between text-xs font-display font-bold mb-2">
          <span class="text-slate-500">Places restantes</span>
          <span class="text-slate-900" id="m-places">—</span>
        </div>
        <div class="cap-bar"><div class="cap-bar-fill" id="m-bar" style="width:0%;background:#2563eb"></div></div>
      </div>

      <div class="p-3 rounded-lg border border-dashed border-amber-300 bg-amber-50">
        <p class="font-display font-bold text-amber-900 text-xs mb-1">🔧 TODO — Partie 2.1 + 4.1</p>
        <div class="todo-block" style="font-size:.75rem">// POST vers events/register.php
// Email confirmation + détection seuil 80% → email alerte PDF
// Mise à jour compteur sur la carte SANS rechargement</div>
      </div>

      <button onclick="submitReg()" id="btn-reg"
        class="w-full py-3 rounded-xl font-display font-bold text-sm text-white flex items-center justify-center gap-2"
        style="background:#2563eb">
        <span id="lbl-reg">S'inscrire &amp; recevoir le ticket PDF</span>
        <span id="spn-reg" class="spinner hidden"></span>
      </button>
    </div>
  </div>
</div>

<!-- MODAL LOGIN -->
<div id="modal-login" class="modal-overlay hidden">
  <div class="modal-box" style="max-width:380px">
    <div class="p-6 border-b border-slate-100">
      <h3 class="font-display font-bold text-slate-900 text-lg">Connexion Organisateur</h3>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Email</label>
        <input type="email" class="form-input" placeholder="admin@ensa.ma" />
      </div>
      <div>
        <label class="block text-xs font-display font-bold text-slate-600 uppercase tracking-widest mb-1">Mot de passe</label>
        <input type="password" class="form-input" placeholder="••••••••" />
      </div>
      <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
        <p class="text-xs text-blue-800 font-display">ℹ️ Auth via sessions PHP — à implémenter dans <b>auth/login.php</b></p>
      </div>
      <button onclick="fakeLogin()"
        class="w-full py-3 rounded-xl font-display font-bold text-sm text-white"
        style="background:#0f1f3d">Se connecter</button>
      <button onclick="document.getElementById('modal-login').classList.add('hidden')"
        class="w-full py-2 text-sm text-slate-400 hover:text-slate-600">Annuler</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<!-- FOOTER -->
<footer class="mt-16 border-t border-slate-200 py-8 px-6 bg-white">
  <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-2">
      <span class="font-display font-bold text-sm text-slate-900">EventHub Pro</span>
      <span class="text-slate-300">·</span>
      <span class="text-xs text-slate-400">MVP Examen PHP · 4ème Année · ENSA Marrakech · Univ. Cadi Ayyad</span>
    </div>
    <div class="flex gap-3 text-xs text-slate-400 font-display">
      <span>PDO</span><span>·</span>
      <span>PHPMailer</span><span>·</span>
      <span>TCPDF / Dompdf</span><span>·</span>
      <span>Fetch API</span>
    </div>
  </div>
</footer>

<script>
// ── MOCK DATA ──────────────────────────────────────────────────
const MOCK = [
  { id:1, title:"DevFest Marrakech 2025", cat:"tech",
    date:"2025-09-20T09:00", loc:"ENSA Marrakech", cap:200, reg:162,
    desc:"La grande conférence tech de Marrakech. Talks, ateliers et networking.", color:"#2563eb" },
  { id:2, title:"UX Design Workshop", cat:"design",
    date:"2025-07-28T14:00", loc:"École Nationale des Arts, Marrakech", cap:30, reg:30,
    desc:"Atelier intensif UX : prototypage, tests utilisateurs, Figma avancé.", color:"#7c3aed" },
  { id:3, title:"Hackathon FinTech Maroc", cat:"tech",
    date:"2025-08-15T08:00", loc:"CBI Marrakech", cap:80, reg:52,
    desc:"48h pour construire une solution fintech innovante. Prix : 50 000 MAD.", color:"#0d9488" },
  { id:4, title:"Conférence IA & Médecine", cat:"science",
    date:"2025-10-10T10:00", loc:"Hôpital Ibn Tofail, Marrakech", cap:120, reg:97,
    desc:"Comment l'IA transforme le diagnostic médical au Maroc.", color:"#dc2626" },
  { id:5, title:"Startup Weekend Marrakech", cat:"business",
    date:"2025-08-30T18:00", loc:"Université Cadi Ayyad", cap:60, reg:20,
    desc:"54h pour lancer votre startup. Mentors, jury, pitchs et réseautage.", color:"#ea580c" },
  { id:6, title:"PHP & MVC Day", cat:"tech",
    date:"2025-11-08T09:30", loc:"ENSA Marrakech — Amphi A", cap:5, reg:4,
    desc:"Journée PHP 8.x, MVC natif, bonnes pratiques et sécurité.", color:"#0f1f3d" },
];

let currentTab = 'all';
let selected   = null;
let debTimer   = null;
let dashTimer  = null;

// ── NAVIGATION ─────────────────────────────────────────────────
function showSection(id, btn) {
  ['events','dashboard','create'].forEach(s =>
    document.getElementById('sec-'+s).classList.toggle('hidden', s !== id));
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  if (id === 'events')    { loadEvents(); updateHero(); }
  if (id === 'dashboard') { startDash(); }
}

// ── LOAD EVENTS (TODO: remplacer par vrai fetch) ───────────────
async function loadEvents() {
  const kw   = document.getElementById('search-input').value.toLowerCase();
  const cat  = document.getElementById('filter-cat').value;
  const pl   = document.getElementById('filter-places').value;

  // TODO: fetch('api/events.php', { method:'POST', body:JSON.stringify({kw,cat,pl,tab:currentTab}) })
  showSkeletons();
  await sleep(500); // simule délai réseau

  let list = MOCK.filter(e => {
    if (cat && e.cat !== cat) return false;
    if (pl === '1' && e.reg >= e.cap) return false;
    if (kw && !e.title.toLowerCase().includes(kw)) return false;
    if (currentTab === 'upcoming' && e.reg >= e.cap) return false;
    if (currentTab === 'full'     && e.reg < e.cap)  return false;
    return true;
  });
  renderCards(list);
  updateHero();
}

function filterTab(tab, el) {
  currentTab = tab;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  loadEvents();
}

// ── RENDER CARDS ───────────────────────────────────────────────
const CAT_STYLE = {
  tech:     { bg:'#dbeafe', tx:'#1d4ed8' },
  design:   { bg:'#ede9fe', tx:'#6d28d9' },
  business: { bg:'#fef3c7', tx:'#b45309' },
  science:  { bg:'#dcfce7', tx:'#15803d' },
};

function renderCards(list) {
  const grid = document.getElementById('events-grid');
  if (!list.length) {
    grid.innerHTML = `<div class="col-span-3 text-center py-16">
      <div class="text-5xl mb-4">🔍</div>
      <p class="font-display font-bold text-slate-600">Aucun événement trouvé</p>
      <p class="text-slate-400 text-sm mt-2">Modifiez vos filtres</p></div>`;
    return;
  }
  grid.innerHTML = list.map(e => {
    const pct  = Math.round(e.reg / e.cap * 100);
    const full = e.reg >= e.cap;
    const warn = pct >= 80 && !full;
    const bar  = full ? '#dc2626' : warn ? '#f59e0b' : e.color;
    const cs   = CAT_STYLE[e.cat] || { bg:'#f1f5f9', tx:'#334155' };
    const d    = new Date(e.date).toLocaleDateString('fr-FR',
      { weekday:'short', day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' });
    const remaining = e.cap - e.reg;
    return `
    <div class="event-card bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col shadow-sm" data-id="${e.id}">
      <div class="h-2" style="background:${e.color}"></div>
      <div class="p-5 flex flex-col flex-1">
        <div class="flex items-start gap-2 mb-3 flex-wrap">
          <span class="badge" style="background:${cs.bg};color:${cs.tx}">${e.cat}</span>
          ${full ? '<span class="badge" style="background:#fee2e2;color:#dc2626">Complet</span>'
            : warn ? '<span class="badge" style="background:#fef3c7;color:#b45309">🔥 Quasi plein</span>' : ''}
        </div>
        <h3 class="font-display font-bold text-base text-slate-900 mb-1 leading-snug">${e.title}</h3>
        <p class="text-xs text-slate-500 mb-1 flex items-center gap-1">
          <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${d}
        </p>
        <p class="text-xs text-slate-500 mb-3 flex items-center gap-1">
          <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/></svg>${e.loc}
        </p>
        <p class="text-xs text-slate-600 leading-relaxed flex-1">${e.desc}</p>
        <div class="mt-4">
          <div class="flex justify-between text-xs font-display font-bold mb-1">
            <span class="text-slate-500">Capacité</span>
            <span style="color:${bar}" id="pl-${e.id}">${e.reg} / ${e.cap}</span>
          </div>
          <div class="cap-bar">
            <div class="cap-bar-fill" id="bar-${e.id}" style="width:${pct}%;background:${bar}"></div>
          </div>
          ${!full ? `<p class="text-xs text-slate-400 mt-1">${remaining} place${remaining>1?'s':''} restante${remaining>1?'s':''}</p>` : ''}
        </div>
        <button
          ${full ? 'disabled' : `onclick="openReg(${e.id})"`}
          id="btn-${e.id}"
          class="mt-4 w-full py-2.5 rounded-xl font-display font-bold text-xs text-white tracking-wide transition
            ${full ? 'opacity-40 cursor-not-allowed' : 'hover:opacity-90'}"
          style="background:${full ? '#94a3b8' : e.color}">
          ${full ? 'Complet' : "S'inscrire →"}
        </button>
      </div>
    </div>`;
  }).join('');
}

// ── MODAL INSCRIPTION ──────────────────────────────────────────
function openReg(id) {
  selected = MOCK.find(e => e.id === id);
  if (!selected) return;
  const pct = Math.round(selected.reg / selected.cap * 100);
  const rem = selected.cap - selected.reg;
  document.getElementById('m-title').textContent = selected.title;
  document.getElementById('m-info').textContent  =
    new Date(selected.date).toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'})
    + ' · ' + selected.loc;
  document.getElementById('m-places').textContent = `${rem} place${rem>1?'s':''} restante${rem>1?'s':''}`;
  document.getElementById('m-bar').style.width      = pct + '%';
  document.getElementById('m-bar').style.background = pct>=80 ? '#f59e0b' : '#2563eb';
  document.getElementById('modal-reg').classList.remove('hidden');
}
function closeReg() {
  document.getElementById('modal-reg').classList.add('hidden');
  selected = null;
}

async function submitReg() {
  const name  = document.getElementById('r-name').value.trim();
  const email = document.getElementById('r-email').value.trim();
  if (!name || !email) { toast('Remplissez tous les champs', 'error'); return; }
  if (!selected) return;

  setLoad('btn-reg','lbl-reg','spn-reg', true, 'Inscription…');
  // TODO: fetch('events/register.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({eventId:selected.id,name,email}) })
  await sleep(1100);

  selected.reg++;
  const pct = Math.round(selected.reg / selected.cap * 100);
  const plEl  = document.getElementById(`pl-${selected.id}`);
  const barEl = document.getElementById(`bar-${selected.id}`);
  const btnEl = document.getElementById(`btn-${selected.id}`);

  if (plEl)  plEl.textContent = `${selected.reg} / ${selected.cap}`;
  if (barEl) barEl.style.width = pct + '%';
  if (selected.reg >= selected.cap && btnEl) {
    btnEl.disabled = true; btnEl.textContent = 'Complet';
    btnEl.style.background = '#94a3b8';
    btnEl.classList.add('opacity-40','cursor-not-allowed');
    toast(`🎉 ${selected.title} est maintenant complet !`, 'info');
  }
  if (pct >= 80 && pct < 100) toast(`⚠️ Alerte : ${selected.title} est à ${pct}% — email envoyé à l'organisateur`, 'info');

  closeReg();
  toast('Inscription réussie ! Votre ticket PDF sera envoyé par email.', 'success');
  setLoad('btn-reg','lbl-reg','spn-reg', false, "S'inscrire & recevoir le ticket PDF");
  updateHero();
}

// ── CREATE ──────────────────────────────────────────────────────
async function submitCreate() {
  const title = document.getElementById('f-title').value.trim();
  const email = document.getElementById('f-email').value.trim();
  if (!title || !email) { toast('Remplissez au moins le titre et l\'email', 'error'); return; }
  setLoad('btn-create','lbl-create','spn-create', true, 'Création…');
  // TODO: fetch('events/create.php', { method:'POST', body:JSON.stringify({...}) })
  await sleep(1000);
  toast('Événement créé avec succès !', 'success');
  ['f-title','f-desc','f-lieu','f-email'].forEach(id => document.getElementById(id).value='');
  setLoad('btn-create','lbl-create','spn-create', false, "Créer l'événement");
}

// ── DASHBOARD ───────────────────────────────────────────────────
function startDash() {
  fetchStats();
  if (dashTimer) clearInterval(dashTimer);
  dashTimer = setInterval(fetchStats, 30000);
}

async function fetchStats() {
  // TODO: fetch('api/stats.php') → JSON → mise à jour KPI
  await sleep(250);
  const total  = MOCK.reduce((s,e)=>s+e.reg,0);
  const new24  = Math.floor(Math.random()*10)+4;
  const taux   = Math.round(MOCK.reduce((s,e)=>s+e.reg/e.cap*100,0)/MOCK.length);
  const alerts = MOCK.filter(e=>e.reg/e.cap>=.8).length;

  anim('d-total', total);
  anim('d-new',   new24);
  document.getElementById('d-taux').textContent  = taux + '%';
  document.getElementById('d-alert').textContent = alerts;

  // Top 3
  const top = [...MOCK].sort((a,b)=> b.reg/b.cap - a.reg/a.cap).slice(0,3);
  const cs = CAT_STYLE;
  document.getElementById('top-list').innerHTML = top.map((e,i)=>{
    const pct = Math.round(e.reg/e.cap*100);
    const bar = pct>=80 ? '#f59e0b' : '#2563eb';
    return `<div class="flex items-center gap-4 p-3 rounded-xl bg-slate-50">
      <span class="font-display font-black text-2xl text-slate-200">0${i+1}</span>
      <div class="flex-1">
        <p class="font-display font-bold text-sm text-slate-900 mb-1">${e.title}</p>
        <div class="cap-bar"><div class="cap-bar-fill" style="width:${pct}%;background:${bar}"></div></div>
      </div>
      <span class="badge font-display" style="background:${pct>=100?'#fee2e2':pct>=80?'#fef3c7':'#dbeafe'};color:${pct>=100?'#dc2626':pct>=80?'#b45309':'#1d4ed8'}">${pct}%</span>
    </div>`;
  }).join('');

  document.getElementById('last-update').textContent =
    'Mis à jour à ' + new Date().toLocaleTimeString('fr-FR');
}

// ── HERO STATS ──────────────────────────────────────────────────
function updateHero() {
  anim('h-total',    MOCK.length);
  anim('h-inscrits', MOCK.reduce((s,e)=>s+e.reg,0));
  anim('h-complets', MOCK.filter(e=>e.reg>=e.cap).length);
  anim('h-new24',    Math.floor(Math.random()*8)+3);
}

// ── DEBOUNCE ────────────────────────────────────────────────────
function debounceSearch() {
  clearTimeout(debTimer);
  debTimer = setTimeout(loadEvents, 400); // TODO: fetch avec délai 400ms
}

// ── UTILS ───────────────────────────────────────────────────────
function showSkeletons() {
  document.getElementById('events-grid').innerHTML = Array(3).fill(`
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
      <div class="skeleton h-2 w-full mb-4 -mx-5 -mt-5" style="width:calc(100%+40px);border-radius:0"></div>
      <div class="skeleton h-5 w-3/4 mb-2 mt-2"></div>
      <div class="skeleton h-3 w-1/2 mb-1"></div>
      <div class="skeleton h-3 w-2/3 mb-4"></div>
      <div class="skeleton h-2 w-full mb-4"></div>
      <div class="skeleton h-9 w-28 rounded-xl"></div>
    </div>`).join('');
}

function toast(msg, type='info') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast ${type}`; t.textContent = msg;
  c.appendChild(t);
  setTimeout(()=>{ t.style.cssText='opacity:0;transform:translateX(120%);transition:all .3s';
    setTimeout(()=>t.remove(),300); }, 3500);
}

function setLoad(btn,lbl,spn,on,txt) {
  document.getElementById(btn).disabled = on;
  document.getElementById(spn).classList.toggle('hidden',!on);
  if(txt) document.getElementById(lbl).textContent = txt;
}

function anim(id, target) {
  const el = document.getElementById(id); if(!el) return;
  const start = parseInt(el.textContent)||0, diff=target-start, steps=20; let i=0;
  const iv=setInterval(()=>{ i++; el.textContent=Math.round(start+diff*(i/steps));
    if(i>=steps){el.textContent=target;clearInterval(iv);} },20);
}

function openLogin() { document.getElementById('modal-login').classList.remove('hidden'); }
function fakeLogin() {
  document.getElementById('modal-login').classList.add('hidden');
  toast('Connecté en tant qu\'organisateur', 'success');
}
function sleep(ms) { return new Promise(r=>setTimeout(r,ms)); }

// fermer modals au clic overlay
document.getElementById('modal-reg').addEventListener('click', e=>{ if(e.target===e.currentTarget)closeReg(); });
document.getElementById('modal-login').addEventListener('click', e=>{ if(e.target===e.currentTarget)e.currentTarget.classList.add('hidden'); });

// INIT
document.addEventListener('DOMContentLoaded', ()=>{ loadEvents(); updateHero(); });
</script>
</body>
</html>
