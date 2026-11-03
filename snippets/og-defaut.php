<?php
// Image de partage par défaut (réseaux sociaux) pour les pages sans image : contact, devis, mentions…
add_action('wpseo_add_opengraph_images', function ($images) {
	if (!$images->has_images()) $images->add_image_by_id(103);
});
add_filter('wpseo_twitter_image', function ($img) {
	return $img ?: wp_get_attachment_url(103);
});
