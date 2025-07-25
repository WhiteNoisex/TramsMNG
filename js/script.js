// js/main.js
document.getElementById("loginBtn").addEventListener("click", () => {
  document.getElementById("loginModal").style.display = "flex";
});
document.getElementById("closeLogin").addEventListener("click", () => {
  document.getElementById("loginModal").style.display = "none";
});
window.onclick = function(e) {
  if (e.target.classList.contains("modal")) {
    e.target.style.display = "none";
  }
}

fetch("data/trams.json")
  .then(res => res.json())
  .then(trams => {
    const grid = document.getElementById("tramGrid");
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");
    const idFilter = document.getElementById("idFilter");
    const dateFilter = document.getElementById("dateFilter");
    const typeFilter = document.getElementById("typeFilter");
    const periodFilter = document.getElementById("periodFilter");

    function render() {
      const keyword = searchInput.value.toLowerCase();
      const status = statusFilter.value.toLowerCase();
      const idVal = idFilter.value.toLowerCase();
      const dateVal = dateFilter.value;
      const typeVal = typeFilter.value.toLowerCase();
      const periodVal = periodFilter.value.toLowerCase();

      grid.innerHTML = "";
      trams.filter(t => {
        return (
          t.title.toLowerCase().includes(keyword) &&
          (status === "" || t.status.toLowerCase() === status) &&
          (idVal === "" || t.id.toString().includes(idVal)) &&
          (dateVal === "" || t.date === dateVal) &&
          (typeVal === "" || t.type.toLowerCase().includes(typeVal)) &&
          (periodVal === "" || t.period.toLowerCase().includes(periodVal))
        );
      }).forEach(tram => {
        const card = document.createElement("div");
        card.className = "card";
        card.innerHTML = `
          <h3>${tram.title}</h3>
          <p><strong>ID:</strong> ${tram.id}</p>
          <p><strong>Status:</strong> ${tram.status}</p>
          <p><strong>Type:</strong> ${tram.type}</p>
          <p><strong>Period:</strong> ${tram.period}</p>
          <p><strong>Date:</strong> ${tram.date}</p>
          <p>${tram.description}</p>
        `;
        grid.appendChild(card);
      });
    }

    render();
    [searchInput, statusFilter, idFilter, dateFilter, typeFilter, periodFilter].forEach(input => {
      input.addEventListener("input", render);
      input.addEventListener("change", render);
    });
  });
