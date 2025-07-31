var username = sessionStorage.getItem("username");
var token = sessionStorage.getItem("token");
var masterServer = "http://localhost/tramsMNG"
var guest = true;

if(!username)
{
  username = "Guest";
  guest = true;
}
else
{
  guest = false;
}

var trams = [
  {id: "001", name: "Z3-215", type: "Z-Class", status: "Active", period: "1992-2022", more: "View", isHistoric: false, age: 30, city: "Melbourne"},
  {id: "015", name: "W8-1002", type: "W-Class", status: "Out of Commision", period: "1950-2005", more: "View", isHistoric: true, age: 55, city: "Melbourne"},
  {id: "237", name: "E2-6012", type: "E-Class", status: "Active", period: "2016-Now", more: "View", isHistoric: false, age: 7, city: "Melbourne"},
  {id: "018", name: "A2-273", type: "A-Class", status: "Destroyed", period: "1988-2020", more: "View", isHistoric: false, age: 32, city: "Sydney"},
  {id: "299", name: "B2-2101", type: "B-Class", status: "Active", period: "1990-2025", more: "View", isHistoric: false, age: 32, city: "Melbourne"},
];

function renderTable() {
  const searchVal = document.getElementById('searchInput').value.trim().toLowerCase();
  const statusChecked = Array.from(document.querySelectorAll('input[name="status"]:checked')).map(cb => cb.value);
  const isHistoric = document.querySelector('input[name="historic"]').checked;
  const age = Number(document.getElementById('ageRange').value);
  const cityVal = document.getElementById('cityInput').value.trim().toLowerCase();

  document.getElementById('ageValue').textContent = `${age}+`;

  const tbody = document.getElementById('tramTableBody');
  tbody.innerHTML = "";
  trams.filter(tram => {
    // Status
    if (statusChecked.length && !statusChecked.includes(tram.status)) return false;
    // Historic
    if (isHistoric && !tram.isHistoric) return false;
    // Age
    if (tram.age < age) return false;
    // City
    if (cityVal && !tram.city.toLowerCase().includes(cityVal)) return false;
    // Search box (by id, name, or type)
    if (
      searchVal &&
      !tram.id.toLowerCase().includes(searchVal) &&
      !tram.name.toLowerCase().includes(searchVal) &&
      !tram.type.toLowerCase().includes(searchVal)
    ) return false;
    return true;
  }).forEach(tram => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${tram.id}</td>
      <td>${tram.name}</td>
      <td>${tram.type}</td>
      <td>${tram.status}</td>
      <td>${tram.period}</td>
      <td><a href="#">${tram.more}</a></td>
    `;
    tbody.appendChild(row);
  });
}

function transformTramData(rawData, isGuest = true) {
  return rawData.map(row => {
    const startYear = parseInt(row.Operating_Year_S);
    const endYear = row.Operating_Year_E?.toLowerCase() === "now" || !row.Operating_Year_E
      ? new Date().getFullYear()
      : parseInt(row.Operating_Year_E);

    const baseData = {
      id: String(row.ID).padStart(3, "0"),
      name: row.Tram_Name,
      type: row.Tram_Type,
      status: row.Service_State,
      period: `${row.Operating_Year_S}-${row.Operating_Year_E || "Now"}`,
      operating_start: row.Operating_Year_S,
      operating_end: row.Operating_Year_E,
      more: "View",
      isHistoric: row.Historic === "1" || row.Historic === 1 || row.Historic === true,
      age: endYear - startYear,
      city: row.Operating_City,
      tram_type: row.Tram_Type
    };

    if (!isGuest) {
      return {
        ...baseData,
        damage_history: row.Damage_History,
        disablity_comp: row.Disablity_Compliance,
        eol_goal: row.EOL_GOAL,
        engine_details: row.Engine_Details,
        historic: row.Historic,
        maintenance_history: row.Maintenance_History,
        photo_location: row.Photo_Location,
        power_type: row.Power_Type,
        seat_cap: row.Seat_Capacity,
        drives_history: row.Drives_History
      };
    }

    return baseData;
  });
}



async function GetUpdatedTrams()
{
    //

    try {

        //console.log(hashData);

        const response = await fetch(masterServer + '/api/GetTramsList_API_.php', {
            method: 'post',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                Username: username,
                Token: token
            })
        });

        if (response.ok) {
            const responseData = await response.json();

            //console.log(responseData);
            return transformTramData(responseData, guest);
            // Add any further actions for successful authentication
        } 
        else if(response.status === 401)
        {
            console.log('Invalid Access')
            return [];
            //window.location.replace("index.html")
        }
        else {
            const responseData = await response.json();

            console.error(responseData.error); // Error message
            return [];
            // Add any further error handling
        }
    } catch (error) {
        console.error('Error:', error);
        return [];
        // Add any further error handling
    }
}


renderTable();

document.getElementById('searchInput').addEventListener('input', renderTable);
document.getElementById('filterForm').addEventListener('input', renderTable), function() {
  document.getElementById('ageValue').textContent = this.value + '+';
  renderTable();
};

// Update your renderTable filter:
async function renderTable() {
  trams = await GetUpdatedTrams();
  const searchVal = document.getElementById('searchInput').value.trim().toLowerCase();
  const statusChecked = Array.from(document.querySelectorAll('input[name="status"]:checked')).map(cb => cb.value);
  const isHistoric = document.querySelector('input[name="historic"]').checked;
  const cityVal = document.getElementById('cityInput').value.trim().toLowerCase();

  const minYear = parseInt(document.getElementById('fromSlider').value, 10);
  const maxYear = parseInt(document.getElementById('toSlider').value, 10);


  const tbody = document.getElementById('tramTableBody');
  tbody.innerHTML = "";
  trams.filter(tram => {
    // Status
    if (statusChecked.length && !statusChecked.includes(tram.status)) return false;
    // Historic
    if (isHistoric && !tram.isHistoric) return false;
    // Operating year filter (assuming tram.period is like '1992-2022' or '2016-Now')
    let period = tram.period.split('-');
    let opStart = parseInt(period[0]);
    let opEnd = period[1] === "Now" ? 2025 : parseInt(period[1]);
    if (opStart > maxYear || opEnd < minYear) return false;
    // City
    if (cityVal && !tram.city.toLowerCase().includes(cityVal)) return false;
    // Search box
    if (
      searchVal &&
      !tram.id.toLowerCase().includes(searchVal) &&
      !tram.name.toLowerCase().includes(searchVal) &&
      !tram.type.toLowerCase().includes(searchVal)
    ) return false;
    return true;
  }).forEach(tram => {
    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${tram.id}</td>
      <td>${tram.name}</td>
      <td>${tram.type}</td>
      <td>${tram.status}</td>
      <td>${tram.period}</td>
      <td><a href='javascript:void(0);' onclick='openModal(${tram.id})'>View</a></td>

    `;
    tbody.appendChild(row);
  });
}



function controlFromInput(fromSlider, fromInput, toInput, controlSlider) {
    const [from, to] = getParsed(fromInput, toInput);
    fillSlider(fromInput, toInput, '#C6C6C6', '#25daa5', controlSlider);
    if (from > to) {
        fromSlider.value = to;
        fromInput.value = to;
    } else {
        fromSlider.value = from;
    }
}
    
function controlToInput(toSlider, fromInput, toInput, controlSlider) {
    const [from, to] = getParsed(fromInput, toInput);
    fillSlider(fromInput, toInput, '#C6C6C6', '#25daa5', controlSlider);
    setToggleAccessible(toInput);
    if (from <= to) {
        toSlider.value = to;
        toInput.value = to;
    } else {
        toInput.value = from;
    }
}

function controlFromSlider(fromSlider, toSlider, fromInput) {
  const [from, to] = getParsed(fromSlider, toSlider);
  fillSlider(fromSlider, toSlider, '#C6C6C6', '#25daa5', toSlider);
  if (from > to) {
    fromSlider.value = to;
    fromInput.value = to;
  } else {
    fromInput.value = from;
  }
}

function controlToSlider(fromSlider, toSlider, toInput) {
  const [from, to] = getParsed(fromSlider, toSlider);
  fillSlider(fromSlider, toSlider, '#C6C6C6', '#25daa5', toSlider);
  setToggleAccessible(toSlider);
  if (from <= to) {
    toSlider.value = to;
    toInput.value = to;
  } else {
    toInput.value = from;
    toSlider.value = from;
  }
}

function getParsed(currentFrom, currentTo) {
  const from = parseInt(currentFrom.value, 10);
  const to = parseInt(currentTo.value, 10);
  return [from, to];
}

function fillSlider(from, to, sliderColor, rangeColor, controlSlider) {
    const rangeDistance = to.max-to.min;
    const fromPosition = from.value - to.min;
    const toPosition = to.value - to.min;
    controlSlider.style.background = `linear-gradient(
      to right,
      ${sliderColor} 0%,
      ${sliderColor} ${(fromPosition)/(rangeDistance)*100}%,
      ${rangeColor} ${((fromPosition)/(rangeDistance))*100}%,
      ${rangeColor} ${(toPosition)/(rangeDistance)*100}%, 
      ${sliderColor} ${(toPosition)/(rangeDistance)*100}%, 
      ${sliderColor} 100%)`;
}

function setToggleAccessible(currentTarget) {
  const toSlider = document.querySelector('#toSlider');
  if (Number(currentTarget.value) <= 0 ) {
    toSlider.style.zIndex = 2;
  } else {
    toSlider.style.zIndex = 0;
  }
}

function openModal(tramID) {
  if(!username || username == "Guest")
  {
    alert("Please Login To View This Data");
    return;
  }

 let found = false;
  var tram = null;
  for (let i = 0; i < trams.length; i++) {
    if (trams[i].id == tramID) {
      found = true;
      tram = trams[i];
      console.log(tram);
      break; // This works
    }
  }


  let currentTram = null;

function openHistoryPopup(tram) {
  currentTram = tram;
  loadTab("Servicing_History");
  document.getElementById("historyPopup").style.display = "block";
}

function loadTab(tabName) {
  const content = currentTram[tabName];
  const container = document.getElementById("historyContent");

  if (!content || content.length === 0) {
    container.innerHTML = `<p>No ${tabName.replace("_", " ")} available.</p>`;
    return;
  }

  let html = `<h3>${tabName.replace("_", " ")}</h3><ul>`;
  content.forEach((entry, i) => {
    html += `<li><strong>Entry ${i + 1}:</strong><pre>${JSON.stringify(entry, null, 2)}</pre></li>`;
  });
  html += `</ul>`;
  container.innerHTML = html;
}


  document.getElementById('modal_id').innerText = tram.id;
  document.getElementById('modal_name').innerText = tram.name;
  document.getElementById('modal_start_year').innerText = tram.operating_start;
  document.getElementById('modal_end_year').innerText = tram.operating_end;
  document.getElementById('modal_status').innerText = tram.status;
  document.getElementById('modal_historic').innerText = tram.isHistoric == 1 ? 'Yes' : 'No';
  document.getElementById('modal_eol_goal').innerText = tram.eol_goal;
  document.getElementById('modal_type').innerText = tram.tram_type;
  document.getElementById('modal_photo').innerText = tram.photo_location;
  document.getElementById('modal_city').innerText = tram.city;
  document.getElementById('modal_power').innerText = tram.power_type;
  document.getElementById('modal_seat').innerText = tram.seat_cap;
  document.getElementById('modal_engine').innerText = tram.engine_details;
  document.getElementById('modal_route').innerText = tram.Tram_Route;
  document.getElementById('modal_disability').innerText = tram.disablity_comp;
  document.getElementById('modal_driver').innerText = tram.Normal_Driver;
  document.getElementById('modal_service').innerText = tram.service_history;
  document.getElementById('modal_damage').innerText = tram.damage_history;
  document.getElementById('modal_maintenance').innerText = tram.maintenance_history;

  document.getElementById('tramModal').style.display = 'block';
}

function closeModal() {
  document.getElementById('tramModal').style.display = 'none';
}





const fromSlider = document.querySelector('#fromSlider');
const toSlider = document.querySelector('#toSlider');
const fromInput = document.querySelector('#fromInput');
const toInput = document.querySelector('#toInput');
fillSlider(fromSlider, toSlider, '#C6C6C6', '#25daa5', toSlider);
setToggleAccessible(toSlider);

fromSlider.oninput = () => controlFromSlider(fromSlider, toSlider, fromInput);
toSlider.oninput = () => controlToSlider(fromSlider, toSlider, toInput);
fromInput.oninput = () => controlFromInput(fromSlider, fromInput, toInput, toSlider);
toInput.oninput = () => controlToInput(toSlider, fromInput, toInput, toSlider);