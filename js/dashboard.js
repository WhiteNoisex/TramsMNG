// --- Auth & API base ---
const masterServer = "https://web.interfacetools.com/TramsMNG";
const username = sessionStorage.getItem("username") || "Guest";
const token = sessionStorage.getItem("token") || "";
const roleFromSession = sessionStorage.getItem("role") || ""; // optional
const notice = document.getElementById("notice");

// --- UI bits ---
const userNameEl = document.getElementById("userName");
const userRoleEl = document.getElementById("userRole");
const logoutBtn  = document.getElementById("logoutBtn");

// Guard: redirect if not logged in
if (!token || username === "Guest") {
  // Show public section only
  showSections(["Public"]);
} else {
  // Fetch role from API (fallback to session or Public)
  bootstrap();
}

userNameEl.textContent = username;

// --- Bootstrap: load role, then render sections
async function bootstrap() {
  try {
    userRoleEl.textContent = role;
    showSections(visibleRolesFor(role));
  } catch (e) {
    userRoleEl.textContent = "Public";
    showNotice("Could not verify role, showing limited features.", true);
    showSections(["Public"]);
  }
}

// Map role -> sections we show
function visibleRolesFor(role) {
  // Normalize common variants
  const r = String(role || "").toLowerCase();
  if (r.includes("admin"))        return ["Admin","Maintenance","Driver"]; // Admin sees everything
  if (r.includes("maint"))        return ["Maintenance"];
  if (r.includes("driver"))       return ["Driver"];
  return ["Public"];
}

// Show only allowed role sections
function showSections(roles) {
  document.querySelectorAll("section.grid").forEach(sec => {
    const role = sec.getAttribute("data-role");
    sec.hidden = !roles.includes(role);
  });
}

// Basic notice helpers
function showNotice(msg, isError=false) {
  if (!notice) return;
  notice.hidden = false;
  notice.textContent = msg;
  notice.style.color = isError ? "#ffd0d0" : "#eaffff";
}
function hideNotice(){ if (notice) notice.hidden = true; }

// --- Click actions (wire up minimal handlers/placeholders) ---
document.addEventListener("click", (e) => {
  const btn = e.target.closest("[data-action]");
  if (!btn) return;
  const action = btn.getAttribute("data-action");
  const handlers = {
    "driver.loadAssigned": driverLoadAssigned,
    "driver.newDamage": driverNewDamage,
    "driver.newService": driverNewService,

    "maint.loadQueue": maintLoadQueue,
    "maint.record": maintRecord,
    "maint.inventory": maintInventory,

    "admin.users": adminUsers,
    "admin.import": adminImport,
    "admin.export": adminExport,
    "admin.logs": adminLogs,
  };
  if (handlers[action]) handlers[action]();
});

// --- Driver handlers ---
async function driverLoadAssigned() {
  const el = document.getElementById("driverAssigned");
  el.textContent = "Loading…";
  try {
    const res = await fetch(`${masterServer}/api/GetDriverAssigned.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ Username: username, Token: token })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const rows = await res.json();
    el.innerHTML = renderList(rows, ["ID","Tram_Name","Service_State","Operating_Year_S","Operating_Year_E"]);
  } catch (e) {
    el.textContent = `Failed to load: ${e.message}`;
  }
}

function driverNewDamage() {
  // Navigate to a damage report page or open a modal in your app
  location.href = "?A=damage_report.html";
}

function driverNewService() {
  // Navigate to a service ticket page or open a modal
  location.href = "?A=service_request.html";
}

// --- Maintenance handlers ---
async function maintLoadQueue() {
  const el = document.getElementById("maintQueue");
  el.textContent = "Loading…";
  try {
    const res = await fetch(`${masterServer}/api/GetMaintenanceQueue.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ Username: username, Token: token })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const rows = await res.json();
    el.innerHTML = renderList(rows, ["TicketID","Tram_ID","Severity","Status","Opened_At"]);
  } catch (e) {
    el.textContent = `Failed to load: ${e.message}`;
  }
}

function maintRecord() {
  location.href = "?A=maintenance_record.html";
}
function maintInventory() {
  location.href = "?A=inventory.html";
}

// --- Admin handlers ---
function adminUsers()  { location.href = "?A=admin_users.html"; }
function adminImport() { location.href = "?A=admin_import.html"; }
function adminExport() { location.href = "?A=admin_export.html"; }
function adminLogs()   { location.href = "?A=admin_logs.html"; }

// --- Utility: render list as compact table-ish block ---
function renderList(rows, cols) {
  if (!Array.isArray(rows) || rows.length === 0) return "<em>No data.</em>";
  const safe = (v)=>escapeHtml(v==null?"":String(v));
  const head = `<div class="mini-row mini-head">${cols.map(c=>`<span>${safe(c)}</span>`).join("")}</div>`;
  const body = rows.slice(0,50).map(r =>
    `<div class="mini-row">${cols.map(c=>`<span>${safe(r[c])}</span>`).join("")}</div>`
  ).join("");
  return `<div class="mini-table">${head}${body}</div>`;
}

// Escape HTML
function escapeHtml(s){ return s.replace(/[&<>\"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;" }[m])); }

// Logout
logoutBtn.addEventListener("click", () => {
  sessionStorage.removeItem("username");
  sessionStorage.removeItem("token");
  sessionStorage.removeItem("role");
  location.href = "?A=login.html";
});
