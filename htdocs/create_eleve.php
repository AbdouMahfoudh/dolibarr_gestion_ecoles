<?php
// Script de création d'un élève
require 'includes/user.class.php';
require_once 'conf/conf.php';
require 'includes/database/drivers/mysqli.class.php';
require 'core/db/MysqliDB.class.php';
require 'core/lib/date.lib.php';
require 'custom/eleves/class/ecole_eleve.class.php';
require 'custom/eleves/class/ecole_responsable.class.php';
require 'custom/classes/class/ecole_classe.class.php';

// Connexion à la base de données
$db = new DoliDB($dolibarr_main_db_type, $dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port);

echo "Connexion à la base de données...\n";

// Vérifier les classes disponibles
$sql = "SELECT rowid, ref, label_fr, fk_niveau FROM llx_ecole_classe WHERE status = 1 LIMIT 10";
$result = $db->query($sql);
echo "Classes disponibles:\n";
while ($row = $db->fetch_object($result)) {
	echo "- ID: " . $row->rowid . ", Ref: " . $row->ref . ", Label: " . $row->label_fr . "\n";
}

// Vérifier les niveaux
$sql = "SELECT rowid, ref, label_fr FROM llx_ecole_niveau WHERE status = 1";
$result = $db->query($sql);
echo "\nNiveaux:\n";
while ($row = $db->fetch_object($result)) {
	echo "- ID: " . $row->rowid . ", Ref: " . $row->ref . ", Label: " . $row->label_fr . "\n";
}

echo "\nFait!\n";
