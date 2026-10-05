const goToNew = (where) => {
  console.log(where);
  window.location.assign(where);
};

//Funkce pro kopírování textu do schránky
function copyToClipboard(text) {
  console.log("To clipboard: " + text);
  navigator.clipboard
    .writeText(text)
    .then(() => {
      /* nic */
    })
    .catch((err) => {
      alert("Error při kopírování: " + err);
    });
}

(function ($) {
  $(document).ready(function () {
    /* empty for now */
  });
})(jQuery);
