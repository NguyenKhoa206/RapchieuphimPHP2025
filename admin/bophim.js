    /* bộ phim */
const tabBtns = document.querySelectorAll(".tab-btn");
const tabPanes = document.querySelectorAll(".tab-pane");

tabBtns.forEach(btn => {
    btn.addEventListener("click", () => {
        // Xóa trạng thái active khỏi tất cả
        tabBtns.forEach(b => b.classList.remove("active"));
        tabPanes.forEach(p => p.classList.remove("active"));

        // Gắn lại cho tab đang chọn
        btn.classList.add("active");
        document.getElementById(btn.dataset.target).classList.add("active");
    });
});

// modal
const modal = document.getElementById("movieModal");
const openBtn = document.getElementById("openModalBtn");
const closeBtn = document.getElementById("closeModalBtn");
const addBtn = document.getElementById("addMovieBtn");

// Mở modal
openBtn.addEventListener("click", () => {
    modal.style.display = "flex";
});

// Đóng modal
closeBtn.addEventListener("click", () => {
    modal.style.display = "none";
});

// Click ngoài modal để đóng
window.addEventListener("click", (e) => {
    if(e.target === modal) modal.style.display = "none";
});