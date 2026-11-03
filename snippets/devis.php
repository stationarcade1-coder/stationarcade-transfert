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
		'tel' => $txt('tel', 40), 'date' => $txt('date', 20), 'date_fin' => $txt('date_fin', 20), 'lieu' => $txt('lieu', 160),
		'perso' => $txt('perso', 10),
		'message' => isset($d['message']) ? mb_substr(sanitize_textarea_field((string) $d['message']), 0, 3000) : '',
		'page' => esc_url_raw(isset($d['page']) ? $d['page'] : ''),
	];
	$err = [];
	if ($f['nom'] === '') $err[] = 'votre nom';
	if (!is_email($f['email'])) $err[] = 'un e-mail valide';
	if (strlen(preg_replace('/\D/', '', $f['tel'])) < 9) $err[] = 'votre téléphone';
	foreach (['date', 'date_fin'] as $k) { if ($f[$k] !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$k]) || $f[$k] < current_time('Y-m-d'))) $f[$k] = ''; }
	if ($f['date'] === '' || $f['date_fin'] < $f['date']) $f['date_fin'] = $f['date'];
	$fmt = function ($s) { return $s ? date_i18n('j F Y', strtotime($s)) : ''; };
	$dates = $f['date'] ? ($f['date_fin'] !== $f['date'] ? 'du ' . $fmt($f['date']) . ' au ' . $fmt($f['date_fin']) : 'le ' . $fmt($f['date'])) : 'non précisées';
	if ($err) return sa_devis_erreur('Merci d’indiquer ' . implode(' et ', $err) . '.');

	$machines = [];
	if (!empty($d['machines']) && is_array($d['machines'])) {
		foreach (array_slice($d['machines'], 0, 40) as $m) {
			if (!is_array($m)) continue;
			$id = isset($m['id']) ? sanitize_key($m['id']) : '';
			$q = isset($m['q']) ? max(1, min(50, (int) $m['q'])) : 1;
			$nom = isset($m['nom']) ? mb_substr(sanitize_text_field($m['nom']), 0, 120) : $id;
			$img = isset($m['img']) ? esc_url_raw($m['img']) : '';
			if ($img && strpos($img, content_url('uploads/')) !== 0) $img = '';
			$url = isset($m['url']) ? esc_url_raw(home_url($m['url'])) : '';
			if ($id !== '') $machines[] = ['id' => $id, 'q' => $q, 'nom' => $nom, 'img' => $img, 'url' => $url];
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
	$infos = [['Dates', $dates], ['Lieu', $f['lieu'] ?: 'Non précisé'], ['Personnalisation', $f['perso'] === 'Oui' ? 'Oui, machines à habiller' : 'Non']];
	$coord = [['Nom', $f['nom']], ['Société', $f['societe'] ?: '—'], ['E-mail', $f['email']], ['Téléphone', $f['tel']]];
	$admin_url = admin_url('post.php?post=' . $id . '&action=edit');
	$tel_client = preg_replace('/[^0-9+]/', '', $f['tel']);
	$html1 = sa_mail_html([
		'pre' => "Nouvelle demande $ref de {$f['nom']}",
		'kick' => 'Nouvelle demande de devis', 'titre' => $ref, 'intro' => esc_html($f['nom']) . ($f['societe'] ? ' · ' . esc_html($f['societe']) : '') . ' vient d’envoyer une demande depuis le site.',
		'boutons' => [['Répondre au client', 'mailto:' . $f['email'] . '?subject=' . rawurlencode("Votre devis Station Arcade ($ref)"), true], ['Appeler ' . $f['tel'], 'tel:' . $tel_client, false]],
		'blocs' => [['L’événement', $infos], ['Le client', $coord]],
		'machines' => $machines, 'message' => $f['message'],
		'pied' => '<a href="' . esc_url($admin_url) . '" style="color:#00e1d4">Ouvrir la demande dans WordPress</a> pour changer son statut.<br>Page d’origine : ' . esc_html($f['page']),
	]);
	$corps = "Nouvelle demande de devis $ref\n\n" .
		"Nom : {$f['nom']}\nSociété : {$f['societe']}\nE-mail : {$f['email']}\nTéléphone : {$f['tel']}\n\n" .
		"Dates : $dates\nLieu : {$f['lieu']}\nPersonnalisation : {$f['perso']}\n\n" .
		"Machines :\n$lignes\n\nMessage :\n{$f['message']}\n\nVoir dans l’administration : $admin_url";
	$ok1 = sa_mail_envoyer(SA_DEVIS_DEST, "Nouvelle demande de devis $ref – {$f['nom']}" . ($f['societe'] ? " ({$f['societe']})" : ''), $html1, $corps, $f['nom'] . ' <' . $f['email'] . '>');
	$html2 = sa_mail_html([
		'pre' => "Nous avons bien reçu votre demande ($ref). Nous revenons vers vous rapidement.",
		'kick' => 'Demande bien reçue', 'titre' => 'Merci ' . esc_html($f['nom']) . ' !', 'intro' => 'Nous avons bien reçu votre demande de devis. Notre équipe l’étudie et revient vers vous rapidement avec une proposition détaillée.<br><br><span style="display:inline-block;border:2px solid #ffd900;color:#ffd900;font-weight:700;padding:6px 12px;border-radius:3px;letter-spacing:.04em">Référence ' . esc_html($ref) . '</span>',
		'blocs' => [['Votre événement', $infos]],
		'machines' => $machines, 'message' => $f['message'],
		'etapes' => ['Nous étudions votre demande et vérifions la disponibilité des machines.', 'Vous recevez votre devis détaillé, livraison et installation comprises.', 'Si vous personnalisez, nous vous envoyons les gabarits : visuels à fournir au plus tard 10 jours avant l’événement.'],
		'boutons' => [['Nous appeler : 06 29 72 90 92', 'tel:+33629729092', true], ['Voir nos réalisations', home_url('/realisations/'), false]],
		'pied' => 'Une question ? Répondez simplement à cet e-mail ou écrivez à <a href="mailto:contact@stationarcade.fr" style="color:#00e1d4">contact@stationarcade.fr</a>.',
	]);
	$conf = "Bonjour {$f['nom']},\n\nNous avons bien reçu votre demande de devis (référence $ref).\n" .
		"Nous revenons vers vous rapidement avec une proposition détaillée.\n\nVotre sélection :\n$lignes\n\n" .
		"Pour toute question : 06 29 72 90 92 ou contact@stationarcade.fr\n\nL’équipe Station Arcade";
	$ok2 = sa_mail_envoyer($f['email'], "Votre demande de devis Station Arcade ($ref)", $html2, $conf, 'Station Arcade <' . SA_DEVIS_DEST . '>');
	update_post_meta($id, '_sa_mail_entreprise', $ok1 ? 1 : 0);
	update_post_meta($id, '_sa_mail_client', $ok2 ? 1 : 0);
	return new WP_REST_Response(['ok' => true, 'ref' => $ref, 'mail' => (bool) $ok1], 201);
}

// Administration : détail, statut, colonnes, alerte d'envoi
add_action('add_meta_boxes', function () {
	add_meta_box('sa_devis_detail', 'Détail de la demande', function ($post) {
		$g = function ($k) use ($post) { return esc_html(get_post_meta($post->ID, '_sa_' . $k, true)); };
		echo '<table class="widefat striped"><tbody>';
		foreach (['ref' => 'Référence', 'nom' => 'Nom', 'societe' => 'Société', 'email' => 'E-mail', 'tel' => 'Téléphone', 'date' => 'Début', 'date_fin' => 'Fin', 'lieu' => 'Lieu', 'perso' => 'Personnalisation', 'page' => 'Page d’origine'] as $k => $l) echo '<tr><th style="width:160px">' . $l . '</th><td>' . $g($k) . '</td></tr>';
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
	if ($col === 'sa_date_evt') { $a = get_post_meta($id, '_sa_date', true); $b = get_post_meta($id, '_sa_date_fin', true); echo esc_html($a . ($b && $b !== $a ? ' → ' . $b : '')); }
}, 10, 2);


// Mise en page des e-mails (HTML compatible messageries, version texte jointe)
function sa_mail_envoyer($a, $sujet, $html, $texte, $reply) {
	$alt = function ($m) use ($texte) { $m->AltBody = $texte; };
	add_action('phpmailer_init', $alt);
	$ok = wp_mail($a, $sujet, $html, ['Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $reply]);
	remove_action('phpmailer_init', $alt);
	return $ok;
}
function sa_mail_html($o) {
	$logo = content_url('uploads/2026/11/logo-blanc.png');
	$e = 'esc_html';
	$h = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><meta name="supported-color-schemes" content="dark"><title>Station Arcade</title></head>';
	$h .= '<body style="margin:0;padding:0;background-color:#000028;background-image:linear-gradient(#000028,#000028);font-family:Poppins,Segoe UI,Helvetica,Arial,sans-serif;color:#ffffff">';
	$h .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . $e($o['pre']) . '</div>';
	$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#000028;background-image:linear-gradient(#000028,#000028)"><tr><td align="center" style="padding:24px 12px">';
	$h .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#000142;background-image:linear-gradient(#000142,#000142);border-radius:6px;overflow:hidden;border:1px solid #1d1f5c">';
	$h .= '<tr><td style="background-color:#000131;background-image:linear-gradient(#000131,#000131);padding:22px 28px;border-bottom:4px solid #00e1d4"><img src="' . esc_url($logo) . '" alt="Station Arcade" width="150" style="display:block;width:150px;height:auto;border:0"></td></tr>';
	$h .= '<tr><td style="padding:28px 28px 8px"><p style="margin:0 0 6px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#00e1d4">' . $e($o['kick']) . '</p>';
	$h .= '<h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#ffffff">' . $o['titre'] . '</h1><p style="margin:0;font-size:15px;line-height:1.6;color:#c9cbe6">' . $o['intro'] . '</p></td></tr>';
	if (!empty($o['boutons'])) {
		$h .= '<tr><td style="padding:18px 28px 6px">';
		foreach ($o['boutons'] as $b) $h .= '<a href="' . esc_url($b[1], ['http', 'https', 'mailto', 'tel']) . '" style="display:inline-block;margin:0 8px 10px 0;padding:12px 18px;border-radius:3px;font-weight:700;font-size:14px;text-decoration:none;' . ($b[2] ? 'border:2px solid #ffd900;color:#ffd900' : 'border:2px solid #ffffff;color:#ffffff') . '">' . $e($b[0]) . '</a>';
		$h .= '</td></tr>';
	}
	foreach ($o['blocs'] as $bl) {
		$h .= '<tr><td style="padding:16px 28px 0"><p style="margin:0 0 8px;font-size:16px;font-weight:700;color:#ffffff">' . $e($bl[0]) . '</p><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
		foreach ($bl[1] as $r) $h .= '<tr><td style="padding:8px 0;border-bottom:1px solid #23265e;font-size:13px;color:#a7a9cc;width:38%">' . $e($r[0]) . '</td><td style="padding:8px 0;border-bottom:1px solid #23265e;font-size:14px;color:#ffffff;font-weight:600">' . $e($r[1]) . '</td></tr>';
		$h .= '</table></td></tr>';
	}
	$h .= '<tr><td style="padding:22px 28px 0"><p style="margin:0 0 8px;font-size:16px;font-weight:700;color:#ffffff">Machines demandées</p>';
	if ($o['machines']) {
		$h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
		foreach ($o['machines'] as $m) {
			$img = $m['img'] ? '<img src="' . esc_url($m['img']) . '" alt="" width="56" height="56" style="display:block;width:56px;height:56px;border-radius:4px;object-fit:cover;border:0">' : '';
			$nom = $m['url'] ? '<a href="' . esc_url($m['url']) . '" style="color:#ffffff;text-decoration:none">' . $e($m['nom']) . '</a>' : $e($m['nom']);
			$h .= '<tr><td width="66" style="padding:6px 0">' . $img . '</td><td style="padding:6px 0;font-size:14px;font-weight:600;color:#ffffff">' . $nom . '</td><td align="right" style="padding:6px 0;font-size:14px;color:#ffd900;font-weight:700;white-space:nowrap">× ' . (int) $m['q'] . '</td></tr>';
		}
		$h .= '</table>';
	} else $h .= '<p style="margin:0;font-size:14px;color:#c9cbe6">Aucune machine choisie : conseil souhaité.</p>';
	$h .= '</td></tr>';
	if ($o['message'] !== '') $h .= '<tr><td style="padding:20px 28px 0"><p style="margin:0 0 8px;font-size:16px;font-weight:700;color:#ffffff">Message</p><div style="background-color:#000131;background-image:linear-gradient(#000131,#000131);border-left:4px solid #00e1d4;padding:12px 14px;font-size:14px;line-height:1.6;color:#ffffff">' . nl2br($e($o['message'])) . '</div></td></tr>';
	if (!empty($o['etapes'])) {
		$h .= '<tr><td style="padding:22px 28px 0"><p style="margin:0 0 10px;font-size:16px;font-weight:700;color:#ffffff">La suite</p>';
		foreach ($o['etapes'] as $i => $s) $h .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:8px"><tr><td valign="top" style="width:30px"><span style="display:inline-block;width:22px;height:22px;line-height:22px;border-radius:50%;background:#00e1d4;color:#000028;font-size:12px;font-weight:700;text-align:center">' . ($i + 1) . '</span></td><td style="font-size:14px;line-height:1.5;color:#c9cbe6">' . $e($s) . '</td></tr></table>';
		$h .= '</td></tr>';
	}
	$h .= '<tr><td style="padding:24px 28px 26px;font-size:13px;line-height:1.6;color:#a7a9cc">' . $o['pied'] . '</td></tr>';
	$h .= '<tr><td style="background-color:#000028;background-image:linear-gradient(#000028,#000028);padding:18px 28px;font-size:12px;line-height:1.6;color:#c9cbe6">Station Arcade · Location et personnalisation de jeux d’arcade<br><a href="tel:+33629729092" style="color:#ffffff;text-decoration:none">06 29 72 90 92</a> · <a href="mailto:contact@stationarcade.fr" style="color:#ffffff;text-decoration:none">contact@stationarcade.fr</a> · <a href="https://www.instagram.com/stationarcade.fr/" style="color:#00e1d4;text-decoration:none">Instagram</a></td></tr>';
	$h .= '</table></td></tr></table></body></html>';
	return $h;
}
