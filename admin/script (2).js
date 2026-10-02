    /* index */
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("toggleBtn");
const main = document.getElementById("main");

// Khi vào trang: sidebar ẩn
window.onload = () => {
    sidebar.classList.remove("active");
    main.classList.remove("shifted");
};

// Khi click nút ☰
toggleBtn.addEventListener("click", () => {
    sidebar.classList.toggle("active");
    main.classList.toggle("shifted");
});

/* hiện ô đang sử dụng sidebar */
const sidebarLinks = document.querySelectorAll(".sidebar ul li a");

// Lắng nghe click để đánh dấu active
sidebarLinks.forEach(link => {
    link.addEventListener("click", function() {
        sidebarLinks.forEach(l => l.classList.remove("active"));
        this.classList.add("active");
    });
});

// Đánh dấu active dựa trên URL khi load trang
sidebarLinks.forEach(link => {
    if(link.pathname === window.location.pathname) {
        link.classList.add("active");
    }
});
