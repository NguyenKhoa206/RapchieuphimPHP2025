
    /* địa điểm */
// modal
const modal=document.getElementById("rapModal");
const openBtn=document.getElementById("openRapModalBtn");
const closeBtn=document.getElementById("closeRapModalBtn");
const addBtn=document.getElementById("addRapBtn");

// Mở modal
openBtn.addEventListener("click",()=>{
    modal.style.display="flex";
});

// Đóng modal
closeBtn.addEventListener("click",()=>{
    modal.style.display="none";
});

// click ngoài modal để đóng
window.addEventListener("click",(f)=>{
    if(f.target===modal) 
    modal.style.display="none";
})