// Employee Tracker Admin — shared JS helpers.
// Page-specific logic (DataTables init, Leaflet maps, polling) lives
// inline in each page's view for now, wrapped in DOMContentLoaded, and
// can call the small helpers below.

document.addEventListener('DOMContentLoaded', function () {
    // Auto-close the mobile offcanvas sidebar after tapping a menu link,
    // so navigating doesn't leave the overlay open on the next page.
    var sidebar = document.getElementById('atSidebar');
    if (sidebar) {
        sidebar.querySelectorAll('a.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992 && window.bootstrap) {
                    var instance = window.bootstrap.Offcanvas.getInstance(sidebar);
                    if (instance) instance.hide();
                }
            });
        });
    }
});

/**
 * Small fetch wrapper used by every AJAX-driven page (DataTables server
 * calls aside, which use jQuery's own ajax). Returns a Promise.
 */
function atFetchJSON(url, options) {
    return fetch(url, options).then(function (res) {
        if (!res.ok) throw new Error('Request failed: ' + res.status);
        return res.json();
    });
}

/** Consistent badge class per attendance/pengajuan/pengiriman status string. */
function atStatusBadgeClass(status) {
    var map = {
        PRESENT: 'badge-at-green',
        LATE: 'badge-at-orange',
        ABSENT: 'badge-at-red',
        PENDING: 'badge-at-orange',
        APPROVED: 'badge-at-green',
        REJECTED: 'badge-at-red',
        IN_PROGRESS: 'badge-at-blue',
        ARRIVED: 'badge-at-orange',
        COMPLETED: 'badge-at-green',
        ACTIVE: 'badge-at-green',
        INACTIVE: 'badge-at-gray'
    };
    return map[status] || 'badge-at-gray';
}
