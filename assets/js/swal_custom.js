/**
 * assets/js/swal_custom.js
 * Global SweetAlert2 Configuration for Minimalist AMOLED UI
 */

// We can't globally override Swal.fire easily without a proxy, 
// but we can define a standard mixin that we include and use.
// However, our CSS overrides in style.css already handle the visual 
// and button styling even if buttonsStyling is true.

const MinimalistSwal = Swal.mixin({
    customClass: {
        confirmButton: 'swal2-confirm',
        cancelButton: 'swal2-cancel',
        denyButton: 'swal2-deny',
        popup: 'swal2-popup-minimalist'
    },
    buttonsStyling: false, // Use our CSS buttons
    showClass: {
        popup: 'animate-fade-up' 
    },
    hideClass: {
        popup: 'animate-fade-down'
    }
});

// For existing code that uses Swal.fire directly, 
// the CSS with !important in style.css will take over.
console.log("Minimalist SweetAlert2 configuration loaded.");
