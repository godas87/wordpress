<?php 
// Obter instância da API de geolocalização
global $geo_api;
$location_data = $geo_api->get_smart_location();
?>
<a 
    href="#" 
    class="bt-modal bt-location-data" 
    data-modal="localizacao"
    title="<?php _e('Definir localização', 'bazar'); ?>"
    rel="me"
>
    <i class="fa fa-map-marker-alt"></i>
    <?php 
    $city_state = '';
    if( $location_data && !empty($location_data['cidade']) ){
        $city_state = $location_data['cidade'];
        if( !empty($location_data['estado_sigla']) ){
            $city_state .= ' / ' . $location_data['estado_sigla'];
        }
    }                
    echo $city_state ? $city_state : 'Localização';
    ?>
</a>