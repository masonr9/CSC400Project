document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector('.form-box');

  form.addEventListener("submit", (event) => {
    event.preventDefault(); // prevents the page from reloading on submit

    // trim method is used to display values in console later on
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('pw').value.trim();

    // validating all fields are filled, not necessary at the moment as required attribute exists in form
    if (name === "" || email === "" || password === "") {
      alert("Fill in all fields.");
      return;
    }

    // checking to make sure the password is at least a certain length
    if (password.length < 8) {
      alert("Password must be at least 8 characters long.")
      return;
    }

    const userData = {
      name: name,
      email: email,
      password, password
    };

    // confirming the inputs went through
    console.log("User registered:", userData);

    localStorage.setItem("User", JSON.stringify(userData));

    alert("Your account was created.");

    // resets the form once you register
    form.reset();
  })
})