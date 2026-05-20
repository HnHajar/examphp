/**
 * assets/js/app.js — EventHub Pro
 * Partie 4.1 : Fetch API — loadEvents, registerToEvent, debounce, fetchStats, submitCreate
 *
 * INSTRUCTIONS D'INTÉGRATION dans index.php :
 *   Remplacer le bloc <script> inline par :
 *   <script src="assets/js/app.js"></script>
 *   (conserver le bloc MOCK + variables en haut du script si nécessaire en attendant l'API)
 */

// ── MOCK DATA (fallback si l'API est indisponible) ─────────────────────────
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

// ── NAVIGATION ─────────────────────────────────────────────────────────────
function showSection(id, btn) {
  ['events','dashboard','create'].forEach(s =>
    document.getElementById('sec-'+s).classList.toggle('hidden', s !== id));
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  if (id === 'events')    { loadEvents(); updateHero(); }
  if (id === 'dashboard') { startDash(); }
}

// ══════════════════════════════════════════════════════════════════
// TODO 1 — Chargement initial des événements
// Appel GET vers api/events.php avec filtres en query string.
// Gère : loading skeleton, erreur réseau, cas "aucun résultat".
// Fallback sur MOCK si l'API répond une erreur 5xx ou est absente.
// ══════════════════════════════════════════════════════════════════
async function loadEvents(filters = {}) {
  // Lecture des valeurs des filtres depuis le DOM (sauf si passées en argument)
  const kw  = filters.kw  ?? document.getElementById('search-input').value.trim();
  const cat = filters.cat ?? document.getElementById('filter-cat').value;
  const pl  = filters.pl  ?? document.getElementById('filter-places').value;
  const tab = filters.tab ?? currentTab;

  // Affichage du skeleton pendant le chargement
  showSkeletons();

  // Construction de la query string
  const params = new URLSearchParams();
  if (kw)  params.set('q',        kw);
  if (cat) params.set('category', cat);
  if (pl)  params.set('places',   pl);
  if (tab && tab !== 'all') params.set('tab', tab);
  params.set('page',  1);
  params.set('limit', 12);

  try {
    const res = await fetch('api/events.php?' + params.toString(), {
      method:  'GET',
      headers: { 'Accept': 'application/json' },
    });

    if (!res.ok) {
      // Erreur HTTP (4xx / 5xx) → fallback MOCK
      throw new Error(`HTTP ${res.status}`);
    }

    const data = await res.json();

    // L'API retourne { success, events: [...], total, page }
    if (!data.success) {
      throw new Error(data.error ?? 'Réponse API invalide');
    }

    renderCards(data.events);
    updateHero(data.stats ?? null);

  } catch (err) {
    console.warn('[loadEvents] Erreur API, fallback MOCK :', err.message);

    // Fallback : filtrage local sur MOCK
    let list = MOCK.filter(e => {
      if (cat && e.cat !== cat)                          return false;
      if (pl === '1' && e.reg >= e.cap)                 return false;
      if (kw  && !e.title.toLowerCase().includes(kw.toLowerCase())) return false;
      if (tab === 'upcoming' && e.reg >= e.cap)          return false;
      if (tab === 'full'     && e.reg <  e.cap)          return false;
      return true;
    });

    renderCards(list);
    updateHero();

    // Toast discret uniquement si pas une simple recherche vide
    if (kw || cat || pl) {
      toast('⚠️ API indisponible — données locales affichées', 'info');
    }
  }
}

function filterTab(tab, el) {
  currentTab = tab;
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  loadEvents();
}

// ══════════════════════════════════════════════════════════════════
// TODO 2 — Inscription en temps réel
// POST JSON vers events/register.php.
// Met à jour le compteur + la barre de capacité SANS rechargement.
// ══════════════════════════════════════════════════════════════════
async function submitReg() {
  const name  = document.getElementById('r-name').value.trim();
  const email = document.getElementById('r-email').value.trim();

  if (!name || !email) { toast('Remplissez tous les champs', 'error'); return; }
  if (!selected)       return;

  setLoad('btn-reg', 'lbl-reg', 'spn-reg', true, 'Inscription…');

  try {
    const res = await fetch('events/register.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ eventId: selected.id, name, email }),
    });

    const data = await res.json();

    if (!res.ok || !data.success) {
      // Erreur métier (doublon, événement complet, etc.)
      toast(data.error ?? 'Erreur lors de l\'inscription.', 'error');
      setLoad('btn-reg', 'lbl-reg', 'spn-reg', false, "S'inscrire & recevoir le ticket PDF");
      return;
    }

    // ── Mise à jour en temps réel (sans rechargement) ──────────────
    // L'API renvoie le nouveau total { reg_count, capacity, pct }
    const newReg = data.reg_count ?? (selected.reg + 1);
    const cap    = data.capacity  ?? selected.cap;
    const pct    = Math.round((newReg / cap) * 100);
    const bar    = pct >= 100 ? '#dc2626' : pct >= 80 ? '#f59e0b' : selected.color;

    // Mise à jour du modèle local (pour cohérence des filtres tab)
    const mock = MOCK.find(e => e.id === selected.id);
    if (mock) mock.reg = newReg;
    selected.reg = newReg;

    // Mise à jour du DOM de la carte
    const plEl  = document.getElementById(`pl-${selected.id}`);
    const barEl = document.getElementById(`bar-${selected.id}`);
    const btnEl = document.getElementById(`btn-${selected.id}`);

    if (plEl)  plEl.textContent       = `${newReg} / ${cap}`;
    if (barEl) { barEl.style.width      = pct + '%'; barEl.style.background = bar; }

    if (newReg >= cap && btnEl) {
      btnEl.disabled = true;
      btnEl.textContent = 'Complet';
      btnEl.style.background = '#94a3b8';
      btnEl.classList.add('opacity-40', 'cursor-not-allowed');
      toast(`🎉 ${selected.title} est maintenant complet !`, 'info');
    } else if (pct >= 80) {
      toast(`⚠️ Alerte : ${selected.title} est à ${pct}% — email envoyé à l'organisateur`, 'info');
    }

    closeReg();
    toast('Inscription réussie ! Votre ticket PDF sera envoyé par email.', 'success');
    updateHero();

  } catch (err) {
    console.error('[submitReg] Erreur réseau :', err);
    toast('Erreur réseau. Vérifiez votre connexion.', 'error');
  } finally {
    setLoad('btn-reg', 'lbl-reg', 'spn-reg', false, "S'inscrire & recevoir le ticket PDF");
  }
}

// ══════════════════════════════════════════════════════════════════
// TODO 3 — Recherche live avec debounce 400 ms
// Déclenche loadEvents() 400 ms après la dernière frappe.
// Annule le timer précédent à chaque nouvel appui (debounce classique).
// ══════════════════════════════════════════════════════════════════
function debounceSearch() {
  clearTimeout(debTimer);
  debTimer = setTimeout(() => loadEvents(), 400);
}

// ── MODAL INSCRIPTION ──────────────────────────────────────────────────────
function openReg(id) {
  // Priorité à la donnée réelle ; fallback MOCK
  selected = MOCK.find(e => e.id === id) ?? { id };
  if (!selected) return;

  const pct = Math.round(selected.reg / selected.cap * 100);
  const rem = selected.cap - selected.reg;

  document.getElementById('m-title').textContent = selected.title;
  document.getElementById('m-info').textContent  =
    new Date(selected.date).toLocaleDateString('fr-FR',
      { day:'numeric', month:'long', year:'numeric' })
    + ' · ' + selected.loc;
  document.getElementById('m-places').textContent  = `${rem} place${rem>1?'s':''} restante${rem>1?'s':''}`;
  document.getElementById('m-bar').style.width      = pct + '%';
  document.getElementById('m-bar').style.background = pct >= 80 ? '#f59e0b' : '#2563eb';

  document.getElementById('modal-reg').classList.remove('hidden');
}

function closeReg() {
  document.getElementById('modal-reg').classList.add('hidden');
  selected = null;
  // Réinitialiser les champs
  ['r-name','r-email'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
}

// ── RENDER CARDS ───────────────────────────────────────────────────────────
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
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2v12a2 2 0 002 2z"/></svg>${d}
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

// ── CREATE ─────────────────────────────────────────────────────────────────
async function submitCreate() {
  const title    = document.getElementById('f-title').value.trim();
  const desc     = document.getElementById('f-desc').value.trim();
  const date     = document.getElementById('f-date').value;
  const location = document.getElementById('f-lieu').value.trim();
  const email    = document.getElementById('f-email').value.trim();
  const capacity = document.getElementById('f-capacity')?.value ?? 50;
  const category = document.getElementById('f-cat')?.value ?? '';

  if (!title || !email) {
    toast('Remplissez au moins le titre et l\'email', 'error');
    return;
  }

  setLoad('btn-create', 'lbl-create', 'spn-create', true, 'Création…');

  try {
    const res = await fetch('events/create.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify({ title, description: desc, date, location, email, capacity, category }),
    });

    const data = await res.json();

    if (!res.ok || !data.success) {
      toast(data.error ?? 'Erreur lors de la création.', 'error');
      return;
    }

    toast('✅ Événement créé avec succès !', 'success');
    ['f-title','f-desc','f-lieu','f-email'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    // Revenir à la liste et recharger
    setTimeout(() => {
      showSection('events', document.querySelectorAll('.nav-link')[0]);
    }, 800);

  } catch (err) {
    console.error('[submitCreate] Erreur réseau :', err);
    toast('Erreur réseau. Vérifiez votre connexion.', 'error');
  } finally {
    setLoad('btn-create', 'lbl-create', 'spn-create', false, "Créer l'événement");
  }
}

// ── DASHBOARD ──────────────────────────────────────────────────────────────
function startDash() {
  fetchStats();
  if (dashTimer) clearInterval(dashTimer);
  dashTimer = setInterval(fetchStats, 30000);
}

async function fetchStats() {
  try {
    const res = await fetch('api/stats.php', {
      method:  'GET',
      headers: { 'Accept': 'application/json' },
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Erreur stats');

    // ── Mise à jour KPI ───────────────────────────────────────────
    anim('d-total', data.total_registrations ?? 0);
    anim('d-new',   data.new_last_24h        ?? 0);
    document.getElementById('d-taux').textContent  = (data.avg_fill_rate  ?? 0) + '%';
    document.getElementById('d-alert').textContent = data.alert_count     ?? 0;

    // Toast si un événement vient de passer à 100%
    if (data.newly_full && data.newly_full.length > 0) {
      data.newly_full.forEach(title =>
        toast(`🎉 "${title}" vient d'atteindre 100% !`, 'info')
      );
    }

    // ── Top 3 ─────────────────────────────────────────────────────
    if (Array.isArray(data.top_events)) {
      document.getElementById('top-list').innerHTML = data.top_events.slice(0, 3).map((e, i) => {
        const pct = Math.round(e.fill_rate ?? 0);
        const bar = pct >= 80 ? '#f59e0b' : '#2563eb';
        return `<div class="flex items-center gap-4 p-3 rounded-xl bg-slate-50">
          <span class="font-display font-black text-2xl text-slate-200">0${i+1}</span>
          <div class="flex-1">
            <p class="font-display font-bold text-sm text-slate-900 mb-1">${e.title}</p>
            <div class="cap-bar"><div class="cap-bar-fill" style="width:${pct}%;background:${bar}"></div></div>
          </div>
          <span class="badge font-display"
            style="background:${pct>=100?'#fee2e2':pct>=80?'#fef3c7':'#dbeafe'};
                   color:${pct>=100?'#dc2626':pct>=80?'#b45309':'#1d4ed8'}">${pct}%</span>
        </div>`;
      }).join('');
    }

    document.getElementById('last-update').textContent =
      'Mis à jour à ' + new Date().toLocaleTimeString('fr-FR');

  } catch (err) {
    console.warn('[fetchStats] Erreur API :', err.message, '— retry dans 10s');

    // Fallback MOCK silencieux
    const total  = MOCK.reduce((s, e) => s + e.reg, 0);
    const taux   = Math.round(MOCK.reduce((s, e) => s + e.reg / e.cap * 100, 0) / MOCK.length);
    const alerts = MOCK.filter(e => e.reg / e.cap >= 0.8).length;

    anim('d-total', total);
    anim('d-new',   0);
    document.getElementById('d-taux').textContent  = taux + '%';
    document.getElementById('d-alert').textContent = alerts;

    // Retry après 10s (sans casser l'interface)
    if (dashTimer) { clearInterval(dashTimer); dashTimer = null; }
    setTimeout(() => {
      dashTimer = setInterval(fetchStats, 30000);
      fetchStats();
    }, 10000);

    document.getElementById('last-update').textContent = '⚠️ API indisponible — retry dans 10s';
  }
}

// ── HERO STATS ─────────────────────────────────────────────────────────────
function updateHero(stats = null) {
  if (stats) {
    // Données depuis l'API
    anim('h-total',    stats.total_events   ?? MOCK.length);
    anim('h-inscrits', stats.total_reg      ?? MOCK.reduce((s, e) => s + e.reg, 0));
    anim('h-complets', stats.full_events    ?? MOCK.filter(e => e.reg >= e.cap).length);
    anim('h-new24',    stats.new_last_24h   ?? 0);
  } else {
    // Fallback MOCK
    anim('h-total',    MOCK.length);
    anim('h-inscrits', MOCK.reduce((s, e) => s + e.reg, 0));
    anim('h-complets', MOCK.filter(e => e.reg >= e.cap).length);
    anim('h-new24',    Math.floor(Math.random() * 8) + 3);
  }
}

// ── UTILS ──────────────────────────────────────────────────────────────────
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

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className   = `toast ${type}`;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => {
    t.style.cssText = 'opacity:0;transform:translateX(120%);transition:all .3s';
    setTimeout(() => t.remove(), 300);
  }, 3500);
}

function setLoad(btn, lbl, spn, on, txt) {
  document.getElementById(btn).disabled = on;
  document.getElementById(spn).classList.toggle('hidden', !on);
  if (txt) document.getElementById(lbl).textContent = txt;
}

function anim(id, target) {
  const el = document.getElementById(id); if (!el) return;
  const start = parseInt(el.textContent) || 0, diff = target - start, steps = 20; let i = 0;
  const iv = setInterval(() => {
    i++;
    el.textContent = Math.round(start + diff * (i / steps));
    if (i >= steps) { el.textContent = target; clearInterval(iv); }
  }, 20);
}

function openLogin() { document.getElementById('modal-login').classList.remove('hidden'); }
function fakeLogin() {
  document.getElementById('modal-login').classList.add('hidden');
  toast('Connecté en tant qu\'organisateur', 'success');
}

// ── INIT ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  // Fermer modals au clic sur l'overlay
  document.getElementById('modal-reg').addEventListener('click',
    e => { if (e.target === e.currentTarget) closeReg(); });
  document.getElementById('modal-login').addEventListener('click',
    e => { if (e.target === e.currentTarget) e.currentTarget.classList.add('hidden'); });

  loadEvents();
  updateHero();
});