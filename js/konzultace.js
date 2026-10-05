function openForm(id, onlyonline) {
  const isOnlyOnline = Number(onlyonline) === 1;

  // Nastav ID slotu do hidden inputu
  document.getElementById("slot_id").value = id;

  const select = $("#form-select");

  select.find("option").prop("selected", false); // cancel všechna selected
  select.find('option[value="teams"]').prop("selected", true);
  select.trigger("change");

  if (isOnlyOnline) {
    // Zakázat a případně skrýt volbu "in person"
    select.find('option[value="osobne"]').prop("disabled", true).hide();

    // Pokud je "in person" aktuálně vybraná, přepni na "teams"
    select.val("teams").trigger("change");
  } else {
    // Zpřístupni a ukaž "in person"
    select.find('option[value="osobne"]').prop("disabled", false).show();

    // Nastav výchozí volbu na "in person"
    select.val("osobne").trigger("change");
  }

  // Zobraz formulář
  document.getElementById("popup-form").style.display = "block";

  /*
  if (Number(onlyonline) === 1) {
    // Hide "Osobně" and switch selection if needed
    $('#form-select option[value="osobne"]').hide();
    if ($("#form-select").val() === "osobne") {
      $("#form-select").val("teams"); // default fallback
    }
  } else {
    $('#form-select option[value="osobne"]').show();
    $("#form-select").val("osobne");
  }


  document.getElementById("popup-form").style.display = "block";*/
}

function closeForm() {
  document.getElementById("popup-form").style.display = "none";
}
