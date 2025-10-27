// config_display.js
document.addEventListener("DOMContentLoaded", function() {
  let config = JSON.parse(localStorage.getItem("systemConfig")) || {};
  if (config.libraryName) {
    let headerTitle = document.querySelector("header h1");
    if (headerTitle) {
      headerTitle.innerText = config.libraryName;
    }
  }
});
