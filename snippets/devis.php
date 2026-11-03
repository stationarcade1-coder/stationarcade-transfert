/* Station Arcade : demandes de devis (enregistrement, e-mails, suivi) */
if (!defined('SA_DEVIS_DEST')) define('SA_DEVIS_DEST', 'contact@stationarcade.fr');

add_action('init', function () {
	register_post_type('sa_devis', [
		'labels' => ['name' => 'Demandes de devis', 'singular_name' => 'Demande de devis', 'menu_name' => 'Demandes de devis', 'edit_item' => 'Demande de devis', 'all_items' => 'Toutes les demandes', 'search_items' => 'Rechercher', 'not_found' => 'Aucune demande'],
		'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 25, 'menu_icon' => 'dashicons-clipboard',
		'supports' => ['title'], 'capability_type' => 'post', 'map_meta_cap' => true,
		'capabilities' => ['create_posts' => 'do_not_allow'],
	]);
});

function sa_devis_statuts() {
	return ['nouvelle' => 'Nouvelle', 'a-completer' => 'À compléter', 'devis-envoye' => 'Devis envoyé', 'signee' => 'Signée', 'perdue' => 'Perdue'];
}

function sa_devis_token() {
	$t = time();
	return $t . '.' . substr(wp_hash('sa-devis|' . $t), 0, 16);
}

add_action('rest_api_init', function () {
	register_rest_route('sa/v1', '/devis/jeton', [
		'methods' => 'GET', 'permission_callback' => '__return_true',
		'callback' => function () { $r = new WP_REST_Response(['jeton' => sa_devis_token()]); $r->header('Cache-Control', 'no-store'); return $r; },
	]);
	register_rest_route('sa/v1', '/devis', [
		'methods' => 'POST', 'permission_callback' => '__return_true',
		'callback' => 'sa_devis_recevoir',
	]);
});

function sa_devis_erreur($msg, $code = 400) {
	return new WP_REST_Response(['ok' => false, 'message' => $msg], $code);
}

function sa_devis_recevoir(WP_REST_Request $req) {
	$d = $req->get_json_params();
	if (!is_array($d)) return sa_devis_erreur('Requête invalide.');
	// Anti-spam : champ piège, jeton signé, délai minimum, limite par adresse IP
	if (!empty($d['site_web'])) return new WP_REST_Response(['ok' => true, 'ref' => 'SA-0'], 200);
	$jeton = isset($d['jeton']) ? (string) $d['jeton'] : '';
	$p = explode('.', $jeton);
	if (count($p) !== 2 || !hash_equals(substr(wp_hash('sa-devis|' . $p[0]), 0, 16), $p[1])) return sa_devis_erreur('Session expirée : rechargez la page puis renvoyez votre demande.');
	$age = time() - (int) $p[0];
	if ($age < 4) return sa_devis_erreur('Envoi trop rapide, merci de réessayer.');
	if ($age > 86400) return sa_devis_erreur('Session expirée : rechargez la page puis renvoyez votre demande.');
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	$cle = 'sa_devis_ip_' . md5($ip);
	$n = (int) get_transient($cle);
	if ($n >= 8) return sa_devis_erreur('Trop de demandes envoyées. Appelez-nous au 06 29 72 90 92.', 429);

	$txt = function ($k, $max = 200) use ($d) { return isset($d[$k]) ? mb_substr(sanitize_text_field((string) $d[$k]), 0, $max) : ''; };
	$f = [
		'nom' => $txt('nom', 120), 'societe' => $txt('societe', 160), 'email' => sanitize_email(isset($d['email']) ? $d['email'] : ''),
		'tel' => $txt('tel', 40), 'date' => $txt('date', 20), 'duree' => $txt('duree', 40), 'lieu' => $txt('lieu', 160),
		'type' => $txt('type', 60), 'perso' => $txt('perso', 40),
		'message' => isset($d['message']) ? mb_substr(sanitize_textarea_field((string) $d['message']), 0, 3000) : '',
		'page' => esc_url_raw(isset($d['page']) ? $d['page'] : ''),
	];
	$err = [];
	if ($f['nom'] === '') $err[] = 'votre nom';
	if (!is_email($f['email'])) $err[] = 'un e-mail valide';
	if ($f['date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['date'])) $f['date'] = '';
	if ($err) return sa_devis_erreur('Merci d’indiquer ' . implode(' et ', $err) . '.');

	$machines = [];
	if (!empty($d['machines']) && is_array($d['machines'])) {
		foreach (array_slice($d['machines'], 0, 40) as $m) {
			if (!is_array($m)) continue;
			$id = isset($m['id']) ? sanitize_key($m['id']) : '';
			$q = isset($m['q']) ? max(1, min(50, (int) $m['q'])) : 1;
			$nom = isset($m['nom']) ? mb_substr(sanitize_text_field($m['nom']), 0, 120) : $id;
			if ($id !== '') $machines[] = ['id' => $id, 'q' => $q, 'nom' => $nom];
		}
	}

	// Doublon accidentel : même e-mail et même contenu dans les 10 dernières minutes
	$empreinte = md5(strtolower($f['email']) . '|' . wp_json_encode($machines) . '|' . $f['date'] . '|' . $f['message']);
	$deja = get_posts(['post_type' => 'sa_devis', 'post_status' => 'private', 'posts_per_page' => 1, 'date_query' => [['after' => '10 minutes ago']], 'meta_key' => '_sa_empreinte', 'meta_value' => $empreinte, 'fields' => 'ids']);
	if ($deja) return new WP_REST_Response(['ok' => true, 'ref' => get_post_meta($deja[0], '_sa_ref', true), 'doublon' => true], 200);

	$id = wp_insert_post(['post_type' => 'sa_devis', 'post_status' => 'private', 'post_title' => $f['nom'] . ($f['societe'] ? ' – ' . $f['societe'] : '')], true);
	if (is_wp_error($id)) return sa_devis_erreur('La demande n’a pas pu être enregistrée. Appelez-nous au 06 29 72 90 92.', 500);
	$ref = 'SA-' . gmdate('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
	foreach ($f as $k => $v) update_post_meta($id, '_sa_' . $k, $v);
	update_post_meta($id, '_sa_machines', $machines);
	update_post_meta($id, '_sa_ref', $ref);
	update_post_meta($id, '_sa_statut', 'nouvelle');
	update_post_meta($id, '_sa_empreinte', $empreinte);
	wp_update_post(['ID' => $id, 'post_title' => $ref . ' · ' . $f['nom'] . ($f['societe'] ? ' – ' . $f['societe'] : '')]);
	set_transient($cle, $n + 1, HOUR_IN_SECONDS);

	$lignes = $machines ? implode("\n", array_map(function ($m) { return '- ' . $m['nom'] . ' × ' . $m['q']; }, $machines)) : '- Aucune machine choisie (conseil souhaité)';
	$corps = "Nouvelle demande de devis $ref\n\n" .
		"Nom : {$f['nom']}\nSociété : {$f['societe']}\nE-mail : {$f['email']}\nTéléphone : {$f['tel']}\n\n" .
		"Date : {$f['date']}\nDurée : {$f['duree']}\nLieu : {$f['lieu']}\nType : {$f['type']}\nPersonnalisation : {$f['perso']}\n\n" .
		"Machines :\n$lignes\n\nMessage :\n{$f['message']}\n\nPage d’origine : {$f['page']}\n\n" .
		"Voir dans l’administration : " . admin_url('post.php?post=' . $id . '&action=edit');
	$ok1 = wp_mail(SA_DEVIS_DEST, "[Devis] $ref – {$f['nom']}" . ($f['societe'] ? " ({$f['societe']})" : ''), $corps, ['Reply-To: ' . $f['nom'] . ' <' . $f['email'] . '>']);
	$conf = "Bonjour {$f['nom']},\n\nNous avons bien reçu votre demande de devis (référence $ref).\n" .
		"Nous revenons vers vous rapidement avec une proposition détaillée.\n\nVotre sélection :\n$lignes\n\n" .
		"Pour toute question : 06 29 72 90 92 ou contact@stationarcade.fr\n\nL’équipe Station Arcade";
	$ok2 = wp_mail($f['email'], "Votre demande de devis Station Arcade ($ref)", $conf, ['Reply-To: Station Arcade <' . SA_DEVIS_DEST . '>']);
	update_post_meta($id, '_sa_mail_entreprise', $ok1 ? 1 : 0);
	update_post_meta($id, '_sa_mail_client', $ok2 ? 1 : 0);
	return new WP_REST_Response(['ok' => true, 'ref' => $ref, 'mail' => (bool) $ok1], 201);
}

// Administration : détail, statut, colonnes, alerte d'envoi
add_action('add_meta_boxes', function () {
	add_meta_box('sa_devis_detail', 'Détail de la demande', function ($post) {
		$g = function ($k) use ($post) { return esc_html(get_post_meta($post->ID, '_sa_' . $k, true)); };
		echo '<table class="widefat striped"><tbody>';
		foreach (['ref' => 'Référence', 'nom' => 'Nom', 'societe' => 'Société', 'email' => 'E-mail', 'tel' => 'Téléphone', 'date' => 'Date', 'duree' => 'Durée', 'lieu' => 'Lieu', 'type' => 'Type', 'perso' => 'Personnalisation', 'page' => 'Page d’origine'] as $k => $l) echo '<tr><th style="width:160px">' . $l . '</th><td>' . $g($k) . '</td></tr>';
		$m = get_post_meta($post->ID, '_sa_machines', true);
		echo '<tr><th>Machines</th><td>' . ($m ? implode('<br>', array_map(function ($x) { return esc_html($x['nom'] . ' × ' . $x['q']); }, $m)) : 'Conseil souhaité') . '</td></tr>';
		echo '<tr><th>Message</th><td>' . nl2br($g('message')) . '</td></tr>';
		echo '<tr><th>E-mails</th><td>Entreprise : ' . (get_post_meta($post->ID, '_sa_mail_entreprise', true) ? 'envoyé' : '<b style="color:#b32d2e">échec</b>') . ' · Client : ' . (get_post_meta($post->ID, '_sa_mail_client', true) ? 'envoyé' : '<b style="color:#b32d2e">échec</b>') . '</td></tr>';
		echo '</tbody></table>';
	}, 'sa_devis', 'normal', 'high');
	add_meta_box('sa_devis_statut', 'Suivi', function ($post) {
		wp_nonce_field('sa_devis_statut', 'sa_devis_nonce');
		$s = get_post_meta($post->ID, '_sa_statut', true);
		echo '<select name="sa_statut" style="width:100%">';
		foreach (sa_devis_statuts() as $k => $l) echo '<option value="' . esc_attr($k) . '"' . selected($s, $k, false) . '>' . esc_html($l) . '</option>';
		echo '</select>';
	}, 'sa_devis', 'side', 'high');
});
add_action('save_post_sa_devis', function ($id) {
	if (!isset($_POST['sa_devis_nonce']) || !wp_verify_nonce($_POST['sa_devis_nonce'], 'sa_devis_statut') || !current_user_can('edit_post', $id)) return;
	$s = isset($_POST['sa_statut']) ? sanitize_key($_POST['sa_statut']) : '';
	if (isset(sa_devis_statuts()[$s])) update_post_meta($id, '_sa_statut', $s);
});
add_filter('manage_sa_devis_posts_columns', function ($c) { return ['cb' => $c['cb'], 'title' => 'Demande', 'sa_date_evt' => 'Date événement', 'sa_statut' => 'Statut', 'date' => 'Reçue le']; });
add_action('manage_sa_devis_posts_custom_column', function ($col, $id) {
	if ($col === 'sa_statut') { $s = get_post_meta($id, '_sa_statut', true); echo esc_html(sa_devis_statuts()[$s] ?? $s); if (!get_post_meta($id, '_sa_mail_entreprise', true)) echo ' <span style="color:#b32d2e">· e-mail non parti</span>'; }
	if ($col === 'sa_date_evt') echo esc_html(get_post_meta($id, '_sa_date', true));
}, 10, 2);
