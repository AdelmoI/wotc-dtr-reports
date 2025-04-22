/**
 * Script per l'interfaccia admin WotC DTR Reports
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Inizializza datepicker
        $('.wotc-dtr-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            firstDay: 1, // Lunedì come primo giorno
            showOtherMonths: true,
            selectOtherMonths: true
        });
        
        // Funzione per calcolare date dalla settimana
        $('#wotc-dtr-week-number, #wotc-dtr-year').on('change', function() {
            var weekNumber = $('#wotc-dtr-week-number').val();
            var year = $('#wotc-dtr-year').val();
            
            if (weekNumber && year) {
                // Calcola le date della settimana
                var firstDay = new Date(year, 0, (1 + (weekNumber - 1) * 7));
                while (firstDay.getDay() !== 1) {
                    firstDay.setDate(firstDay.getDate() - 1);
                }
                
                var lastDay = new Date(firstDay);
                lastDay.setDate(lastDay.getDate() + 6);
                
                // Formatta le date
                var startDate = formatDate(firstDay);
                var endDate = formatDate(lastDay);
                
                // Aggiorna i campi
                $('#wotc-dtr-start-date').val(startDate);
                $('#wotc-dtr-end-date').val(endDate);
            }
        });
        
        // Formatta data in YYYY-MM-DD
        function formatDate(date) {
            var year = date.getFullYear();
            var month = (date.getMonth() + 1).toString().padStart(2, '0');
            var day = date.getDate().toString().padStart(2, '0');
            return year + '-' + month + '-' + day;
        }
    });
})(jQuery);