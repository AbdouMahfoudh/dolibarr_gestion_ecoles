<?php
/**
 * Appel des élèves par le surveillant dans son espace (téléphone), toutes les classes.
 *  - sans classe : tableau du jour, chaque classe avec ses créneaux de cours « fait / à faire » ;
 *  - une classe et un créneau : tous présents par défaut, on touche Absent / Retard (heure facultative) / Renvoyé,
 *    on indique si l'enseignant est présent, absent ou en retard, puis on valide.
 * Un appel se corrige le jour même (ensuite, correction dans l'interface de gestion). Après l'appel :
 * boutons WhatsApp pour prévenir les parents (si la direction ne les a pas bloqués sur la fiche du surveillant). Mêmes règles et mêmes données que la page d'appel du module Élèves.
 *
 *   appel[/<AAAA-MM-JJ>[/<classe>[/<créneau>]]]
 *
 * Fichier : custom/personnel/portail/appel.php
 */

require_once __DIR__.'/init_espace.php';
dol_include_once('/classes/class/ecole_classe.class.php');
dol_include_once('/eleves/core/lib/discipline.lib.php');

list($acces, $emp, $moi, $rubriques) = pe_session($db, 'appel');
$aujourdhui = personnel_aujourdhui();
$date = personnel_date_ok(pe_arg(0));
if ($date === '' || $date > $aujourdhui) {
	$date = $aujourdhui;
}

// Classes actives qui ont des élèves inscrits
$classes = array();
$sql = "SELECT c.rowid, c.ref, c.label_fr, c.label_ar FROM ".$db->prefix()."ecole_classe c LEFT JOIN ".$db->prefix()."ecole_niveau n ON n.rowid = c.fk_niveau";
$sql .= " WHERE c.entity IN (".getEntity('ecole_classe').") AND c.status = 1 AND EXISTS (SELECT 1 FROM ".$db->prefix()."ecole_eleve e WHERE e.fk_classe = c.rowid AND e.status IN (".implode(',', EcoleEleve::statusOccupantPlace())."))";
$sql .= " ORDER BY n.position, c.rowid";
$resql = $db->query($sql);
while ($resql && ($o = $db->fetch_object($resql))) {
	$classes[(int) $o->rowid] = $o;
}
$fk_classe = pe_arg(1) !== '' ? espace_id_par_jeton('classe', pe_arg(1), array_keys($classes)) : 0;
$creneaux = $fk_classe ? eleves_appel_creneaux($db, $fk_classe, $date) : array();
$fk_creneau = ($fk_classe && pe_arg(2) !== '') ? espace_id_par_jeton('creneau', pe_arg(2), array_keys($creneaux)) : 0;
if ($fk_classe && !$fk_creneau && !empty($creneaux)) {
	$fk_creneau = ($date === $aujourdhui) ? eleves_creneau_courant($creneaux) : (int) key($creneaux);
}
$urlJour = espace_page_url('appel/'.$date);
$urlAppel = ($fk_classe && $fk_creneau) ? espace_page_url('appel/'.$date.'/'.espace_jeton('classe', $fk_classe).'/'.espace_jeton('creneau', $fk_creneau)) : $urlJour;
$appel = ($fk_classe && $fk_creneau) ? eleves_appel_fetch($db, $fk_classe, $date, $fk_creneau) : null;
$editable = ($fk_classe && $fk_creneau && isset($creneaux[$fk_creneau])) && (!$appel || $date === $aujourdhui);

/*
 * Actions
 */
if (GETPOST('action', 'aZ09') === 'save' && $editable) {
	$abs = GETPOST('abs', 'array');
	$ret = GETPOST('ret', 'array');
	$ren = GETPOST('ren', 'array');
	$heures = GETPOST('heure', 'array');
	$etats = array();
	foreach (eleves_appel_eleves($db, $fk_classe, $date) as $eid => $e) {
		if ($e->etat_special !== '') {
			continue;
		}
		$k = espace_jeton('eleve', $eid);
		$type = !empty($abs[$k]) ? ELEVES_ABSENT : (!empty($ret[$k]) ? ELEVES_RETARD : (!empty($ren[$k]) ? ELEVES_RENVOYE : ELEVES_PRESENT));
		$etats[$eid] = array('type' => $type, 'heure' => isset($heures[$k]) ? trim((string) $heures[$k]) : '');
	}
	$error = '';
	$id = eleves_appel_enregistrer($db, $moi, $fk_classe, $date, $fk_creneau, $etats, $error, (int) $creneaux[$fk_creneau]['fk_matiere']);
	if ($id > 0) {
		$errprof = '';
		if (personnel_appel_enregistrer($db, $moi, $date, $fk_classe, $fk_creneau, $errprof) < 0) {
			pe_flash($errprof, false);
		}
		$nb = array(ELEVES_ABSENT => 0, ELEVES_RETARD => 0, ELEVES_RENVOYE => 0, ELEVES_PRESENT => 0);
		foreach ($etats as $e) {
			$nb[$e['type']]++;
		}
		pe_flash($langs->transnoentities($appel ? 'AppelCorrige' : 'AppelEnregistre', $nb[ELEVES_ABSENT], $nb[ELEVES_RETARD], $nb[ELEVES_RENVOYE]));
	} else {
		pe_flash($error, false);
	}
	header('Location: '.$urlAppel.'#prevenir');
	exit;
}

/*
 * Affichage
 */
pe_header($langs->trans('EspAppel'), $acces, $emp, $rubriques, 'appel', $fk_classe ? $urlJour : '');

$veille = date('Y-m-d', strtotime($date.' -1 day'));
$lendemain = date('Y-m-d', strtotime($date.' +1 day'));
$suffixe = $fk_classe ? '/'.espace_jeton('classe', $fk_classe) : '';
print '<div class="es-daynav"><a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('appel/'.$veille.$suffixe)).'"><i class="fas fa-chevron-'.(espace_rtl() ? 'right' : 'left').'"></i></a>';
print '<b>'.dol_escape_htmltag(personnel_date_label($date)).'</b>';
if ($date < $aujourdhui) {
	print '<a class="es-btn es-btn-light es-btn-sm" href="'.dol_escape_htmltag(espace_page_url('appel/'.$lendemain.$suffixe)).'"><i class="fas fa-chevron-'.(espace_rtl() ? 'left' : 'right').'"></i></a>';
} else {
	print '<span class="es-btn es-btn-sm" style="visibility:hidden"><i class="fas fa-chevron-right"></i></span>';
}
print '</div>';

if (!$fk_classe) {
	// Tableau du jour
	$appels = eleves_appels_du_jour($db, $date);
	$tous = ecole_creneaux_actifs($db);
	$courant = ($date === $aujourdhui) ? eleves_creneau_courant($tous) : 0;
	if (!in_array((int) date('N', strtotime($date)), ecole_jours_ouvrables(), true)) {
		print espace_msg($langs->trans('JourNonOuvrable'), 'info');
	}
	print '<div class="es-list">';
	foreach ($classes as $cid => $c) {
		$cr = eleves_appel_creneaux($db, $cid, $date);
		if (empty($cr)) {
			continue;
		}
		print '<div class="es-card"><div class="es-item-top"><b>'.dol_escape_htmltag($c->ref.' · '.ecole_label($c)).'</b></div><div class="es-chips">';
		foreach ($cr as $crid => $info) {
			$fait = isset($appels[$cid.'|'.$crid]);
			$url = espace_page_url('appel/'.$date.'/'.espace_jeton('classe', $cid).'/'.espace_jeton('creneau', $crid));
			$txt = $info['creneau']->ref.' '.$info['creneau']->heure_debut;
			if ($fait) {
				$a = $appels[$cid.'|'.$crid];
				$txt .= ' · '.$langs->transnoentities('NbAbsNbRet', (int) $a->nb_absents, (int) $a->nb_retards);
			}
			print '<a class="es-chip '.($fait ? 'es-chip-green' : ($crid === $courant ? 'es-chip-orange' : '')).'" href="'.dol_escape_htmltag($url).'">'.($fait ? '<i class="fas fa-check"></i> ' : '').'<span dir="ltr">'.dol_escape_htmltag($txt).'</span></a>';
		}
		print '</div></div>';
	}
	print '</div>';
	print '<p class="es-muted es-small">'.$langs->trans('AideTableauDuJour').'</p>';
	espace_footer();
	$db->close();
	exit;
}

$classe = $classes[$fk_classe];
print '<section class="es-card"><h2>'.dol_escape_htmltag($classe->ref.' · '.ecole_label($classe)).'</h2><div class="es-chips">';
foreach ($creneaux as $crid => $info) {
	$fait = (bool) eleves_appel_fetch($db, $fk_classe, $date, $crid);
	print '<a class="es-chip '.($crid === $fk_creneau ? 'es-chip-blue' : ($fait ? 'es-chip-green' : '')).'" href="'.dol_escape_htmltag(espace_page_url('appel/'.$date.'/'.espace_jeton('classe', $fk_classe).'/'.espace_jeton('creneau', $crid))).'">'.($fait ? '<i class="fas fa-check"></i> ' : '').'<span dir="ltr">'.dol_escape_htmltag($info['creneau']->ref.' '.$info['creneau']->heure_debut).'</span></a>';
}
print '</div>';
if (!$fk_creneau || !isset($creneaux[$fk_creneau])) {
	print espace_msg($langs->trans('PasDeCoursClasseJour'), 'info').'</section>';
	espace_footer();
	$db->close();
	exit;
}
$info = $creneaux[$fk_creneau];
print '<div class="es-line"><span class="es-muted">'.$langs->trans('Creneau').'</span><span dir="ltr">'.dol_escape_htmltag(eleves_creneau_label($info['creneau'])).'</span></div>';
if ($info['matiere'] !== '') {
	print '<div class="es-line"><span class="es-muted">'.$langs->trans('Matiere').'</span><span>'.dol_escape_htmltag($info['matiere']).'</span></div>';
}
if ($appel) {
	print '<div class="es-line"><span class="es-muted">'.$langs->trans('EtatAppel').'</span><span class="es-chip es-chip-green">'.$langs->trans('AppelFait').'</span></div>';
	print '<div class="es-muted es-small">'.$langs->trans('EspAppelFaitPar', dol_escape_htmltag(ecole_user_label($db, $appel->fk_user_creat))).'</div>';
}
print '</section>';

$eleves = eleves_appel_eleves($db, $fk_classe, $date);
$lignes = $appel ? eleves_appel_lignes($db, (int) $appel->rowid) : array();

if ($editable) {
	print '<form method="POST" action="'.dol_escape_htmltag($urlAppel).'" id="formappel">';
	print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
} elseif ($appel) {
	print espace_msg($langs->trans('EspAppelLectureSeule'), 'info');
}

// Enseignant : présent / absent / en retard
$edt = personnel_cours_edt($db, $date, $fk_creneau, $fk_classe);
$rec = personnel_cours_fetch($db, $date, $fk_creneau, $fk_classe);
$fk_prof = $rec && (int) $rec->fk_employe > 0 ? (int) $rec->fk_employe : ($edt ? (int) $edt->fk_employe : 0);
if ($fk_prof > 0) {
	$absprof = personnel_absence_couvre(personnel_absences_periode($db, $date, $date, $fk_prof), $date, $edt ? $edt->heure_debut : '');
	$e = $rec ? (int) $rec->etat : ($absprof ? PERSONNEL_ABSENT : PERSONNEL_PRESENT);
	print '<section class="es-card"><h2><i class="fas fa-chalkboard-teacher"></i> '.$langs->trans('Enseignant').' : '.dol_escape_htmltag(personnel_employe_nom($db, $fk_prof)).'</h2>';
	if ($editable) {
		print '<div class="es-toggles">';
		foreach (array(PERSONNEL_PRESENT => array('Present', ''), PERSONNEL_ABSENT => array('Absent', 'es-tg-abs'), PERSONNEL_RETARD => array('EnRetard', 'es-tg-ret')) as $v => $l) {
			print '<label class="es-toggle '.$l[1].'"><input type="radio" name="prof_etat" value="'.$v.'"'.($e === $v ? ' checked' : '').'> '.$langs->trans($l[0]).'</label>';
		}
		print '</div>';
		$min = ($rec && (int) $rec->etat === PERSONNEL_RETARD) ? (int) $rec->minutes_retard : '';
		print '<div class="es-prof-retard"'.($e === PERSONNEL_RETARD ? '' : ' style="display:none"').'><input type="number" name="prof_retard" min="1" max="600" inputmode="numeric" value="'.dol_escape_htmltag((string) $min).'" placeholder="'.dol_escape_htmltag($langs->trans('Minutes')).'"> '.$langs->trans('Minutes').'</div>';
	} else {
		$etat = personnel_etat_cours($rec, $absprof, false, false);
		print pe_etat_chip($etat, ($etat === 'retard' && $rec) ? '('.$langs->trans('NbMinutes', (int) $rec->minutes_retard).')' : '');
	}
	print '</section>';
}

// Élèves
print '<div class="es-card es-appel">';
foreach ($eleves as $eid => $el) {
	$k = espace_jeton('eleve', $eid);
	$l = isset($lignes[$eid]) ? $lignes[$eid] : null;
	$type = $l ? (int) $l->type : ELEVES_PRESENT;
	print '<div class="es-appel-row" data-row="1"><div class="es-appel-nom"><b dir="ltr">'.($el->numero_appel ? (int) $el->numero_appel.'.' : '').'</b> '.dol_escape_htmltag(ecole_label($el)).'</div>';
	if ($el->etat_special !== '') {
		print '<span class="es-chip es-chip-purple">'.$langs->trans($el->etat_special === 'exclu' ? 'ExcluTemporairement' : 'StatutSuspendu').'</span>';
	} elseif ($editable) {
		$heure = ($l && $l->heure_arrivee) ? $l->heure_arrivee : '';
		print '<div class="es-toggles es-toggles-sm">';
		print '<label class="es-toggle es-tg-abs"><input type="checkbox" name="abs['.$k.']" value="1"'.($type === ELEVES_ABSENT ? ' checked' : '').'> '.$langs->trans('Absent').'</label>';
		print '<label class="es-toggle es-tg-ret"><input type="checkbox" name="ret['.$k.']" value="1"'.($type === ELEVES_RETARD ? ' checked' : '').'> '.$langs->trans('Retard').'</label>';
		print '<label class="es-toggle es-tg-ren"><input type="checkbox" name="ren['.$k.']" value="1"'.($type === ELEVES_RENVOYE ? ' checked' : '').'> '.$langs->trans('Renvoye').'</label>';
		print '<input type="time" name="heure['.$k.']" class="es-heure" value="'.dol_escape_htmltag($heure).'"'.($type === ELEVES_RETARD ? '' : ' style="display:none"').'>';
		print '</div>';
	} else {
		if ($l) {
			$css = array(ELEVES_ABSENT => 'es-chip-red', ELEVES_RETARD => 'es-chip-orange', ELEVES_RENVOYE => 'es-chip-purple');
			print '<span class="es-chip '.(isset($css[$type]) ? $css[$type] : '').'">'.dol_escape_htmltag(eleves_presence_label(eleves_presence_code($l->type, (string) $l->heure_arrivee))).'</span>';
		} else {
			print '<span class="es-chip es-chip-green">'.$langs->trans('Present').'</span>';
		}
	}
	print '</div>';
}
if (empty($eleves)) {
	print '<div class="es-muted">'.$langs->trans('AucunEleveClasseDate').'</div>';
}
print '</div>';

if ($editable) {
	print '<button type="submit" class="es-btn es-btn-primary es-btn-block es-sticky-save" id="appel-submit"><i class="fas fa-check"></i> <span id="appel-libelle">'.$langs->trans('ValiderAppel').'</span></button></form>';
	$libValider = dol_escape_js($langs->transnoentities('ValiderAppel'), 2);
	$libValiderNb = dol_escape_js($langs->transnoentities('ValiderAppelNb', '%s', '%s', '%s'), 2);
	print '<script>
(function () {
	var form = document.getElementById("formappel");
	function maj() {
		var a = 0, r = 0, x = 0;
		form.querySelectorAll("[data-row]").forEach(function (row) {
			var abs = row.querySelector("input[name^=abs]"), ret = row.querySelector("input[name^=ret]"), ren = row.querySelector("input[name^=ren]"), h = row.querySelector(".es-heure");
			if (!abs) { return; }
			[abs, ret, ren].forEach(function (i) { i.parentNode.classList.toggle("on", i.checked); });
			h.style.display = ret.checked ? "" : "none";
			a += abs.checked ? 1 : 0; r += ret.checked ? 1 : 0; x += ren.checked ? 1 : 0;
		});
		form.querySelectorAll("input[name=prof_etat]").forEach(function (i) { i.parentNode.classList.toggle("on", i.checked); });
		var pe = form.querySelector("input[name=prof_etat]:checked"), pr = form.querySelector(".es-prof-retard");
		if (pr) { pr.style.display = (pe && pe.value === "'.PERSONNEL_RETARD.'") ? "" : "none"; }
		document.getElementById("appel-libelle").textContent = (a + r + x) ? "'.$libValiderNb.'".replace("%s", a).replace("%s", r).replace("%s", x) : "'.$libValider.'";
	}
	form.addEventListener("change", function (ev) {
		var t = ev.target, row = t.closest("[data-row]");
		if (t.checked && row && t.type === "checkbox") {
			["abs", "ret", "ren"].forEach(function (k) {
				if (t.name.indexOf(k) !== 0) { row.querySelector("input[name^=" + k + "]").checked = false; }
			});
			if (t.name.indexOf("ret") === 0) {
				var h = row.querySelector(".es-heure");
				if (!h.value) { var d = new Date(); h.value = ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2); }
			}
		}
		maj();
	});
	maj();
})();
</script>';
}

// Prévenir les parents (boutons WhatsApp, bloquables sur la fiche du surveillant)
if ($appel && !empty($lignes) && personnel_whatsapp_autorise($emp)) {
	print '<a id="prevenir"></a><h2 class="es-h2"><i class="fas fa-bell"></i> '.$langs->trans('PrevenirParents').'</h2><div class="es-list">';
	foreach ($eleves as $eid => $el) {
		if (!isset($lignes[$eid])) {
			continue;
		}
		$l = $lignes[$eid];
		$wa = eleves_whatsapp_url($db, $eid, $date, (int) $l->type);
		print '<div class="es-card es-item"><div class="es-item-main"><b>'.dol_escape_htmltag(ecole_label($el)).'</b><span class="es-muted es-small">'.dol_escape_htmltag(eleves_presence_label(eleves_presence_code($l->type, (string) $l->heure_arrivee))).'</span></div>';
		print '<div class="es-item-side">'.($wa !== '' ? '<a class="es-btn es-btn-sm es-btn-wa" href="'.dol_escape_htmltag($wa).'" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> WhatsApp</a>' : '<span class="es-muted es-small">'.$langs->trans('EspPasDeNumero').'</span>').'</div></div>';
	}
	print '</div>';
}

espace_footer();
$db->close();
