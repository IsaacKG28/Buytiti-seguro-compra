<?php
/**
 * Plugin Name: Buytiti - Costo de Seguro
 * Description: Agrega un costo de seguro de envío opcional al carrito.
 * Version: 1.0
 * Author: Fernando Isaac González Medina 
 */

// Iniciar la sesión si no está iniciada
function iniciar_sesion_si_no_existe() {
    if (!session_id()) {
        session_start();
    }
}
add_action('init', 'iniciar_sesion_si_no_existe');

// Función para calcular el costo del seguro basado en el total de la compra
function calcular_costo_seguro($total_compra) {
    if ($total_compra >= 1 && $total_compra < 2000) {
        return 35;
    } elseif ($total_compra >= 2000 && $total_compra < 5000) {
        return 50;
    } elseif ($total_compra >= 5000 && $total_compra < 10000) {
        return 100;
    } elseif ($total_compra >= 10000 && $total_compra < 20000) {
        return 150;
    } elseif ($total_compra >= 20000 && $total_compra <= 30000) {
        return 300;
    } else {
        return 0;
    }
}

// Agregar el costo del seguro al carrito
function agregar_costo_seguro_al_carrito() {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $total_compra = WC()->cart->subtotal; // Obtén el total del carrito
    $costo_seguro = calcular_costo_seguro($total_compra);

    // Verificar si el cliente ha optado por quitar el seguro
    if (isset($_SESSION['quitar_seguro']) && $_SESSION['quitar_seguro'] === 'yes') {
        $costo_seguro = 0; // No agregar el costo si el cliente ha optado por quitarlo
    }

    WC()->cart->add_fee('Seguro de compra', $costo_seguro);
}
add_action('woocommerce_cart_calculate_fees', 'agregar_costo_seguro_al_carrito');

// Mostrar la opción para quitar el seguro en el carrito
function mostrar_opcion_quitar_seguro() {
    // Verificar si la opción para quitar el seguro está marcada en la sesión
    $quitar_seguro = isset($_SESSION['quitar_seguro']) && $_SESSION['quitar_seguro'] === 'yes' ? 'checked' : '';
    
    // Obtener el texto de la etiqueta según el estado del seguro de compra
    $etiqueta_texto = $quitar_seguro ? 'Haz quitado el seguro de compra, desmarca la casilla para volverlo a agregar.' : 'Quiero quitar el seguro de compra';
    
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($){
        // Mover la opción de quitar seguro arriba de la fila "fee"
        var $feeRow = $('.cart_totals .fee').closest('tr');
        var quitarSeguroHtml = '<tr class="quitar-seguro-option"><td colspan="2" style="font-weight: bold; color: red; margin-top: 10px;"><label><input type="checkbox" id="quitar-seguro" name="quitar_seguro" value="yes" <?php echo $quitar_seguro; ?>> <span id="seguro-texto"><?php echo $etiqueta_texto; ?></span></label></td></tr>';
        $feeRow.before(quitarSeguroHtml);

        $('#quitar-seguro').change(function(){
            var quitar_seguro = $(this).is(':checked') ? 'yes' : 'no';

            if(quitar_seguro === 'yes'){
                Swal.fire({
                    title: 'CUIDADO',
                    text: 'Estás quitando el seguro de compra contra robo parcial o total...',
                    icon: 'warning',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#d33',
                    iconColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Hacer la solicitud AJAX para actualizar el carrito
                        $.ajax({
                            type: 'POST',
                            url: wc_cart_params.ajax_url,
                            data: {
                                action: 'actualizar_costo_seguro',
                                quitar_seguro: quitar_seguro
                            },
                            success: function(response) {
                                // Recargar la página
                                location.reload();
                            },
                            error: function(xhr, status, error) {
                                console.log('AJAX Error:', error);
                            }
                        });
                    } else {
                        // Si no confirma, revertir la casilla de verificación
                        $('#quitar-seguro').prop('checked', !$(this).is(':checked'));
                    }
                });
            } else {
                Swal.fire({
                    title: 'ÉXITO',
                    text: 'Haz agregado correctamente el seguro de compra.',
                    icon: 'success',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#3085d6',
                    iconColor: '#3085d6'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Hacer la solicitud AJAX para actualizar el carrito
                        $.ajax({
                            type: 'POST',
                            url: wc_cart_params.ajax_url,
                            data: {
                                action: 'actualizar_costo_seguro',
                                quitar_seguro: quitar_seguro
                            },
                            success: function(response) {
                                // Recargar la página
                                location.reload();
                            },
                            error: function(xhr, status, error) {
                                console.log('AJAX Error:', error);
                            }
                        });
                    } else {
                        // Si no confirma, revertir la casilla de verificación
                        $('#quitar-seguro').prop('checked', !$(this).is(':checked'));
                    }
                });
            }
        });

        // Verificar si la opción para quitar el seguro debe estar marcada
        if ('<?php echo $quitar_seguro; ?>' === 'yes') {
            $('#quitar-seguro').prop('checked', true);
        }
    });

    </script>
    <?php
}
add_action('woocommerce_cart_totals_after_order_total', 'mostrar_opcion_quitar_seguro');

// Función AJAX para actualizar el costo del seguro en el carrito
function actualizar_costo_seguro() {
    // Verificar si el cliente ha seleccionado quitar el seguro
    $quitar_seguro = isset($_POST['quitar_seguro']) ? sanitize_text_field($_POST['quitar_seguro']) : 'no';

    // Guardar la opción en la sesión
    if ($quitar_seguro === 'yes') {
        $_SESSION['quitar_seguro'] = 'yes';
    } else {
        unset($_SESSION['quitar_seguro']);
    }

    WC()->cart->calculate_totals();
    wp_send_json_success(['reload_page' => true]); // Cambiado a wp_send_json_success para asegurar JSON response
}
add_action('wp_ajax_actualizar_costo_seguro', 'actualizar_costo_seguro');
add_action('wp_ajax_nopriv_actualizar_costo_seguro', 'actualizar_costo_seguro');

// Agregar estilos CSS para el texto del seguro
function agregar_estilos_css() {
    echo '<style>
        .woocommerce .cart_totals .fee strong {
            font-weight: bold;
        }
        .quitar-seguro-option {
            font-weight: bold;
            color: red;
            margin-top: 10px;
        }
    </style>';
}
add_action('wp_head', 'agregar_estilos_css');

// Enqueue SweetAlert desde el plugin
function enqueue_sweetalert() {
    wp_enqueue_script('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_sweetalert');
?>
