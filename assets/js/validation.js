document.addEventListener("DOMContentLoaded", () => {
    const regForm = document.getElementById("visitorForm");
    if (!regForm) return;

    regForm.addEventListener("submit", (e) => {
        const phone = document.getElementById("phone").value.trim();
        const name = document.getElementById("name").value.trim();
        const phoneRegex = /^[0-9+\-\s]{10,15}$/;

        if (name.length < 2) {
            e.preventDefault();
            alert("Please enter a valid visitor full name.");
            return;
        }
        if (!phoneRegex.test(phone)) {
            e.preventDefault();
            alert("Please enter a valid 10 to 15 digit phone number.");
        }
    });
});