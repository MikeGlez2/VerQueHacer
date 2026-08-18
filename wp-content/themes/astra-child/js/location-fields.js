jQuery(document).ready(function($) {
    // Esperar a que Meta Box inicialice completamente
    setTimeout(function() {
        var $provinceSelect = $('select[name="zones_id"]');
        var $citySelect = $('select[name="post_city_id"]');
        
        console.log('Province select found:', $provinceSelect.length);
        console.log('City select found:', $citySelect.length);
        console.log('Saved zone ID:', vqh_location.saved_zone_id);
        console.log('Saved city ID:', vqh_location.saved_city_id);
        
        // Función para cargar ciudades
        function loadCities(zoneId, selectedCityId) {
            if (!zoneId || zoneId === '') {
                $citySelect.html('<option value="">-- Primero selecciona una provincia --</option>');
                return;
            }
            
            $citySelect.html('<option value="">Cargando ciudades...</option>');
            $citySelect.prop('disabled', true);
            
            $.ajax({
                url: vqh_location.ajax_url,
                type: 'POST',
                data: {
                    action: 'vqh_get_cities_by_zone',
                    zone_id: zoneId,
                    nonce: vqh_location.nonce
                },
                success: function(response) {
                    $citySelect.html(response);
                    $citySelect.prop('disabled', false);
                    
                    // Si hay una ciudad guardada, seleccionarla
                    if (selectedCityId && selectedCityId !== '') {
                        $citySelect.val(selectedCityId);
                        console.log('Selected city:', selectedCityId);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading cities:', error);
                    $citySelect.html('<option value="">Error al cargar ciudades</option>');
                    $citySelect.prop('disabled', false);
                }
            });
        }
        
        // Cuando cambia la provincia
        $provinceSelect.on('change', function() {
            var zoneId = $(this).val();
            console.log('Province changed to:', zoneId);
            loadCities(zoneId, '');
        });
        
        // Cargar ciudades al iniciar si hay provincia seleccionada
        if (vqh_location.saved_zone_id && vqh_location.saved_zone_id !== '') {
            // Pequeño delay para asegurar que Meta Box ha terminado de inicializar
            setTimeout(function() {
                // Asegurar que el valor de provincia esté seleccionado
                $provinceSelect.val(vqh_location.saved_zone_id);
                
                // Cargar ciudades de esa provincia
                loadCities(vqh_location.saved_zone_id, vqh_location.saved_city_id);
            }, 500);
        }
    }, 300); // Delay inicial para asegurar que el DOM está listo
});