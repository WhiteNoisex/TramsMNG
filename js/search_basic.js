// --- Auth & API base ---
let username = sessionStorage.getItem("username") || "GuestGuest";
let token = sessionStorage.getItem("token") || "";
let guest = (username === "GuestGuest");
const masterServer = "http://localhost/tramsMNG";

// --- State ---
let trams = [];
let currentTram = null;

// --- DOM ---
const tbody = document.getElementById("tramTableBody");
const searchInput = document.getElementById("searchInput");
const filterForm = document.getElementById("filterForm");
const notice = document.getElementById("notice");
const emptyState = document.getElementById("emptyState");

// Range slider elements
const fromSlider = document.getElementById("fromSlider");
const toSlider   = document.getElementById("toSlider");
const fromInput  = document.getElementById("fromInput");
const toInput    = document.getElementById("toInput");

// Modal elements
const modal = document.getElementById("tramModal");
const modalClose = document.getElementById("modalClose");
const historyContent = document.getElementById("historyContent");

// --- Utils ---
const debounce = (fn, ms = 180) => {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(null, args), ms);
  };
};

// Normalize status values (handles “OutofCommision”, etc.)
function normalizeStatus(s) {
  if (!s) return "";
  const x = String(s).toLowerCase().replace(/\s+/g, "");
  if (x.includes("destroy")) return "Destroyed";
  if (x.includes("outofcomm")) return "Out of Commission";
  return "Active";
}

function parseHistoryField(val) {
  if (!val) return [];
  if (Array.isArray(val)) return val;
  try {
    const parsed = JSON.parse(val);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function transformTramData(rawData, isGuest = true) {
  return rawData.map(row => {
    const startYear = parseInt(row.Operating_Year_S);
    const endYear = (row.Operating_Year_E && String(row.Operating_Year_E).toLowerCase() !== "now")
      ? parseInt(row.Operating_Year_E)
      : new Date().getFullYear();

    const base = {
      id: String(row.ID).padStart(3, "0"),
      name: row.Tram_Name,
      type: row.Tram_Type,
      status: normalizeStatus(row.Service_State),
      period: `${row.Operating_Year_S}-${row.Operating_Year_E || "Now"}`,
      operating_start: row.Operating_Year_S,
      operating_end: row.Operating_Year_E || "Now",
      isHistoric: row.Historic === "1" || row.Historic === 1 || row.Historic === true,
      city: row.Operating_City || "",
      more: "View",
      raw: row
    };

    if (isGuest) return base;

    return {
      ...base,
      eol_goal: row.EOL_GOAL ?? "",
      photo_location: row.Photo_Location ?? "",
      power_type: row.Power_Type ?? "",
      seat_cap: row.Seat_Capacity ?? "",
      engine_details: row.Engine_Details ?? "",
      Tram_Route: row.Tram_Route ?? "",
      disablity_comp: row.Disablity_Compliance ?? "",
      Normal_Driver: row.Normal_Driver ?? "",
      Servicing_History: parseHistoryField(row.Servicing_History),
      Damage_History: parseHistoryField(row.Damage_History),
      Maintenance_History: parseHistoryField(row.Maintenance_History)
    };
  });
}


// Fetch from backend (graceful errors)
async function getUpdatedTrams() {
  try {
    const res = await fetch(`${masterServer}/api/GetTramsList_API_.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ Username: username, Token: token })
    });

    if (res.status === 401) {
      showNotice("Invalid access. Some data may be hidden until you log in.", true);
      return [];
    }
    if (!res.ok) {
      const j = await safeJson(res);
      showNotice(j?.error || `Server error (${res.status})`, true);
      return [];
    }
    const data = await res.json();
    hideNotice();
    return transformTramData(data, guest);
  } catch (e) {
    showNotice(`Network error: ${e.message}`, true);
    return [];
  }
}
async function safeJson(res){ try { return await res.json(); } catch { return null; } }

function showNotice(msg, isError=false){
  if (!notice) return;
  notice.textContent = msg;
  notice.hidden = false;
  notice.style.color = isError ? "#ffd0d0" : "#eaffff";
}
function hideNotice(){ if (notice) notice.hidden = true; }

// --- Render ---
function renderTable() {
  const searchVal = searchInput.value.trim().toLowerCase();

  const statusChecked = Array.from(filterForm.querySelectorAll('input[name="status"]:checked'))
    .map(cb => cb.value);

  const isHistoricOnly = filterForm.querySelector('input[name="historic"]').checked;
  const cityVal = document.getElementById("cityInput").value.trim().toLowerCase();

  const minYear = parseInt(fromSlider.value, 10);
  const maxYear = parseInt(toSlider.value, 10);

  tbody.innerHTML = "";

  const rows = trams.filter(t => {
    if (statusChecked.length && !statusChecked.includes(t.status)) return false;
    if (isHistoricOnly && !t.isHistoric) return false;

    const [s, e] = String(t.period).split("-");
    const opStart = parseInt(s, 10);
    const opEnd = (e === "Now") ? new Date().getFullYear() : parseInt(e, 10);
    if (Number.isFinite(opStart) && Number.isFinite(opEnd)) {
      if (opStart > maxYear || opEnd < minYear) return false;
    }

    if (cityVal && !t.city.toLowerCase().includes(cityVal)) return false;

    if (searchVal) {
      const blob = `${t.id} ${t.name} ${t.type}`.toLowerCase();
      if (!blob.includes(searchVal)) return false;
    }
    return true;
  });

  if (!rows.length) {
    emptyState.hidden = false;
    return;
  }
  emptyState.hidden = true;

  const frag = document.createDocumentFragment();
  rows.forEach(t => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${t.id}</td>
      <td>${t.name}</td>
      <td>${t.type}</td>
      <td>${t.status}</td>
      <td>${t.period}</td>
      <td><a href="javascript:void(0)" data-tramid="${t.id}" class="view-link">View</a></td>
    `;
    frag.appendChild(tr);
  });
  tbody.appendChild(frag);
}

// --- Modal / History ---
function openModal(tramId){
  if (guest) { alert("Please log in to view full details."); return; }

  const t = trams.find(x => x.id === String(tramId).padStart(3,"0"));
  if (!t) return;

  currentTram = t;

  // Fill fields
  setTxt("modal_id", t.id);
  setTxt("modal_name", t.name);
  setTxt("modal_start_year", t.operating_start ?? "");
  setTxt("modal_end_year", t.operating_end ?? "");
  setTxt("modal_status", t.status ?? "");
  setTxt("modal_historic", t.isHistoric ? "Yes" : "No");
  setTxt("modal_eol_goal", t.eol_goal ?? "");
  setTxt("modal_type", t.type ?? "");
  setTxt("modal_photo", t.photo_location ?? "");
  setTxt("modal_city", t.city ?? "");
  setTxt("modal_power", t.power_type ?? "");
  setTxt("modal_seat", t.seat_cap ?? "");
  setTxt("modal_engine", t.engine_details ?? "");
  setTxt("modal_route", t.Tram_Route ?? "");
  setTxt("modal_disability", t.disablity_comp ?? "");
  setTxt("modal_driver", t.Normal_Driver ?? "");

  // Default tab
  setActiveTab("Servicing_History");
  modal.setAttribute("aria-hidden","false");
}
function closeModal(){
  modal.setAttribute("aria-hidden","true");
  historyContent.innerHTML = "";
  currentTram = null;
}
function setTxt(id, val){ const el = document.getElementById(id); if (el) el.textContent = val; }

function renderHistoryList(tab, entries) {
  if (tab === "Normal_Driver") {
    return `<h3>Driver</h3>
            <p>${escapeHtml(currentTram?.Normal_Driver || "No driver assigned.")}</p>`;
  }

  if (!Array.isArray(entries) || entries.length === 0) {
    return `<p>No ${tab.replaceAll("_"," ").toLowerCase()} available.</p>`;
  }

  return `
    <h3>${tab.replaceAll("_", " ")}</h3>
    <div class="history-list">
      ${entries.map((entry, i) => {
        if (typeof entry === 'string') {
          return `<div class="history-entry">
                    <div class="history-title">Entry ${i+1}</div>
                    <div class="history-body">${escapeHtml(entry)}</div>
                  </div>`;
        }

        if (typeof entry === 'object' && entry !== null) {
          return `
            <div class="history-entry">
              <div class="history-title">Entry ${i+1}</div>
              <div class="history-body">
                ${Object.entries(entry).map(([key, value]) => {
                  return `<div class="history-line">
                            <span class="history-key">${escapeHtml(key)}:</span> 
                            <span class="history-value">${escapeHtml(String(value))}</span>
                          </div>`;
                }).join("")}
              </div>
            </div>
          `;
        }

        return `<div class="history-entry">Entry ${i+1}: ${escapeHtml(String(entry))}</div>`;
      }).join("")}
    </div>
  `;
}

function setActiveTab(tab) {
  document.querySelectorAll(".tab-btn").forEach(b => 
    b.classList.toggle("is-active", b.dataset.tab === tab)
  );

  if (!currentTram) return;

  if (tab === "Normal_Driver") {
    historyContent.innerHTML = renderHistoryList(tab, null);
  } else {
    historyContent.innerHTML = renderHistoryList(tab, currentTram[tab]);
  }
}



function escapeHtml(s){ return s.replace(/[&<>]/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;" }[m])); }

// --- Range sync ---
function getParsed(a,b){ return [parseInt(a.value,10), parseInt(b.value,10)]; }
function fillSlider(from, to, sliderColor, rangeColor, controlSlider){
  const rangeDistance = to.max - to.min;
  const fromPosition = from.value - to.min;
  const toPosition   = to.value - to.min;
  controlSlider.style.background = `linear-gradient(
    to right,
    ${sliderColor} 0%,
    ${sliderColor} ${(fromPosition)/(rangeDistance)*100}%,
    ${rangeColor} ${((fromPosition)/(rangeDistance))*100}%,
    ${rangeColor} ${(toPosition)/(rangeDistance)*100}%,
    ${sliderColor} ${(toPosition)/(rangeDistance)*100}%,
    ${sliderColor} 100%)`;
}
function setToggleAccessible(currentTarget){
  const toS = document.querySelector('#toSlider');
  toS.style.zIndex = (Number(currentTarget.value) <= 0) ? 2 : 0;
}
function controlFromSlider(fromS, toS, fromI){
  const [from, to] = getParsed(fromS, toS);
  fillSlider(fromS, toS, '#475569', '#22d3ee', toS);
  if (from > to){ fromS.value = to; fromI.value = to; }
  else { fromI.value = from; }
  renderTable();
}
function controlToSlider(fromS, toS, toI){
  const [from, to] = getParsed(fromS, toS);
  fillSlider(fromS, toS, '#475569', '#22d3ee', toS);
  setToggleAccessible(toS);
  if (from <= to){ toS.value = to; toI.value = to; }
  else { toI.value = from; toS.value = from; }
  renderTable();
}
function controlFromInput(fromS, fromI, toI, controlSlider){
  const [from, to] = getParsed(fromI, toI);
  fillSlider(fromI, toI, '#475569', '#22d3ee', controlSlider);
  if (from > to){ fromS.value = to; fromI.value = to; }
  else { fromS.value = from; }
  renderTable();
}
function controlToInput(toS, fromI, toI, controlSlider){
  const [from, to] = getParsed(fromI, toI);
  fillSlider(fromI, toI, '#475569', '#22d3ee', controlSlider);
  setToggleAccessible(toI);
  if (from <= to){ toS.value = to; toI.value = to; }
  else { toI.value = from; }
  renderTable();
}

// --- Events ---
document.addEventListener("click", (e)=>{
  const a = e.target.closest(".view-link");
  if (a){
    const id = a.getAttribute("data-tramid");
    openModal(id);
  }
  const tabBtn = e.target.closest(".tab-btn");
  if (tabBtn){
    setActiveTab(tabBtn.dataset.tab);
  }
});
modalClose.addEventListener("click", closeModal);
modal.addEventListener("click", (e)=>{ if (e.target === modal) closeModal(); });

searchInput.addEventListener("input", debounce(renderTable, 120));
filterForm.addEventListener("input", renderTable);

fromSlider.addEventListener("input", ()=>controlFromSlider(fromSlider, toSlider, fromInput));
toSlider.addEventListener("input", ()=>controlToSlider(fromSlider, toSlider, toInput));
fromInput.addEventListener("input", ()=>controlFromInput(fromSlider, fromInput, toInput, toSlider));
toInput.addEventListener("input",   ()=>controlToInput(toSlider, fromInput, toInput, toSlider));

// --- Init ---
(async function init(){
  // style initial range background
  fillSlider(fromSlider, toSlider, '#475569', '#22d3ee', toSlider);
  setToggleAccessible(toSlider);

  trams = await getUpdatedTrams();

  // Fallback sample if API empty (dev mode)
  if (!trams.length){
    trams = transformTramData([
      {ID:1, Tram_Name:"Z3-215", Tram_Type:"Z-Class", Service_State:"Active", Operating_Year_S:1992, Operating_Year_E:2022, Historic:0, Operating_City:"Melbourne"},
      {ID:15, Tram_Name:"W8-1002", Tram_Type:"W-Class", Service_State:"OutofCommision", Operating_Year_S:1950, Operating_Year_E:2005, Historic:1, Operating_City:"Melbourne"},
      {ID:237, Tram_Name:"E2-6012", Tram_Type:"E-Class", Service_State:"Active", Operating_Year_S:2016, Operating_Year_E:"Now", Historic:0, Operating_City:"Melbourne"}
    ], guest);
    showNotice("Showing sample data (API returned no rows).");
  }

  renderTable();
})();
