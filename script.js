<script>
const slider = document.querySelector('.slider');
const items = document.querySelectorAll('.promo-item');
const prev = document.querySelector('.prev');
const next = document.querySelector('.next');

let index = 0;

function showSlide(n) {
  const total = items.length;
  index = (n + total) % total;
  slider.style.transform = `translateX(-${index * 25}%)`;
}

prev.addEventListener('click', () => showSlide(index - 1));
next.addEventListener('click', () => showSlide(index + 1));

// Tự động chạy sau mỗi 4 giây
setInterval(() => {
  showSlide(index + 1);
}, 4000);
</script>
