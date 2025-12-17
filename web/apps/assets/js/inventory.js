/**
 * Inventory Management Interaction
 */

function toggleRow(rowId) {
    const detailRow = document.getElementById(rowId);
    const icon = document.getElementById('icon-' + rowId);
    
    if (detailRow) {
        // Toggle visibility with simple transition effect
        detailRow.classList.toggle('d-none');
        
        // Rotate icon
        if (icon) {
            icon.classList.toggle('active');
        }
    }
}

// Optional: Search filter real-time (jika ingin tanpa reload, tapi kita pakai GET dulu)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inventory Module Loaded');
});