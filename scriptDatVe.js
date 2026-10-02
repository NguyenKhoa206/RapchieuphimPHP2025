// scriptDatVe.js
// --- CẤU HÌNH ---
const TICKET_PRICE = 90000;

// Dữ liệu Rạp
const CINEMA_NAMES = {
    'galaxy-qt': 'Galaxy Quang Trung',
    'galaxy-nd': 'Galaxy Nguyễn Du',
    'galaxy-tb': 'Galaxy Tân Bình',
};

// Dữ liệu Suất chiếu cho trang chi tiết phim (movie jjk.html)
const MOVIE_SHOWTIMES_DATA = {
    'mon': ['10:00', '13:00', '16:00', '20:00'],
    'tue': ['11:30', '14:30', '18:30'],
    'wed': ['10:30', '13:30', '17:30', '21:00'], // ĐÃ THÊM LỊCH CHIẾU THỨ TƯ
    'today': ['09:00', '15:00', '22:00'],
    'thu': ['10:00', '14:00', '19:00'],
    'fri': ['10:00', '13:00', '16:00', '21:00'],
    'sat': ['09:00', '12:00', '15:00', '18:00', '22:00'],
    'sun': ['09:00', '12:00', '15:00', '18:00', '22:00'],
};


// --- HÀM TIỆN ÍCH ---
function qs(id) { return document.getElementById(id); }
function formatMoney(v) { return v.toLocaleString('vi-VN') + " VNĐ"; }


/* =======================================================
   TRANG PHIM — movie jjk.html (MỚI)
======================================================= */
(function initPageMovie() {
    // Lấy thông tin phim từ thẻ cha
    const movieInfoEl = document.querySelector('.showTimes__row div[data-phim-id]'); 
    if (!movieInfoEl) return; // không phải trang phim

    const phimId = movieInfoEl.dataset.phimId || 'unknown';
    const phimName = movieInfoEl.dataset.phimName || 'Phim đang chọn';

    // 1. Hàm hiển thị suất chiếu cho một ngày cụ thể
    function renderShowtimesForDay(dayId) {
        const showtimeListEl = qs(`showtimeList-${dayId}`);
        if (!showtimeListEl) return;

        const timesForDay = MOVIE_SHOWTIMES_DATA[dayId] || [];
        
        let htmlContent = '';
        
        if (timesForDay.length === 0) {
            htmlContent = '<p class="text-center text-danger fw-bold">Không tìm thấy suất chiếu cho ngày này.</p>';
            showtimeListEl.innerHTML = htmlContent;
            return;
        }

        let cinemaHtml = '';
        
        // Lặp qua 3 rạp đã định nghĩa
        Object.keys(CINEMA_NAMES).forEach((rapId, index) => {
            const rapName = CINEMA_NAMES[rapId];
            
            // Chia suất chiếu cho 3 rạp (ví dụ: mỗi rạp lấy 1/3 tổng số suất)
            const times = timesForDay.filter((_, i) => (i + index) % 3 === index);

            cinemaHtml += `
                <div class="col-12 mb-4 border p-3 rounded shadow-sm bg-white">
                    <h4 class="text-primary">${rapName}</h4>
                    <div class="d-flex flex-wrap gap-2">
            `;
            
            if (times.length > 0) {
                times.forEach(time => {
                    // MỖI BUTTON THỜI GIAN
                    // Lấy ngày chiếu thực tế (Tạm thời là ngày hiện tại)
                    const ngayChieu = new Date().toLocaleDateString('en-CA'); 

                    cinemaHtml += `
                        <button 
                            class="btn btn-outline-warning fw-bold showtime-btn" 
                            data-rap-id="${rapId}" 
                            data-phim-id="${phimId}" 
                            data-phim-name="${phimName}" 
                            data-ngay-chieu="${ngayChieu}"
                            data-suat="${time}">
                            ${time}
                        </button>
                    `;
                });
            } else {
                cinemaHtml += '<span class="text-muted">Không có suất chiếu tại rạp này vào ngày này.</span>';
            }

            cinemaHtml += `
                    </div>
                </div>
            `;
        });

        showtimeListEl.innerHTML = `<div class="row">${cinemaHtml}</div>`;
        
        // 2. Gắn sự kiện click cho các button suất chiếu trong tab này
        showtimeListEl.querySelectorAll('.showtime-btn').forEach(button => {
            button.addEventListener('click', handleBookingFromMoviePage);
        });
    }

    // 3. Hàm xử lý khi click vào suất chiếu -> Lưu Booking và chuyển trang
    function handleBookingFromMoviePage(event) {
        const btn = event.currentTarget;
        const rapId = btn.dataset.rapId;
        const rapName = CINEMA_NAMES[rapId];
        const phimId = btn.dataset.phimId;
        const phimName = btn.dataset.phimName;
        const ngay = btn.dataset.ngayChieu; 
        const suat = btn.dataset.suat;

        const booking = {
            rap: rapId,
            rapName: rapName,
            phim: phimId,
            phimName: phimName,
            ngay: ngay,
            suat: suat
        };

        localStorage.setItem("bookingInfo", JSON.stringify(booking));
        window.location.href = "seating-payment.html"; // Chuyển đến trang chọn ghế
    }

    // 4. Gắn sự kiện chuyển tab và load suất chiếu
    const tabLinks = document.querySelectorAll('#myTab .nav-link');
    
    tabLinks.forEach(link => {
        const tabId = link.getAttribute('id').replace('-tab', '');
        
        // Load suất chiếu lần đầu cho tab đang active (mặc định là 'mon')
        if (link.classList.contains('active')) {
            renderShowtimesForDay(tabId);
        }

        // Gắn sự kiện cho các tab để render khi click
        link.addEventListener('click', (e) => {
            renderShowtimesForDay(tabId);
        });
    });

})();


/* =======================================================
   TRANG 1 — datve.html (Giữ nguyên logic cũ)
======================================================= */
(function initPage1() {
    const btnDatVe = qs("btnDatVe");
    if (!btnDatVe) return; // không phải trang đặt vé

    const chonRap = qs("chonRap");
    const chonPhim = qs("chonPhim");
    const chonNgay = qs("chonNgay");
    const chonSuat = qs("chonSuat");

    // Set ngày mặc định = hôm nay
    const today = new Date().toISOString().slice(0, 10);
    chonNgay.min = today;
    chonNgay.value = today;

    function checkForm() {
        const ok =
            chonRap.value &&
            chonPhim.value &&
            chonNgay.value &&
            chonSuat.value;

        btnDatVe.disabled = !ok;
    }

    [chonRap, chonPhim, chonNgay, chonSuat].forEach(e => e.addEventListener("change", checkForm));

    btnDatVe.disabled = true;
    checkForm();

    // Khi ấn ĐẶT NGAY
    btnDatVe.addEventListener("click", () => {
        if (btnDatVe.disabled) return;

        const booking = {
            rap: chonRap.value,
            rapName: chonRap.selectedOptions[0].text,
            phim: chonPhim.value,
            phimName: chonPhim.selectedOptions[0].text,
            ngay: chonNgay.value,
            suat: chonSuat.value
        };

        localStorage.setItem("bookingInfo", JSON.stringify(booking));
        window.location.href = "seating-payment.html";
    });
})();


/* =======================================================
   TRANG 2 — seating-payment.html (CẬP NHẬT COMBO)
======================================================= */
(function initPage2() {
    const seatMap = qs("seat-map");
    if (!seatMap) return; // không phải trang chọn ghế

    // --- ELEMENTS CẦN THIẾT ---
    const chonGheSection = qs("ChonGhe"); 
    const infoPhim = qs("infoPhim"); 
    const infoRap = qs("infoRap");   
    const infoThoiGian = qs("infoThoiGian"); 
    const selectedSeatsList = qs("selectedSeatsList");
    const selectedSeatsCount = qs("selectedSeatsCount");
    const selectedCombosList = qs("selectedCombosList"); 
    const totalPriceElement = qs("totalPrice");
    const btnThanhToan = qs("btnThanhToan");
    const comboListElement = qs("combo-list");

    // Lấy Modal elements (Để logic đầy đủ hơn)
    const confirmationModal = qs("confirmationModal");
    const confirmPhim = qs("confirmPhim");
    const confirmSeats = qs("confirmSeats");
    const confirmTotal = qs("confirmTotal");
    const closeModalBtn = qs("closeModalBtn");
    
    // --- LẤY BOOKING TỪ LOCALSTORAGE ---
    const booking = JSON.parse(localStorage.getItem("bookingInfo") || "null");
    if (!booking) {
        // Nếu không có info, chuyển về trang đặt vé
        // alert("Không tìm thấy thông tin suất chiếu."); 
        // window.location.href = "datve.html";
        return;
    }
    
    // HIỂN THỊ PHẦN CHỌN GHẾ VÀ CẬP NHẬT INFO
    if(chonGheSection) {
        chonGheSection.style.display = 'block'; 
    }
    if (infoPhim) infoPhim.textContent = booking.phimName;
    if (infoRap) infoRap.textContent = booking.rapName;
    if (infoThoiGian) infoThoiGian.textContent = booking.ngay + " " + booking.suat;

    // --- SƠ ĐỒ GHẾ ---
    const SEAT_LAYOUT = [
        "AAAAAAAAAAAAAAAAAAAA",
        "AAAAAAAAAAAAAAAAAAAA",
        "ABAAAAAAAAABAAAAAAAA",
        "AAAAAAAAAAAAAAAAAAAA",
        "AAAAAAAAAAAAAAAAAAAA",
        "AAAAABAAAAAAAAAAAAAA",
        "AAAAAAAAAAAAAAAAAAAA",
        "AAAAAAAAAAAAAAAAAAAA",
        "AAAAAAAAAAAAAAAAAAAA"
    ];

    const ROWS = ["A","B","C","D","E","F","G","H","I"];

    let selectedSeats = [];
    let selectedCombos = {}; // LƯU TRỮ COMBO

    // --- VẼ GHẾ / CHỌN GHẾ ---
    function renderSeats() {
        seatMap.innerHTML = "";
        // ... (Logic renderSeats) ...
        SEAT_LAYOUT.forEach((rowStr, rowIndex) => {
            const rowDiv = document.createElement("div");
            rowDiv.className = "seat-row";

            const label = document.createElement("div");
            label.className = "row-label";
            label.textContent = ROWS[rowIndex];
            rowDiv.appendChild(label);

            [...rowStr].forEach((seatChar, i) => {
                const seat = document.createElement("div");
                seat.className = "seat";
                seat.textContent = i + 1;

                const seatId = ROWS[rowIndex] + (i + 1);

                if (seatChar === "B") {
                    seat.classList.add("booked");
                } else {
                    seat.dataset.id = seatId;
                    seat.addEventListener("click", () => toggleSeat(seat, seatId));
                }

                rowDiv.appendChild(seat);
            });

            seatMap.appendChild(rowDiv);
        });
    }

    function toggleSeat(el, id) {
        if (el.classList.contains("selected")) {
            el.classList.remove("selected");
            selectedSeats = selectedSeats.filter(s => s !== id);
        } else {
            el.classList.add("selected");
            selectedSeats.push(id);
        }

        updateUI();
    }
    
    // --- XỬ LÝ COMBO (MỚI) ---
    function initComboSelection() {
        if (!comboListElement) return;

        comboListElement.querySelectorAll('.combo-item').forEach(item => {
            const comboId = item.querySelector('.plus-btn').dataset.comboId; 
            selectedCombos[comboId] = 0;

            item.addEventListener('click', (e) => {
                const target = e.target;
                if (target.classList.contains('plus-btn') || target.classList.contains('minus-btn')) {
                    handleComboSelection(target, comboId);
                }
            });
        });
    }

    function handleComboSelection(btn, comboId) {
        const itemElement = btn.closest('.combo-item');
        const comboQuantityElement = itemElement.querySelector(`#${comboId}-quantity`);
        
        let currentQuantity = selectedCombos[comboId] || 0;

        if (btn.classList.contains('plus-btn')) {
            currentQuantity++;
        } else if (btn.classList.contains('minus-btn') && currentQuantity > 0) {
            currentQuantity--;
        }

        selectedCombos[comboId] = currentQuantity;
        comboQuantityElement.textContent = currentQuantity;
        updateUI();
    }

    function calculateComboTotal() {
        let total = 0;
        let comboDetails = [];

        if (comboListElement) {
            comboListElement.querySelectorAll('.combo-item').forEach(item => {
                const comboId = item.querySelector('.plus-btn').dataset.comboId;
                const price = parseInt(item.dataset.price); 
                const name = item.dataset.name; 
                const quantity = selectedCombos[comboId] || 0;

                if (quantity > 0) {
                    total += quantity * price;
                    comboDetails.push(`${name} (x${quantity})`);
                }
            });
        }

        return { total, details: comboDetails };
    }

    // --- CẬP NHẬT UI ---
    function updateUI() {
        // Cập nhật Ghế
        selectedSeatsCount.textContent = selectedSeats.length;
        selectedSeatsList.textContent =
            selectedSeats.length ? selectedSeats.join(", ") : "Chưa chọn ghế nào";

        // Cập nhật Combo 
        const comboResult = calculateComboTotal();
        selectedCombosList.textContent =
            comboResult.details.length ? comboResult.details.join(", ") : "Chưa chọn combo nào";

        // Tính Tổng Cộng (Vé + Combo)
        const seatTotal = selectedSeats.length * TICKET_PRICE;
        const total = seatTotal + comboResult.total;
        
        totalPriceElement.textContent = formatMoney(total);

        // KHÓA nút thanh toán nếu chưa chọn ghế
        btnThanhToan.disabled = selectedSeats.length === 0;
    }

    // --- NÚT THANH TOÁN ---
    btnThanhToan.addEventListener("click", () => {
        if (selectedSeats.length === 0) {
            alert("Bạn chưa chọn ghế!");
            return;
        }

        const comboResult = calculateComboTotal();
        const total = (selectedSeats.length * TICKET_PRICE) + comboResult.total;

        // Hiển thị thông tin trong modal
        if (confirmPhim) confirmPhim.textContent = booking.phimName;
        if (confirmSeats) confirmSeats.textContent = selectedSeats.join(", ") + 
                                  (comboResult.details.length ? ` (+ Combo: ${comboResult.details.join(", ")})` : '');
        if (confirmTotal) confirmTotal.textContent = formatMoney(total);
        
        if (confirmationModal) confirmationModal.style.display = "flex";
    });
    
    // Xử lý nút đóng modal (để hoàn tất giao dịch)
    if (closeModalBtn) {
        closeModalBtn.addEventListener("click", () => {
            if (confirmationModal) confirmationModal.style.display = "none";
            localStorage.removeItem("bookingInfo"); // Xóa dữ liệu khi thanh toán xong
            window.location.href = "datve.html"; // Quay lại trang chính
        });
    }


    // --- KHỞI TẠO ---
    selectedSeats = [];
    renderSeats();
    initComboSelection(); 
    updateUI();
})();