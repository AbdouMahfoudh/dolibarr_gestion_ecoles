<?php
/**
 * Données installées par défaut à l'activation des modules (première installation d'une école) :
 * séries, créneaux, matières, classes (module Classes), autres frais (module Élèves), mention « P.Tr »
 * (module Notes), jours fériés fixes (module Personnel).
 *
 * Tout reste modifiable ensuite. Les listes ne sont installées que si la table est encore vide
 * (réactiver un module ne recrée pas ce que l'école a supprimé). Pas de coefficients, pas de salles,
 * pas de données de test.
 *
 * Les mêmes données servent au fichier SQL autonome : sql/donnees_par_defaut.sql
 * (régénéré par : php classes/core/lib/installation.lib.php > ../donnees_par_defaut.sql).
 *
 * Fichier : custom/classes/core/lib/installation.lib.php
 */

/**
 * Séries du lycée : [ref, français, arabe, position].
 *
 * @return array
 */
function ecole_defaut_sections()
{
	return array(
		array('C', 'Série C (mathématiques)', 'شعبة الرياضيات', 10),
		array('D', 'Série D (sciences naturelles)', 'شعبة العلوم الطبيعية', 20),
		array('A', 'Série A (lettres)', 'شعبة الآداب', 30),
	);
}

/**
 * Créneaux horaires : [ref, français, arabe, début, fin, position].
 *
 * @return array
 */
function ecole_defaut_creneaux()
{
	return array(
		array('S1', 'Séance 1', 'الحصة 1', '08:00', '10:00', 10),
		array('S2', 'Séance 2', 'الحصة 2', '10:15', '12:15', 20),
		array('S3', 'Séance 3', 'الحصة 3', '15:00', '17:00', 30),
		array('S4', 'Séance 4', 'الحصة 4', '17:00', '19:00', 40),
	);
}

/**
 * Matières (catalogue, sans coefficients) : [ref, français, arabe, langue d'enseignement].
 *
 * @return array
 */
function ecole_defaut_matieres()
{
	return array(
		array('MATH', 'Mathématiques', 'الرياضيات', 'fr'),
		array('PC', 'Physique-Chimie', 'الفيزياء والكيمياء', 'fr'),
		array('SN', 'Sciences naturelles', 'العلوم الطبيعية', 'fr'),
		array('FR', 'Français', 'اللغة الفرنسية', 'fr'),
		array('AR', 'Arabe', 'اللغة العربية', 'ar'),
		array('ANG', 'Anglais', 'اللغة الإنجليزية', 'fr'),
		array('IR', 'Instruction religieuse', 'التربية الإسلامية', 'ar'),
		array('IC', 'Instruction civique', 'التربية المدنية', 'ar'),
		array('HG', 'Histoire-Géographie', 'التاريخ والجغرافيا', 'ar'),
		array('PHILO', 'Philosophie', 'الفلسفة', 'ar'),
		array('PENS', 'Pensée islamique', 'الفكر الإسلامي', 'ar'),
		array('CORAN', 'Coran', 'القرآن الكريم', 'ar'),
		array('INFO', 'Informatique', 'المعلوماتية', 'fr'),
		array('EPS', 'Éducation physique et sportive', 'التربية البدنية والرياضية', 'fr'),
		array('DESSIN', 'Dessin', 'الرسم', 'fr'),
		array('EVEIL', 'Éveil scientifique', 'التربية العلمية', 'fr'),
		array('LECT', 'Lecture', 'القراءة', 'ar'),
		array('ECRIT', 'Écriture', 'الكتابة', 'ar'),
		array('DICT', 'Dictée', 'الإملاء', 'fr'),
		array('EXPR', 'Expression écrite', 'التعبير الكتابي', 'fr'),
		array('ACT', 'Activités pratiques', 'الأنشطة التطبيقية', 'fr'),
	);
}

/**
 * Classes : [ref, français, arabe, niveau (ref), série (ref ou '')].
 *
 * @return array
 */
function ecole_defaut_classes()
{
	$out = array(
		array('PS', 'Petite section', 'القسم الصغير', 'MAT', ''),
		array('MS', 'Moyenne section', 'القسم المتوسط', 'MAT', ''),
		array('GS', 'Grande section', 'القسم الكبير', 'MAT', ''),
	);
	$ordAr = array(1 => 'الأولى', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة', 5 => 'الخامسة', 6 => 'السادسة', 7 => 'السابعة');
	$ordFr = array(1 => '1ère', 2 => '2ème', 3 => '3ème', 4 => '4ème', 5 => '5ème', 6 => '6ème', 7 => '7ème');
	for ($i = 1; $i <= 6; $i++) {
		$out[] = array($i.'AF', $ordFr[$i].' année fondamentale', 'السنة '.$ordAr[$i].' أساسية', 'PRI', '');
	}
	for ($i = 1; $i <= 4; $i++) {
		$out[] = array($i.'AS', $ordFr[$i].' année secondaire', 'السنة '.$ordAr[$i].' إعدادية', 'COL', '');
	}
	$series = array('C' => array('C', 'ر'), 'D' => array('D', 'ع'), 'A' => array('A', 'آ'));
	for ($i = 5; $i <= 7; $i++) {
		foreach ($series as $s => $x) {
			$out[] = array($i.$s, $ordFr[$i].' année '.$x[0], 'السنة '.$ordAr[$i].' ثانوية ('.$x[1].')', 'LYC', $s);
		}
	}
	return $out;
}

/**
 * Autres frais (prix à régler dans la configuration) : [ref, français, arabe, position].
 *
 * @return array
 */
function ecole_defaut_frais()
{
	return array(
		array('UNIFORME', 'Uniforme', 'الزي المدرسي', 10),
		array('FOURNITURES', 'Fournitures scolaires', 'الأدوات المدرسية', 20),
		array('TRANSPORT', 'Transport', 'النقل', 30),
		array('CANTINE', 'Cantine', 'المطعم المدرسي', 40),
	);
}

/**
 * Engagements du responsable proposés : [ref, titre FR, titre AR, texte FR, texte AR, signature de l'élève].
 * Variables : [responsable] [lien] [eleve] [matricule] [classe] [annee] [ecole] [frais_inscription] [mensualite]
 * [jour_limite] [premier_mois] [dernier_mois]. Une ligne commençant par « - » devient une puce.
 *
 * @return array
 */
function ecole_defaut_engagements()
{
	return array(
		array('REGLEMENT', 'Engagement au règlement intérieur', 'التعهد باحترام النظام الداخلي',
			"Je soussigné(e) [responsable], [lien] de l'élève [eleve], déclare avoir reçu et pris connaissance du règlement intérieur de [ecole] et m'engage à le respecter et à le faire respecter par mon enfant, notamment :\n"
			."- le respect des horaires et l'assiduité ; toute absence doit être justifiée ;\n"
			."- le port de la tenue scolaire ;\n"
			."- le respect du personnel, des camarades et du matériel ; toute dégradation sera à la charge de la famille ;\n"
			."- l'acceptation des sanctions prévues par le règlement.",
			"أنا الموقع أدناه [responsable]، [lien] التلميذ(ة) [eleve]، أصرح بأنني استلمت النظام الداخلي لـ [ecole] واطلعت عليه، وأتعهد باحترامه وبجعل ابني (ابنتي) يحترمه، ولا سيما :\n"
			."- احترام المواعيد والمواظبة، ويجب تبرير كل غياب ؛\n"
			."- ارتداء الزي المدرسي ؛\n"
			."- احترام الطاقم والزملاء والتجهيزات، وكل إتلاف تتحمله الأسرة ؛\n"
			."- قبول العقوبات المنصوص عليها في النظام.", 1),
		array('PAIEMENT', 'Engagement de paiement', 'التعهد بالدفع',
			"Je soussigné(e) [responsable] m'engage à régler pour l'élève [eleve] (classe [classe]), année scolaire [annee] :\n"
			."- frais d'inscription : [frais_inscription] ;\n"
			."- mensualité : [mensualite], payable avant le [jour_limite] de chaque mois, de [premier_mois] à [dernier_mois].\n"
			."Je reconnais qu'en cas de retard, l'école pourra suspendre la remise des bulletins ou l'accès aux examens. Les sommes versées ne sont pas remboursables, sauf décision de la direction.",
			"أنا الموقع أدناه [responsable] أتعهد بأن أدفع عن التلميذ(ة) [eleve] (القسم [classe])، السنة الدراسية [annee] :\n"
			."- رسوم التسجيل : [frais_inscription] ؛\n"
			."- الرسوم الشهرية : [mensualite]، تُدفع قبل اليوم [jour_limite] من كل شهر، من [premier_mois] إلى [dernier_mois].\n"
			."وأقر بأنه في حالة التأخر يمكن للمدرسة تعليق تسليم كشوف الدرجات أو المشاركة في الامتحانات. والمبالغ المدفوعة غير قابلة للاسترجاع إلا بقرار من الإدارة.", 0),
	);
}

/**
 * Jours fériés fixes de la Mauritanie : [ref, français, arabe, mois, jour].
 * Les fêtes religieuses (dates variables) sont à saisir chaque année.
 *
 * @return array
 */
function ecole_defaut_feries()
{
	return array(
		array('NOUVEL_AN', 'Nouvel an', 'رأس السنة الميلادية', 1, 1),
		array('TRAVAIL', 'Fête du travail', 'عيد العمال', 5, 1),
		array('AFRIQUE', 'Journée de l\'Afrique', 'يوم إفريقيا', 5, 25),
		array('FORCES', 'Fête des forces armées', 'عيد القوات المسلحة', 7, 10),
		array('INDEPENDANCE', 'Fête de l\'indépendance', 'عيد الاستقلال', 11, 28),
	);
}

/**
 * Réglages par défaut (posés seulement s'ils ne sont pas déjà réglés) : constante => valeur.
 *
 * @return array<string,string>
 */
function ecole_defaut_reglages()
{
	return array(
		'ELEVES_MOIS_PAYANTS' => '10,11,12,1,2,3,4,5,6',
		'ELEVES_JOUR_LIMITE' => '10',
		'ELEVES_RECU_FORMAT' => 'A5',
		'ELEVES_WHATSAPP_INDICATIF' => '222',
		'ELEVES_SEUIL_ABSENCES' => '10',
		'ELEVES_SEUIL_RETARDS' => '5',
		'ECOLE_JOURS_OUVRABLES' => '1,2,3,4,5,6',
	);
}

/**
 * Modes de paiement locaux ajoutés à Dolibarr (+ un compte chacun) : [code, libellé, libellé du compte].
 * « LIQ » (espèces) existe déjà dans Dolibarr : il est relié au compte « Caisse espèces ».
 *
 * @return array
 */
function ecole_defaut_modes_paiement()
{
	return array(
		array('BANKIL', 'Bankily', 'Bankily'),
		array('MASRVI', 'Masrvi', 'Masrvi'),
		array('SEDAD', 'Sedad', 'Sedad'),
	);
}

/**
 * Année scolaire de départ (année de la rentrée) pour les jours fériés.
 *
 * @return int
 */
function ecole_defaut_annee()
{
	$a = function_exists('getDolGlobalInt') ? getDolGlobalInt('ELEVES_ANNEE_SCOLAIRE') : 0;
	if ($a > 2000) {
		return $a;
	}
	return ((int) date('n') >= 8) ? (int) date('Y') : (int) date('Y') - 1;
}

/**
 * Requêtes d'installation par module.
 *
 * @param  string   $module   classes | eleves | notes | personnel
 * @param  string   $p        Préfixe des tables (llx_)
 * @param  int      $e        Entité
 * @param  string   $now      Date SQL entre quotes
 * @param  callable $esc      Échappement d'une chaîne SQL
 * @param  callable $vide     function(string $table): bool — la table est-elle vide ? (null = toujours installer)
 * @return string[]
 */
function ecole_defaut_sql($module, $p, $e, $now, $esc, $vide = null)
{
	$q = function ($s) use ($esc) {
		return "'".call_user_func($esc, $s)."'";
	};
	$installer = function ($table) use ($vide) {
		return $vide === null || call_user_func($vide, $table);
	};
	$sql = array();
	if ($module === 'classes') {
		if ($installer('ecole_section')) {
			foreach (ecole_defaut_sections() as $d) {
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_section (entity, ref, label_fr, label_ar, position, date_creation, status) VALUES (".$e.", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", ".$d[3].", ".$now.", 1)";
			}
		}
		if ($installer('ecole_creneau')) {
			foreach (ecole_defaut_creneaux() as $d) {
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_creneau (entity, ref, label_fr, label_ar, heure_debut, heure_fin, position, date_creation, status) VALUES (".$e.", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", ".$q($d[3]).", ".$q($d[4]).", ".$d[5].", ".$now.", 1)";
			}
		}
		if ($installer('ecole_matiere')) {
			foreach (ecole_defaut_matieres() as $d) {
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_matiere (entity, ref, code, label_fr, label_ar, langue, date_creation, status) VALUES (".$e.", ".$q($d[0]).", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", ".$q($d[3]).", ".$now.", 1)";
			}
		}
		// Modèles de PDF de départ : un par type de document, par défaut (modifiables, duplicables)
		if ($installer('ecole_pdf_modele')) {
			foreach (array(array('LISTE', 'Liste standard', 'قائمة عادية', 'liste'), array('RECU', 'Reçu standard', 'وصل عادي', 'recu'), array('PAIE', 'Bulletin de paie standard', 'كشف راتب عادي', 'paie')) as $i => $d) {
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_pdf_modele (entity, ref, label_fr, label_ar, type_doc, couleur, orientation, taille_police, filigrane, par_defaut, position, date_creation, status) VALUES (".$e.", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", ".$q($d[3]).", 'bleu', 'auto', 8, 'defaut', 1, ".(($i + 1) * 10).", ".$now.", 1)";
			}
		}
		if ($installer('ecole_classe')) {
			foreach (ecole_defaut_classes() as $d) {
				$niv = "(SELECT n.rowid FROM ".$p."ecole_niveau n WHERE n.entity = ".$e." AND n.ref = ".$q($d[3]).")";
				$sec = $d[4] !== '' ? "(SELECT s.rowid FROM ".$p."ecole_section s WHERE s.entity = ".$e." AND s.ref = ".$q($d[4]).")" : "NULL";
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_classe (entity, ref, label_fr, label_ar, fk_niveau, fk_section, effectif_max, mensualite, frais_inscription, date_creation, status)"
					." SELECT ".$e.", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", ".$niv.", ".$sec.", 100, 0, 0, ".$now.", 1 FROM DUAL WHERE ".$niv." IS NOT NULL";
			}
		}
	} elseif ($module === 'eleves') {
		if ($installer('ecole_frais_type')) {
			foreach (ecole_defaut_frais() as $d) {
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_frais_type (entity, ref, label_fr, label_ar, montant, position, date_creation, status) VALUES (".$e.", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", 0, ".$d[3].", ".$now.", 1)";
			}
		}
		if ($installer('ecole_engagement')) {
			foreach (ecole_defaut_engagements() as $i => $d) {
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_engagement (entity, ref, label_fr, label_ar, texte_fr, texte_ar, signature_eleve, position, date_creation, status) VALUES (".$e.", ".$q($d[0]).", ".$q($d[1]).", ".$q($d[2]).", ".$q($d[3]).", ".$q($d[4]).", ".$d[5].", ".(($i + 1) * 10).", ".$now.", 1)";
			}
		}
		foreach (ecole_defaut_modes_paiement() as $d) {
			$sql[] = "INSERT IGNORE INTO ".$p."c_paiement (entity, code, libelle, type, active, position) VALUES (".$e.", ".$q($d[0]).", ".$q($d[1]).", 2, 1, 0)";
		}
	} elseif ($module === 'notes') {
		$sql[] = "INSERT IGNORE INTO ".$p."ecole_note_mention (entity, ref, label_fr, label_ar, seuil, date_creation, status) VALUES (".$e.", 'PTR', 'P.Tr', NULL, 8, ".$now.", 1)";
	} elseif ($module === 'personnel') {
		if ($installer('ecole_jour_sans_cours')) {
			$annee = ecole_defaut_annee();
			foreach (ecole_defaut_feries() as $d) {
				// Année scolaire : de septembre (rentrée) à août
				$an = ($d[3] >= 9) ? $annee : $annee + 1;
				$date = sprintf('%04d-%02d-%02d', $an, $d[3], $d[4]);
				$sql[] = "INSERT IGNORE INTO ".$p."ecole_jour_sans_cours (entity, ref, label_fr, label_ar, date_debut, date_fin, description, date_creation, status) VALUES (".$e.", ".$q($d[0].'-'.$an).", ".$q($d[1]).", ".$q($d[2]).", '".$date."', '".$date."', 'Jour férié', ".$now.", 1)";
			}
		}
	}
	return $sql;
}

/**
 * Pose les réglages par défaut qui ne sont pas encore réglés.
 *
 * @param  DoliDB $db Handler base
 * @return void
 */
function ecole_defaut_poser_reglages($db)
{
	global $conf;
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	foreach (ecole_defaut_reglages() as $const => $val) {
		if (getDolGlobalString($const) === '') {
			dolibarr_set_const($db, $const, $val, 'chaine', 0, '', $conf->entity);
		}
	}
}

/**
 * Crée les comptes (Bankily, Masrvi, Sedad, Caisse espèces) s'ils n'existent pas et les relie aux modes
 * de paiement (réglage ELEVES_COMPTE_MODE_<id> de la configuration des paiements), si le module Banque est actif.
 *
 * @param  DoliDB $db   Handler base
 * @param  User   $user Utilisateur
 * @return void
 */
function ecole_defaut_comptes($db, $user)
{
	global $conf, $mysoc;
	if (!isModEnabled('banque') || !is_object($mysoc) || empty($mysoc->country_id)) {
		return;
	}
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
	$p = $db->prefix();
	$comptes = array();
	foreach (ecole_defaut_modes_paiement() as $d) {
		$comptes[] = array($d[0], strtoupper($d[0]), $d[2], Account::TYPE_CURRENT);
	}
	$comptes[] = array('LIQ', 'CAISSE', 'Caisse espèces', Account::TYPE_CASH);
	foreach ($comptes as $c) {
		// Compte existant (même référence) ou nouveau
		$accid = 0;
		$resql = $db->query("SELECT rowid FROM ".$p."bank_account WHERE entity = ".((int) $conf->entity)." AND ref = '".$db->escape($c[1])."'");
		if ($resql && ($o = $db->fetch_object($resql))) {
			$accid = (int) $o->rowid;
		} else {
			$acc = new Account($db);
			$acc->ref = $c[1];
			$acc->label = $c[2];
			$acc->type = $c[3];
			$acc->courant = $c[3];
			$acc->currency_code = $conf->currency;
			$acc->country_id = $mysoc->country_id;
			$acc->date_solde = dol_now();
			$acc->balance = 0;
			$acc->status = 0;
			$accid = $acc->create($user);
		}
		if ($accid <= 0) {
			continue;
		}
		$resql = $db->query("SELECT id FROM ".$p."c_paiement WHERE code = '".$db->escape($c[0])."' AND entity IN (".getEntity('c_paiement').")");
		if ($resql && ($o = $db->fetch_object($resql)) && getDolGlobalString('ELEVES_COMPTE_MODE_'.((int) $o->id)) === '') {
			dolibarr_set_const($db, 'ELEVES_COMPTE_MODE_'.((int) $o->id), $accid, 'chaine', 0, '', $conf->entity);
		}
	}
}

// Appel en ligne de commande : écrit le fichier SQL autonome (préfixe llx_, entité 1)
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
	$esc = function ($s) {
		return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $s);
	};
	echo "-- Données par défaut d'une nouvelle école (à exécuter dans la base Dolibarr, tables llx_, entité 1).\n";
	echo "-- Équivalent de ce qu'installe l'activation des modules Classes, Élèves, Notes et Personnel.\n";
	echo "-- INSERT IGNORE : sans effet sur ce qui existe déjà (même référence).\n";
	echo "-- À exécuter après l'activation des modules (les tables et les niveaux doivent exister).\n\n";
	foreach (array('classes' => 'Classes : séries, créneaux, matières, classes', 'eleves' => 'Élèves : autres frais, modes de paiement', 'notes' => 'Notes : mention P.Tr', 'personnel' => 'Personnel : jours fériés fixes (année scolaire '.ecole_defaut_annee().'-'.(ecole_defaut_annee() + 1).')') as $m => $titre) {
		echo "-- ".$titre."\n";
		foreach (ecole_defaut_sql($m, 'llx_', 1, 'NOW()', $esc) as $s) {
			echo $s.";\n";
		}
		echo "\n";
	}
	echo "-- Réglages par défaut (seulement s'ils ne sont pas déjà réglés)\n";
	foreach (ecole_defaut_reglages() as $k => $v) {
		echo "INSERT IGNORE INTO llx_const (name, entity, value, type, visible, note) VALUES ('".$k."', 1, '".$v."', 'chaine', 0, '');\n";
	}
}
