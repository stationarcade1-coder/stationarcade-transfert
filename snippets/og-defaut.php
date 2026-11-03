<?php
// Image de partage par défaut (réglage Yoast « image par défaut ») : utilisée seulement pour les pages sans image propre
add_filter('option_wpseo_social', function ($o) {
	if (!is_array($o)) return $o;
	if (empty($o['og_default_image_id'])) {
		$o['og_default_image_id'] = 103;
		$o['og_default_image'] = wp_get_attachment_url(103);
	}
	return $o;
});
