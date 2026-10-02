<nav class="navbar topnav-shell sticky-top" aria-label="{Overview}">
  <div class="container-fluid px-3 py-2">
    <div class="row g-2 align-items-center w-100">
      <div class="col-12 col-lg-auto">
        <div class="d-flex flex-wrap align-items-center gap-2 xnova-topnav-icons">
          {message_notif}
          {notes_popup}
          {buddy_popup}
          {players_online}
          <button type="button" class="btn btn-outline-secondary btn-sm xnova-theme-toggle" data-theme-toggle
                  aria-pressed="true" title="Activer le thème clair"
                  data-theme-title-light="Activer le thème clair" data-theme-title-dark="Activer le thème sombre">
            <i class="bi bi-sun-fill" data-theme-icon aria-hidden="true"></i>
          </button>
        </div>
        <div class="d-flex align-items-center gap-2 mt-2">
          <a href="/game/overview" class="d-inline-flex">
            <img src="{dpath}planeten/small/s_{image}.jpg" alt="{Planet}" class="xnova-planet-thumb rounded-circle border border-secondary">
          </a>
          <select class="form-select form-select-sm xnova-planet-select" size="1" aria-label="{Planet}"
                  onChange="eval('location=\''+this.options[this.selectedIndex].value+'\'');">
            {planetlist}
          </select>
        </div>
      </div>
      <div class="col-12 col-lg">
        <div class="row row-cols-2 row-cols-sm-2 row-cols-lg-4 g-2">
          <div class="resource-pill">
            <img src="{dpath}images/metall.gif" alt="{Metal}">
            <span class="label">{Metal}</span>
            <span class="value" id="xnova-res-metal" data-resource="metal">{metal}</span>
          </div>
          <div class="resource-pill">
            <img src="{dpath}images/kristall.gif" alt="{Crystal}">
            <span class="label">{Crystal}</span>
            <span class="value" id="xnova-res-crystal" data-resource="crystal">{crystal}</span>
          </div>
          <div class="resource-pill">
            <img src="{dpath}images/deuterium.gif" alt="{Deuterium}">
            <span class="label">{Deuterium}</span>
            <span class="value" id="xnova-res-deuterium" data-resource="deuterium">{deuterium}</span>
          </div>
          <div class="resource-pill">
            <img src="{dpath}images/energie.gif" alt="{Energy}">
            <span class="label">{Energy}</span>
            <span class="value" id="xnova-res-energy" data-resource="energy">{energy}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>