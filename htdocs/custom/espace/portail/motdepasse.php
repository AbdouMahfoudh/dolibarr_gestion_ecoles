<?php
/**
 * Changement du mot de passe dans l'espace : obligatoire à la première connexion (mot de passe provisoire
 * donné par l'école), possible ensuite à tout moment. Le mot de passe provisoire gardé pour la fiche est effacé.
 *
 * Fichier : custom/espace/portail/motdepasse.php
 */

require 'boot.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

$acces = espace_exiger_session($db, true);
$langs = espace_langs_init(espace_langue_code($db, $acces));
$provisoire = (int) $acces->mdp_provisoire;

$erreur = '';
if (GETPOST('action', 'aZ09') === 'save') {
	$actuel = GETPOST('actuel', 'password');
	$nouveau = GETPOST('nouveau', 'password');
	$confirm = GETPOST('confirm', 'password');
	$u = new User($db);
	$u->fetch((int) $acces->fk_user);
	$ok = checkLoginPassEntity($u->login, $actuel, $conf->entity, array('dolibarr'));
	unset($_SESSION['dol_loginmesg']);
	if ($ok === '' || dol_strtoupper($ok) !== dol_strtoupper($u->login)) {
		sleep(1);
		$erreur = $langs->trans('ErreurMotDePasseActuel');
	} elseif (dol_strlen($nouveau) < 6) {
		$erreur = $langs->trans('ErreurMotDePasseCourt', 6);
	} elseif ($nouveau !== $confirm) {
		$erreur = $langs->trans('ErreurMotDePasseConfirm');
	} elseif ($nouveau === $actuel) {
		$erreur = $langs->trans('ErreurMotDePasseIdentique');
	} else {
		$db->begin();
		if (espace_user_mdp($db, (int) $u->id, $nouveau) < 0) {
			$db->rollback();
			$erreur = $langs->trans('Error');
		} else {
			$db->query("UPDATE ".$db->prefix()."ecole_acces SET mdp_provisoire = 0, mdp_fiche = NULL WHERE rowid = ".((int) $acces->rowid));
			$db->commit();
			$_SESSION['ecole_espace_msg'] = $langs->trans('MotDePasseChange');
			header('Location: '.espace_page_url(''));
			exit;
		}
	}
}

espace_header($langs->trans('ChangerMotDePasse'), $acces, $provisoire ? '' : espace_page_url(''));

print '<div class="es-login">';
print '<h1><i class="fas fa-key"></i> '.$langs->trans('ChangerMotDePasse').'</h1>';
if ($provisoire) {
	print espace_msg($langs->trans('AideMotDePasseProvisoire'), 'info');
}
if ($erreur !== '') {
	print espace_msg(dol_escape_htmltag($erreur), 'error');
}
print '<form method="POST" action="'.dol_escape_htmltag(espace_page_url('mot-de-passe')).'" class="es-form">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save">';
$champs = array(
	'actuel' => array($provisoire ? 'MotDePasseProvisoire' : 'MotDePasseActuel', 'current-password'),
	'nouveau' => array('NouveauMotDePasse', 'new-password'),
	'confirm' => array('ConfirmerMotDePasse', 'new-password'),
);
foreach ($champs as $name => $c) {
	print '<label for="'.$name.'">'.$langs->trans($c[0]).'</label>';
	print '<div class="es-pass"><input type="password" id="'.$name.'" name="'.$name.'" dir="ltr" autocomplete="'.$c[1].'" required'.($name !== 'actuel' ? ' minlength="6"' : '').'>';
	print '<button type="button" class="es-eye" data-for="'.$name.'" aria-label="'.dol_escape_htmltag($langs->trans('AfficherMotDePasse')).'"><i class="fas fa-eye"></i></button></div>';
}
print '<p class="es-muted es-small">'.$langs->trans('AideNouveauMotDePasse', 6).'</p>';
print '<button type="submit" class="es-btn es-btn-primary es-btn-block">'.$langs->trans('Enregistrer').'</button>';
print '</form></div>';
print '<script>document.querySelectorAll(".es-eye").forEach(function(b){b.addEventListener("click",function(){var i=document.getElementById(b.dataset.for);i.type=i.type==="password"?"text":"password";});});</script>';

espace_footer();
$db->close();
