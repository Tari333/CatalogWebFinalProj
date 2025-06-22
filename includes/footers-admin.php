<!-- Bootstrap and jQuery Scripts -->
<script src="../libs/jquery/dist/jquery.min.js"></script>
<script src="../libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<!-- Third-party JS -->
<script src="../libs/dataTables/datatables.min.js"></script>
<script src="../libs/highcharts/highcharts.js"></script>
<script src="../libs/highcharts/highcharts-more.js"></script>
<script src="../libs/highcharts/modules/exporting.js"></script>
<script src="../libs/highcharts/modules/export-data.js"></script>
<script src="../libs/highcharts/modules/accessibility.js"></script>
<script src="../libs/select2/dist/js/select2.min.js"></script>
<script src="../libs/sweetalert2/dist/sweetalert2.min.js"></script>
<!-- JQ validate -->
<script src="../libs/jquery-validation/dist/jquery.validate.min.js"></script>
<script src="../libs/jquery-validation-unobtrusive/jquery.validate.unobtrusive.min.js"></script>
<!-- Custom JS -->
<script>
    // Global chart variables for updating
    let monthlySalesChart;
    let orderStatusChart;

    // Sidebar toggle functionality
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
    });
    
    // Mobile sidebar toggle
    if (window.innerWidth <= 768) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
        });
    }
</script>