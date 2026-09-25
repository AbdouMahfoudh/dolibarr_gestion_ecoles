<?php
/**
 * Connexion à l'espace parents et élèves (identifiant : code du responsable ou matricule) ou, à l'adresse
 * de l'espace du personnel, connexion des employés (identifiant : matricule employé ou login). Chaque page
 * n'accepte que les accès de sa zone : un employé est refusé chez les parents, un parent ou un élève chez le personnel.
 * Mot de passe oublié : l'école le réinitialise (aucun envoi automatique).
 *
 * Fichier : custom/espace/portail/login.php
 */

require 'boot.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

$langs = espace_langs_init(espace_langue_code($db));

if (espace_session_acces($db)) {
	header('Location: '.espace_page_url(''));
	exit;
}

$zonePersonnel = (espace_zone() === ESPACE_ZONE_PERSONNEL);
if ($zonePersonnel && (!isModEnabled('personnel') || !espace_table_existe($db, 'ecole_employe'))) {
	http_response_code(404);
	print 'Not available';
	exit;
}
$erreur = '';
$identifiant = dol_strtoupper(trim(GETPOST('identifiant', 'alphanohtml')));
if (GETPOST('action', 'aZ09') === 'login') {
	$mdp = GETPOST('motdepasse', 'password');
	$acces = null;
	$loginok = '';
	if ($identifiant !== '' && $mdp !== '') {
		$sql = "SELECT a.rowid, u.login FROM ".$db->prefix()."ecole_acces a INNER JOIN ".$db->prefix()."user u ON u.rowid = a.fk_user";
		$sql .= " WHERE a.entity IN (".getEntity('ecole_acces').")";
		if ($zonePersonnel) {
			$sql .= " AND a.type = '".ESPACE_EMPLOYE."' AND (u.login = '".$db->escape($identifiant)."'";
			$sql .= " OR a.fk_cible IN (SELECT rowid FROM ".$db->prefix()."ecole_employe WHERE ref = '".$db->escape($identifiant)."'))";
		} else {
			$sql .= " AND a.type <> '".ESPACE_EMPLOYE."' AND u.login = '".$db->escape($identifiant)."'";
		}
		$resql = $db->query($sql);
		$login = '';
		if ($resql && ($o = $db->fetch_object($resql))) {
			$acces = espace_acces_fetch_id($db, (int) $o->rowid);
			$login = (string) $o->login;
		}
		if ($acces) {
			$loginok = checkLoginPassEntity($login, $mdp, $conf->entity, array('dolibarr'));
		}
		unset($_SESSION['dol_loginmesg']);
	}
	if (!$acces || $loginok === '' || $loginok === '--bad-login-validity--' || dol_strtoupper($loginok) !== dol_strtoupper($login)) {
		sleep(1); // ralentit les essais de mots de passe
		$erreur = $langs->trans('ErreurIdentifiants');
	} elseif (espace_etat($db, $acces) !== 'actif') {
		$erreur = $langs->trans('AccesPlusActif');
	} else {
		espace_session_ouvrir($db, $acces);
		header('Location: '.espace_page_url((int) $acces->mdp_provisoire ? 'mot-de-passe' : ''));
		exit;
	}
}

espace_header($langs->trans('Connexion'));

print '<div class="es-login">';
$logo = espace_logo_url();
if ($logo !== '') {
	print '<img class="es-login-logo" src="'.dol_escape_htmltag($logo).'" alt="">';
}
print '<h1>'.$langs->trans($zonePersonnel ? 'TitreEspacePersonnel' : 'TitreEspace').'</h1>';
print '<p class="es-muted">'.$langs->trans($zonePersonnel ? 'AideConnexionPersonnel' : 'AideConnexion').'</p>';

if (GETPOSTINT('refus')) {
	print espace_msg($langs->trans('AccesPlusActif'), 'warn');
}
if (GETPOSTINT('deconnecte')) {
	print espace_msg($langs->trans('VousEtesDeconnecte'), 'ok');
}
if ($erreur !== '') {
	print espace_msg(dol_escape_htmltag($erreur), 'error');
}

print '<form method="POST" action="'.dol_escape_htmltag(espace_page_url('connexion')).'" class="es-form" autocomplete="on">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="login">';
print '<label for="identifiant">'.$langs->trans('Identifiant').'</label>';
print '<input type="text" id="identifiant" name="identifiant" dir="ltr" autocapitalize="characters" autocorrect="off" spellcheck="false" autocomplete="username" required value="'.dol_escape_htmltag($identifiant).'" placeholder="'.($zonePersonnel ? 'P00001' : 'R00001 / E00001').'">';
print '<label for="motdepasse">'.$langs->trans('MotDePasse').'</label>';
print '<div class="es-pass"><input type="password" id="motdepasse" name="motdepasse" dir="ltr" autocomplete="current-password" required>';
print '<button type="button" class="es-eye" data-for="motdepasse" aria-label="'.dol_escape_htmltag($langs->trans('AfficherMotDePasse')).'"><i class="fas fa-eye"></i></button></div>';
print '<button type="submit" class="es-btn es-btn-primary es-btn-block">'.$langs->trans('SeConnecter').'</button>';
print '</form>';

print '<p class="es-muted es-small es-center"><i class="fas fa-question-circle"></i> '.$langs->trans('MotDePasseOublieAide').'</p>';
print '</div>';
print '<script>document.querySelectorAll(".es-eye").forEach(function(b){b.addEventListener("click",function(){var i=document.getElementById(b.dataset.for);i.type=i.type==="password"?"text":"password";});});</script>';

espace_footer();
$db->close();
