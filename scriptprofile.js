window.onload = () => {
    document.getElementById("name").value = localStorage.getItem("name") || "";
    document.getElementById("dob").value = localStorage.getItem("dob") || "";
    document.getElementById("email").value = localStorage.getItem("email") || "";
    document.getElementById("phone").value = localStorage.getItem("phone") || "";

    let gender = localStorage.getItem("gender");
    if (gender) {
        document.querySelector(`input[name="gender"][value="${gender}"]`).checked = true;
    }

    document.getElementById("display-name").innerText = localStorage.getItem("name") || "Anh Khoa";
};

function saveInfo() {
    localStorage.setItem("name", document.getElementById("name").value);
    localStorage.setItem("dob", document.getElementById("dob").value);
    localStorage.setItem("email", document.getElementById("email").value);
    localStorage.setItem("phone", document.getElementById("phone").value);

    let gender = document.querySelector('input[name="gender"]:checked');
    if (gender) localStorage.setItem("gender", gender.value);

    document.getElementById("display-name").innerText = document.getElementById("name").value;

    alert("Đã cập nhật thông tin!");
}
