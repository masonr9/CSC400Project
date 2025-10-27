
  document.getElementById("loginForm").addEventListener("submit", function(event) {
    event.preventDefault();

    let email = document.getElementById("loginEmail").value;
    let password = document.getElementById("loginPassword").value;
    let storedUsers = JSON.parse(localStorage.getItem("users")) || [];
    let foundUser = storedUsers.find(user => user.email === email && user.password === password);

    if (foundUser) {
      // Save logged-in user
      localStorage.setItem("loggedInUser", JSON.stringify(foundUser));

      // Log login event
      let logs = JSON.parse(localStorage.getItem("userLogs")) || [];
      let timestamp = new Date().toLocaleString();
      logs.push({
        user: foundUser.name,
        action: "Logged in as " + foundUser.role,
        time: timestamp
      });
      localStorage.setItem("userLogs", JSON.stringify(logs));

      // Maintenance mode check
      let config = JSON.parse(localStorage.getItem("systemConfig")) || {};
      if (config.maintenanceMode === "on" && foundUser.role !== "Admin") {
        alert("The site is under maintenance. Only Admins can log in.");
        localStorage.removeItem("loggedInUser"); // clear login
        window.location.href = "maintenance.html";
        return;
      }

      // Redirect based on role
      if (foundUser.role === "Admin") {
        window.location.href = "admin.html";
      } else if (foundUser.role === "Librarian") {
        window.location.href = "librarian.html";
      } else {
        window.location.href = "member.html";
      }

    } else {
      document.getElementById("loginMessage").innerText = "Invalid email or password!";
    }
  });





