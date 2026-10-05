// dashboard.js
$(document).ready(function () {


  $("#minule-zapisy").hide();
  // class: modal
  // Přidání modálního okna pro přidávání/úpravy publikací
  $("body").append(`
      <div id="pub-modal" class="modal" style="display:none; position: fixed; top: 20%; left: 50%; transform: translateX(-50%); background: white; border: 1px solid #ccc; padding: 3em; z-index: 9999;">
        <div class="modal-content formEdit">
          <span class="close-modal" style="float:right; cursor:pointer;">&times;</span>
          <h3 id="modal-title">Add publikaci</h3>
          <input type="hidden" id="pub-id">
          <input type="text" id="pub-title" title ="Pokud publikace zatím nemá název, dejte alespoň téma, pro orientaci" placeholder="Name publikace"><br>
          <select id="pub-type" style="margin-top: 1em">

            <option value="">(vyber typ)</option>
            <option value="article">recenzovaný článek</option>
            <option value="article_wos">článek ve WOS</option>
            <option value="article_scopus">článek ve SCOPUS</option>
            <option value="chapter">kapitola v monografii</option>
          </select><br>
          <button id="save-publication" class="button-link centered" style="margin-top: 1em">Save</button>
        </div>
      </div>
    `);

  $(document).on("click", ".close-modal", function () {
    $("#pub-modal").hide();
  });

  $(document).on("click", "#save-publication", function () {
    const id = $("#pub-id").val();
    const title = $("#pub-title").val();
    const type = $("#pub-type").val();

    const url = id
      ? "../diaries/update_publication.php"
      : "../diaries/add_publication.php";
    const data = id ? { id, title, type } : { title, type };

    $.post(url, data, function (response) {

      $("#pub-modal").hide();
      $.getJSON("../diaries/get_publications.php", renderPublications);
    });
  });

  $(document).on("click", ".delete-publication", function () {
    if (!confirm("Opravdu chcete publikaci delete?")) return;

    const pubId = $(this).closest(".publication").data("id");

    $.post(
      "../diaries/delete_publication.php",
      { id: pubId },
      function (response) {

        $.getJSON("../diaries/get_publications.php", renderPublications);
      },
    );
  });

  // 2. Načti publikace
  const statuses = [
    "not_started",
    "data_collection",
    "draft",
    "submitted",
    "review",
    "accepted",
    "printed",
  ];

  $.getJSON("../diaries/get_publications.php", function (data) {

    renderPublications(data);
  });

  function renderPublications(data) {
    let html = "";
    data.forEach((pub, iPub) => {
      const title =
        pub.title && pub.title.trim() ? pub.title.trim() : "(untitled)";
      const type = pub.type && pub.type.trim() ? pub.type.trim() : "(bez typu)";
      const currentIndex = statuses.indexOf(pub.status);
      let typeText = "";

      switch (type) {
        case "article":
          typeText = "recenzovaný článek";
          break;
        case "chapter":
          typeText = "kapitola v mezinárodní monografii";
          break;
        case "article_scopus":
          typeText = "článek ve SCOPUS";
          break;
        case "article_wos":
          typeText = "článek ve WOS";
          break;
        default:
          typeText = "neznámý";
      }

      html += `<div class="formRow publication" data-type="${escapeHtml(type)}" data-title="${escapeHtml(title)}" data-id="${escapeHtml(pub.id)}" data-status="${escapeHtml(pub.status)}">
          <span><strong>Name či téma:</strong> ${escapeHtml(title)}</span>
          <span class="edit-publication" title="Edit informace o publikaci" style="cursor:pointer; float:right; color:blue; margin-left: 10px;">✎</span>
          <span class="delete-publication" title="Delete publikaci" style="cursor:pointer; float:right; color:red;">✖</span><br/>
          <em>Druh: ${typeText}</em>
          <table class="progress-table" title="Označte vývoj publikace – vždy skáčete o jeden vývojový skok. Kliknutím na active jej zneactivete (posunete vývoj back o jedno), kliknutím na neactive zactivete (posunete o jeden krok vpřed)"><tr>`;

      statuses.forEach((step, i) => {
        const activeClass =
          i <= currentIndex ? step + "_active" : step + "_inactive";

        html += `<td class="${activeClass}" data-step="${step}">${step.replaceAll("_", " ")}</td>`;
      });

      html += `</tr></table></div>`;
    });

    html += `<div class="formRow"><button class="button-link centered" id="add-publication-btn">➕ Add publikaci</button></div>`;

    $("#pub-list").html(html);
  }

  $(document).on("click", "#add-publication-btn", function () {
    $("#modal-title").text("Add publikaci");
    $("#pub-id").val("");
    $("#pub-title").val("");
    $("#pub-type").val("");
    $("#pub-modal").show();
  });

  $(document).on("click", ".edit-publication", function () {
    const pubDiv = $(this).closest(".publication");
    const id = pubDiv.data("id");
    const title = pubDiv.data("title");
    const type = pubDiv.data("type");

    $("#modal-title").text("Edit publikaci");
    $("#pub-id").val(id);
    $("#pub-title").val(title);
    if (["article", "article_wos", "article_scopus", "chapter"].includes(type)) {
      $("#pub-type").val(type);
    } else {
      $("#pub-type").val("article");
    }
    $("#pub-modal").show();
  });

  // 3. Timeline záznamů seskupená podle roku a měsíce
  $.getJSON("../diaries/get_diary_timeline.php", function (data) {

    const grouped = {};

    data.forEach((entry) => {
      const date = new Date(entry.week_start);
      const year = date.getFullYear();
      const month = date.toLocaleString("cs-CZ", { month: "long" });
      const day = date.getDate();

      if (!grouped[year]) grouped[year] = {};
      if (!grouped[year][month]) grouped[year][month] = [];

      grouped[year][month].push({
        id: entry.id,
        label: `${day}. ${month}`,
        isPrivate:
          entry.visible_to_teacher === "0" || entry.visible_to_teacher === 0,
      });
    });

    let html = "";

    // Seřadíme roky sestupně podle číselné hodnoty
    const sortedYears = Object.keys(grouped)
      .map(Number)
      .sort((a, b) => b - a);

    sortedYears.forEach((year) => {
      html += `<li><strong class="tl-year">${year}</strong><div class="accordion">`;

      // Měsíce ponecháme v pořadí, jak přijdou (volitelně lze seřadit i je)
      for (const month in grouped[year]) {
        html += `<h4>${month}</h4><div><ul>`;
        grouped[year][month].forEach((item) => {
          html += `<li><a href="#" class="entry-link" data-id="${item.id}" data-private="${item.isPrivate}">${item.label}${item.isPrivate ? " 🕵️‍♂️" : ""}</a></li>`;
        });
        html += `</ul></div>`;
      }

      html += `</div></li>`;
    });

    $("#timeline-menu").html(html);

    // Inicializace accordionu
    $(".accordion").accordion({
      collapsible: true,
      heightStyle: "content", // výška podle contentu
      active: false, // vše zavřené na začátku
    });
  });

  // 4. Zobrazení konkrétního záznamu
  $(document).on("click", ".entry-link", function (e) {
    e.preventDefault();
    const entryId = $(this).data("id");


    $.getJSON(
      "../diaries/get_diary_entry.php",
      { id: entryId },
      function (entry) {


        if (!entry || entry.error) {
          $("#entry-detail").html(`<p class="red">Záznam was not found.</p>`);
          return;
        }

        const isPrivate = entry.visible_to_teacher == "0";
        const date = new Date(entry.week_start);
        const formatted = `${date.getDate()}. ${date.getMonth() + 1}. ${date.getFullYear()}`;
        const legendText = `Z týdne ${formatted}${isPrivate ? " (rozpracovaný)" : ""}`;

        let detail = `
        <div><h3>${legendText}</h3>
        ${isPrivate ? '<div class="alertBox">Tento záznam je zatím neveřejný a teacher ho nevidí.</div>' : ""}`;

        // Co jsem dělal/a
        if (entry.what_did_you_do && entry.what_did_you_do.trim() !== "") {
          detail += `<p><strong>Co jsem dělal/a:</strong><br><span class="code-font">${escapeHtml(entry.what_did_you_do)}</span></p>`;
        }

        // Co mi to dalo
        if (
          entry.what_did_it_give_me &&
          entry.what_did_it_give_me.trim() !== ""
        ) {
          detail += `<p><strong>Co mi to dalo:</strong><br><span class="code-font">${escapeHtml(entry.what_did_it_give_me)}</span></p>`;
        }

        // Otázky, nejistoty
        if (
          entry.doubts_or_questions &&
          entry.doubts_or_questions.trim() !== ""
        ) {
          detail += `<p><strong>Otázky, nejistoty:</strong><br><span class="code-font">${escapeHtml(entry.doubts_or_questions)}</span></p>`;
        }

        // Reflexe úkolů
        if (entry.task_reflection && entry.task_reflection.trim() !== "") {
          detail += `<p><strong>Reflexe úkolů:</strong><br><span class="code-font">${escapeHtml(entry.task_reflection)}</span></p>`;
        }

        // Reflexe četby/teorie
        if (
          entry.publications_reflection &&
          entry.publications_reflection.trim() !== ""
        ) {
          detail += `<p><strong>Reflexe četby/teorie:</strong><br><span class="code-font">${escapeHtml(entry.publications_reflection)}</span></p>`;
        }

        // Feedback teachere – vždy zobrazíme, ale s "–" pokud prázdné
        const feedback = entry.teacher_feedback?.trim() || "–";
        detail += `<p><strong>Backná vazba školitele:</strong><br><span class="red code-font">${escapeHtml(feedback)}</span></p>`;
        if (entry.private_notes && entry.private_notes.trim() !== "") {
          detail += `<p><strong>Notes k práci v týdnu:</strong><br><span class="code-font">${escapeHtml(entry.private_notes)}</span><br><small>${entry.notes_to_teacher == "1" ? "Odesláno vyučujícímu" : "Soukromé – vyučující je nevidí"}</small></p>`;
        }

        detail += `</div><hr>`;

        detail += `<div class="formRow"><button class="button-link centered" id="close-entry">🗃️ Zavřít záznam</button></div>`;

        $("#entry-detail").html(detail);
        $("#minule-zapisy").show();

        var elOffset = $("#minule-zapisy").offset();
        const dest = elOffset.top;
        $("html, body").animate({ scrollTop: dest });
      },
    );
  });

  // 5. Předvyplň formulář, pokud existuje soukromý zápis
  $.getJSON("../diaries/get_editable_entry.php", function (entry) {

    if (entry && entry.id) {
      $('input[name="entry_id"]').val(entry.id);
      $('textarea[name="what_did_you_do"]').val(entry.what_did_you_do);
      $('textarea[name="what_did_it_give_me"]').val(entry.what_did_it_give_me);
      $('textarea[name="doubts_or_questions"]').val(entry.doubts_or_questions);
      $('textarea[name="task_reflection"]').val(entry.task_reflection);
      $('textarea[name="publications_reflection"]').val(
        entry.publications_reflection,
      );
      $('textarea[name="private_notes"]').val(entry.private_notes || "");
      $('input[name="notes_to_teacher"]').prop("checked", entry.notes_to_teacher == "1");
      $('input[name="visible_to_teacher"]').prop(
        "checked",
        entry.visible_to_teacher == "1",
      );

      const isPrivate = entry.visible_to_teacher == "0";
      const label = `${escapeHtml(entry.week_start)}${isPrivate ? " (rozpracovaný)" : ""}`;
      const infoBox = isPrivate
        ? `<div class="alertBox">Tento záznam je zatím neveřejný a teacher ho nevidí.</div>`
        : "";


      // Zobraz info o rozpracovaném zápisu u formuláře
      $("#novy_zapis_title").text(
        isPrivate ? "New zápis – rozpracovaný" : "New zápis",
      );
      $("#infoBox").html(infoBox);

      // Nepřepisuj sekci #entry-detail, to zůstává pro zobrazení z timeline
    }
  });

  $(document).on("click", "#close-entry", function () {
    $("#minule-zapisy").hide();
    const topOffset = $("#timeline-menu").offset();
    const destination = topOffset.top;
    $("html, body").animate({ scrollTop: destination });
  });
  // 6. Po kliknutí na fázi publikace
  $(document).on("click", ".progress-table td", function () {
    const $pub = $(this).closest(".publication");
    const pubId = $pub.data("id");
    const currentStatus = $pub.data("status");
    const clickedStatus = $(this).data("step");


    const currentIndex = statuses.indexOf(currentStatus);
    const clickedIndex = statuses.indexOf(clickedStatus);

    if (clickedIndex === currentIndex) {
      const newStatus = statuses[currentIndex - 1] || "not_started";
      updatePublicationStatus(pubId, newStatus);
    } else if (clickedIndex === currentIndex + 1) {
      const newStatus = statuses[clickedIndex];
      updatePublicationStatus(pubId, newStatus);
    }
  });

  function updatePublicationStatus(id, status) {

    $.post(
      "../diaries/update_publication_status.php",
      { id, status },
      function () {
        $.getJSON("../diaries/get_publications.php", function (data) {

          renderPublications(data);
        });
      },
    );
  }
});
