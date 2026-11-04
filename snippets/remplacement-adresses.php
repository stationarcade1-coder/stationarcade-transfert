<?php
// Mise en ligne : remplace preprod.stationarcade.fr par stationarcade.fr (données sérialisées respectées). Usage unique.
if (get_option('sa_sr_done')) return;
global $wpdb;
$de = 'preprod.stationarcade.fr'; $vers = 'stationarcade.fr';
$rec = function ($v) use (&$rec, $de, $vers) {
	if (is_string($v)) {
		$u = @unserialize($v);
		if ($u !== false || $v === 'b:0;') return serialize($rec($u));
		return str_replace($de, $vers, $v);
	}
	if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $rec($x); return $v; }
	if (is_object($v) && !($v instanceof __PHP_Incomplete_Class)) { foreach (get_object_vars($v) as $k => $x) $v->$k = $rec($x); return $v; }
	return $v;
};
$rapport = [];
$cibles = [
	[$wpdb->posts, 'ID', ['post_content', 'post_excerpt', 'guid']],
	[$wpdb->postmeta, 'meta_id', ['meta_value']],
	[$wpdb->options, 'option_id', ['option_value']],
	[$wpdb->termmeta, 'meta_id', ['meta_value']],
	[$wpdb->usermeta, 'umeta_id', ['meta_value']],
	[$wpdb->prefix . 'snippets', 'id', ['code']],
];
foreach ($cibles as [$t, $pk, $cols]) {
	foreach ($cols as $c) {
		$rows = $wpdb->get_results($wpdb->prepare("SELECT `$pk` AS id, `$c` AS v FROM `$t` WHERE `$c` LIKE %s", '%' . $wpdb->esc_like($de) . '%'));
		$n = 0;
		foreach ($rows as $r) {
			$nv = $rec($r->v);
			if ($nv !== $r->v) { $wpdb->update($t, [$c => $nv], [$pk => $r->id]); $n++; }
		}
		$rapport["$t.$c"] = $n;
	}
}
// Tables Yoast : vidées, elles se reconstruisent avec les bonnes adresses
foreach (['yoast_indexable', 'yoast_indexable_hierarchy', 'yoast_seo_links'] as $y) {
	$wpdb->query("TRUNCATE TABLE `{$wpdb->prefix}$y`");
}
update_option('sa_sr_done', 1, false);
update_option('sa_sr_rapport', $rapport, false);
wp_cache_flush();
